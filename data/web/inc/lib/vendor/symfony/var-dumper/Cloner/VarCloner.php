<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VarCloner extends AbstractCloner
{
    private static $gid;
    private static $arrayCache = [];

    /**
     * {@inheritdoc}
     */
    protected function doClone($var)
    {
        $len = 1;                       // Length of $queue
        $pos = 0;                       // Number of cloned items past the minimum depth
        $refsCounter = 0;               // Hard references counter
        $queue = [[$var]];    // This breadth-first queue is the return value
        $hardRefs = [];            // Map of original zval ids to stub objects
        $objRefs = [];             // Map of original object handles to their stub object counterpart
        $objects = [];             // Keep a ref to objects to ensure their handle cannot be reused while cloning
        $resRefs = [];             // Map of original resource handles to their stub object counterpart
        $values = [];              // Map of stub objects' ids to original values
        $maxItems = $this->maxItems;
        $maxString = $this->maxString;
        $minDepth = $this->minDepth;
        $currentDepth = 0;              // Current tree depth
        $currentDepthFinalIndex = 0;    // Final $queue index for current tree depth
        $minimumDepthReached = 0 === $minDepth; // Becomes true when minimum tree depth has been reached
        $cookie = (object) [];          // Unique object used to detect hard references
        $a = null;                      // Array cast for nested structures
        $stub = null;                   // Stub capturing the main properties of an original item value
                                        // or null if the original value is used directly

        if (!$gid = self::$gid) {
            $gid = self::$gid = md5(random_bytes(6)); // Unique string used to detect the special $GLOBALS variable
        }
        $arrayStub = new Stub();
        $arrayStub->type = Stub::TYPE_ARRAY;
        $fromObjCast = false;

        for ($i = 0; $i < $len; ++$i) {
            // Detect when we move on to the next tree depth
            if ($i > $currentDepthFinalIndex) {
                ++$currentDepth;
                $currentDepthFinalIndex = $len - 1;
                if ($currentDepth >= $minDepth) {
                    $minimumDepthReached = true;
                }
            }

            $refs = $vals = $queue[$i];
            foreach ($vals as $k => $v) {
                // $v is the original value or a stub object in case of hard references

                if (\PHP_VERSION_ID >= 70400) {
                    $zvalIsRef = null !== \ReflectionReference::fromArrayElement($vals, $k);
                } else {
                    $refs[$k] = $cookie;
                    $zvalIsRef = $vals[$k] === $cookie;
                }

                if ($zvalIsRef) {
                    $vals[$k] = &$stub;         // Break hard references to make $queue completely
                    unset($stub);               // independent from the original structure
                    if ($v instanceof Stub && isset($hardRefs[spl_object_id($v)])) {
                        $vals[$k] = $refs[$k] = $v;
                        if ($v->value instanceof Stub && (Stub::TYPE_OBJECT === $v->value->type || Stub::TYPE_RESOURCE === $v->value->type)) {
                            ++$v->value->refCount;
                        }
                        ++$v->refCount;
                        continue;
                    }
                    $refs[$k] = $vals[$k] = new Stub();
                    $refs[$k]->value = $v;
                    $h = spl_object_id($refs[$k]);
                    $hardRefs[$h] = &$refs[$k];
                    $values[$h] = $v;
                    $vals[$k]->handle = ++$refsCounter;
                }
                // Create $stub when the original value $v can not be used directly
                // If $v is a nested structure, put that structure in array $a
                switch (true) {
                    case null === $v:
                    case \is_bool($v):
                    case \is_int($v):
                    case \is_float($v):
                        continue 2;
                    case \is_string($v):
                        if ('' === $v) {
                            continue 2;
                        }
                        if (!preg_match('//u', $v)) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_STRING;
                            $stub->class = Stub::STRING_BINARY;
                            if (0 <= $maxString && 0 < $cut = \strlen($v) - $maxString) {
                                $stub->cut = $cut;
                                $stub->value = substr($v, 0, -$cut);
                            } else {
                                $stub->value = $v;
                            }
                        } elseif (0 <= $maxString && isset($v[1 + ($maxString >> 2)]) && 0 < $cut = mb_strlen($v, 'UTF-8') - $maxString) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_STRING;
                            $stub->class = Stub::STRING_UTF8;
                            $stub->cut = $cut;
                            $stub->value = mb_substr($v, 0, $maxString, 'UTF-8');
                        } else {
                            continue 2;
                        }
                        $a = null;
                        break;

                    case \is_array($v):
                        if (!$v) {
                            continue 2;
                        }
                        $stub = $arrayStub;
                        $stub->class = Stub::ARRAY_INDEXED;

                        $j = -1;
                        foreach ($v as $gk => $gv) {
                            if ($gk !== ++$j) {
                                $stub->class = Stub::ARRAY_ASSOC;
                                break;
                            }
                        }
                        $a = $v;

                        if (Stub::ARRAY_ASSOC === $stub->class) {
                            // Copies of $GLOBALS have very strange behavior,
                            // let's detect them with some black magic
                            if (\PHP_VERSION_ID < 80100 && ($a[$gid] = true) && isset($v[$gid])) {
                                unset($v[$gid]);
                                $a = [];
                                foreach ($v as $gk => &$gv) {
                                    if ($v === $gv) {
                                        unset($v);
                                        $v = new Stub();
                                        $v->value = [$v->cut = \count($gv), Stub::TYPE_ARRAY => 0];
                                        $v->handle = -1;
                                        $gv = &$hardRefs[spl_object_id($v)];
                                        $gv = $v;
                                    }

                                    $a[$gk] = &$gv;
                                }
                                unset($gv);
                            } else {
                                $a = $v;
                            }
                        }
                        break;

                    case \is_object($v):
                    case $v instanceof \__PHP_Incomplete_Class:
                        if (empty($objRefs[$h = spl_object_id($v)])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_OBJECT;
                            $stub->class = \get_class($v);
                            $stub->value = $v;
                            $stub->handle = $h;
                            $a = $this->castObject($stub, 0 < $i);
                            if ($v !== $stub->value) {
                                if (Stub::TYPE_OBJECT !== $stub->type || null === $stub->value) {
                                    break;
                                }
                                $stub->handle = $h = spl_object_id($stub->value);
                            }
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos && $minimumDepthReached) {
                                $stub->cut = \count($a);
                                $a = null;
                            }
                        }
                        if (empty($objRefs[$h])) {
                            $objRefs[$h] = $stub;
                            $objects[] = $v;
                        } else {
                            $stub = $objRefs[$h];
                            ++$stub->refCount;
                            $a = null;
                        }
                        break;

                    default: // resource
                        if (empty($resRefs[$h = (int) $v])) {
                            $stub = new Stub();
                            $stub->type = Stub::TYPE_RESOURCE;
                            if ('Unknown' === $stub->class = @get_resource_type($v)) {
                                $stub->class = 'Closed';
                            }
                            $stub->value = $v;
                            $stub->handle = $h;
                            $a = $this->castResource($stub, 0 < $i);
                            $stub->value = null;
                            if (0 <= $maxItems && $maxItems <= $pos && $minimumDepthReached) {
                                $stub->cut = \count($a);
                                $a = null;
                            }
                        }
                        if (empty($resRefs[$h])) {
                            $resRefs[$h] = $stub;
                        } else {
                            $stub = $resRefs[$h];
                            ++$stub->refCount;
                            $a = null;
                        }
                        break;
                }

                if ($a) {
                    if (!$minimumDepthReached || 0 > $maxItems) {
                        $queue[$len] = $a;
                        $stub->position = $len++;
                    } elseif ($pos < $maxItems) {
                        if ($maxItems < $pos += \count($a)) {
                            $a = \array_slice($a, 0, $maxItems - $pos, true);
                            if ($stub->cut >= 0) {
                                $stub->cut += $pos - $maxItems;
                            }
                        }
                        $queue[$len] = $a;
                        $stub->position = $len++;
                    } elseif ($stub->cut >= 0) {
                        $stub->cut += \count($a);
                        $stub->position = 0;
                    }
                }

                if ($arrayStub === $stub) {
                    if ($arrayStub->cut) {
                        $stub = [$arrayStub->cut, $arrayStub->class => $arrayStub->position];
                        $arrayStub->cut = 0;
                    } elseif (isset(self::$arrayCache[$arrayStub->class][$arrayStub->position])) {
                        $stub = self::$arrayCache[$arrayStub->class][$arrayStub->position];
                    } else {
                        self::$arrayCache[$arrayStub->class][$arrayStub->position] = $stub = [$arrayStub->class => $arrayStub->position];
                    }
                }

                if ($zvalIsRef) {
                    $refs[$k]->value = $stub;
                } else {
                    $vals[$k] = $stub;
                }
            }

            if ($fromObjCast) {
                $fromObjCast = false;
                $refs = $vals;
                $vals = [];
                $j = -1;
                foreach ($queue[$i] as $k => $v) {
                    foreach ([$k => true] as $gk => $gv) {
                    }
                    if ($gk !== $k) {
                        $vals = (object) $vals;
                        $vals->{$k} = $refs[++$j];
                        $vals = (array) $vals;
                    } else {
                        $vals[$k] = $refs[++$j];
                    }
                }
            }

            $queue[$i] = $vals;
        }

        foreach ($values as $h => $v) {
            $hardRefs[$h] = $v;
        }

        return $queue;
    }
}
