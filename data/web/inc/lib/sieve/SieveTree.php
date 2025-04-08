<?php namespace Sieve;

class SieveTree
{
    protected $childs_;
    protected $parents_;
    protected $nodes_;
    protected $max_id_;
    protected $dump_;

    public function __construct($name = 'tree')
    {
        $this->childs_ = array();
        $this->parents_ = array();
        $this->nodes_ = array();
        $this->max_id_ = 0;

        $this->parents_[0] = null;
        $this->nodes_[0] = $name;
    }

    public function addChild(SieveDumpable $child)
    {
        return $this->addChildTo($this->max_id_, $child);
    }

    public function addChildTo($parent_id, SieveDumpable $child)
    {
        if (!is_int($parent_id)
         || !isset($this->nodes_[$parent_id]))
            return null;

        if (!isset($this->childs_[$parent_id]))
            $this->childs_[$parent_id] = array();

        $child_id = ++$this->max_id_;
        $this->nodes_[$child_id] = $child;
        $this->parents_[$child_id] = $parent_id;
        array_push($this->childs_[$parent_id], $child_id);

        return $child_id;
    }

    public function getRoot()
    {
        return 0;
    }

    public function getChilds($node_id)
    {
        if (!is_int($node_id)
        || !isset($this->nodes_[$node_id]))
            return null;

        if (!isset($this->childs_[$node_id]))
            return array();

        return $this->childs_[$node_id];
    }

    public function getNode($node_id)
    {
        if ($node_id == 0 || !is_int($node_id)
         || !isset($this->nodes_[$node_id]))
            return null;

        return $this->nodes_[$node_id];
    }

    public function dump()
    {
        $this->dump_ = $this->nodes_[$this->getRoot()] ."\n";
        $this->dumpChilds_($this->getRoot(), ' ');
        return $this->dump_;
    }

    protected function dumpChilds_($parent_id, $prefix)
    {
        if (!isset($this->childs_[$parent_id]))
            return;

        $childs = $this->childs_[$parent_id];
        $last_child = count($childs);

        for ($i=1; $i <= $last_child; ++$i)
        {
            $child_node = $this->nodes_[$childs[$i-1]];
            $infix = ($i == $last_child ? '`--- ' : '|--- ');
            $this->dump_ .= $prefix . $infix . $child_node->dump() . " (id:" . $childs[$i-1] . ")\n";

            $next_prefix = $prefix . ($i == $last_child ? '   ' : '|  ');
            $this->dumpChilds_($childs[$i-1], $next_prefix);
        }
    }

    public function getText()
    {
        $this->dump_ = '';
        $this->childText_($this->getRoot());
        return $this->dump_;
    }

    protected function childText_($parent_id)
    {
        if (!isset($this->childs_[$parent_id]))
            return;

        $childs = $this->childs_[$parent_id];

        for ($i = 0; $i < count($childs); ++$i)
        {
            $child_node = $this->nodes_[$childs[$i]];
            $this->dump_ .= $child_node->text();
            $this->childText_($childs[$i]);
        }
    }
}
