<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Profiler\Dumper;

use Twig\Profiler\Profile;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HtmlDumper extends BaseDumper
{
    private static $colors = [
        'block' => '#dfd',
        'macro' => '#ddf',
        'template' => '#ffd',
        'big' => '#d44',
    ];

    public function dump(Profile $profile): string
    {
        return '<pre>'.parent::dump($profile).'</pre>';
    }

    protected function formatRoot(Profile $profile): string
    {
        return self::escape($profile->getName());
    }

    protected function formatTemplate(Profile $profile, $prefix): string
    {
        return \sprintf('%s└ <span style="background-color: %s">%s</span>', $prefix, self::$colors['template'], self::escape($profile->getTemplate()));
    }

    protected function formatNonTemplate(Profile $profile, $prefix): string
    {
        return \sprintf('%s└ %s::%s(<span style="background-color: %s">%s</span>)', $prefix, self::escape($profile->getTemplate()), $profile->getType(), self::$colors[$profile->getType()] ?? 'auto', self::escape($profile->getName()));
    }

    protected function formatTime(Profile $profile, $percent): string
    {
        return \sprintf('<span style="color: %s">%.2fms/%.0f%%</span>', $percent > 20 ? self::$colors['big'] : 'auto', $profile->getDuration() * 1000, $percent);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
