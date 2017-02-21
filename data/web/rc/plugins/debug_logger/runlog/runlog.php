<?php

/**
 * runlog
 *
 * @author Ziba Scott <ziba@umich.edu>
 */
class runlog {

    private $start_time   = FALSE;
    private $parent_stack = array();
    private $file_handles = array();
    private $indent       = 0;
    private $run_log      = array();

    public $print_to_console = FALSE;
    public $threshold        = 0;
    public $tag_count        = array();
    public $timestamp        = "d-M-Y H:i:s O";
    public $max_line_size    = 150;

    function runlog()
    {
        $this->start_time = microtime(true);
    }

    public function start($name, $tag = false)
    {
        $this->run_log[] = array(
                'type'    => 'start',
                'tag'     => $tag,
                'index'   => count($this->run_log),
                'value'   => $name,
                'time'    => microtime(true),
                'parents' => $this->parent_stack,
                'ended'   => false,
        );

        $this->parent_stack[] = $name;

        $this->print_to_console("start: ".$name, $tag, 'start');
        $this->print_to_file("start: ".$name, $tag, 'start');
        $this->indent++;
    }

    public function end()
    {
        $name = array_pop($this->parent_stack);
        foreach ($this->run_log as $k => $entry) {
            if ($entry['value'] == $name && $entry['type'] == 'start' && !$entry['ended']) {
                $lastk = $k;
            }
        }

        $start = $this->run_log[$lastk]['time'];
        $this->run_log[$lastk]['duration'] = microtime(true) - $start;
        $this->run_log[$lastk]['ended'] = true;
        $this->run_log[] = array(
                'type'     => 'end',
                'tag'      =>  $this->run_log[$lastk]['tag'],
                'index'    => $lastk,
                'value'    => $name,
                'time'     => microtime(true),
                'duration' => microtime(true) - $start,
                'parents'  => $this->parent_stack,
        );

        $this->indent--;
        if ($this->run_log[$lastk]['duration'] >= $this->threshold) {
            $tag_report = "";
            foreach($this->tag_count as $tag => $count){
                $tag_report .= "$tag: $count, ";
            }
            if (!empty($tag_report)) {
//                $tag_report = "\n$tag_report\n";
            }
            $end_txt = sprintf("end: $name - %0.4f seconds $tag_report", $this->run_log[$lastk]['duration']);
            $this->print_to_console($end_txt, $this->run_log[$lastk]['tag'], 'end');
            $this->print_to_file($end_txt,  $this->run_log[$lastk]['tag'], 'end');
        }
    }

    public function increase_tag_count($tag)
    {
        if (!isset($this->tag_count[$tag])) {
            $this->tag_count[$tag] = 0;
        }

        $this->tag_count[$tag]++;
    }

    public function get_text()
    {
        $text = "";
        foreach ($this->run_log as $entry){
            $text .= str_repeat("   ",count($entry['parents']));
            if ($entry['tag'] != 'text'){
                $text .= $entry['tag'].': ';
            }
            $text .= $entry['value'];

            if ($entry['tag'] == 'end') {
                $text .= sprintf(" - %0.4f seconds", $entry['duration']);
            }

            $text .= "\n";
        }

        return $text;
    }

    public function set_file($filename, $tag = 'master')
    {
        if (!isset($this->file_handle[$tag])) {
            $this->file_handles[$tag] = fopen($filename, 'a');
            if (!$this->file_handles[$tag]) {
                trigger_error('Could not open file for writing: '.$filename);
            }
        }
    }

    public function note($msg, $tag = false)
    {
        if ($tag) {
            $this->increase_tag_count($tag);
        }
        if (is_array($msg)) {
            $msg = '<pre>' . print_r($msg, true) . '</pre>';
        }
        $this->debug_messages[] = $msg;
        $this->run_log[] = array(
                'type'    => 'note',
                'tag'     => $tag ?: 'text',
                'value'   => htmlentities($msg),
                'time'    => microtime(true),
                'parents' => $this->parent_stack,
        );

        $this->print_to_file($msg, $tag);
        $this->print_to_console($msg, $tag);
    }

    public function print_to_file($msg, $tag = false, $type = false)
    {
        if (!$tag) {
            $file_handle_tag = 'master';
        }
        else{
            $file_handle_tag = $tag;
        }

        if ($file_handle_tag != 'master' && isset($this->file_handles[$file_handle_tag])) {
            $buffer = $this->get_indent();
            $buffer .= "$msg\n";
            if (!empty($this->timestamp)) {
                $buffer = sprintf("[%s] %s",date($this->timestamp, time()), $buffer);
            }
            fwrite($this->file_handles[$file_handle_tag], wordwrap($buffer, $this->max_line_size, "\n     "));
        }

        if (isset($this->file_handles['master']) && $this->file_handles['master']) {
            $buffer = $this->get_indent();
            if ($tag) {
                $buffer .= "$tag: ";
            }
            $msg = str_replace("\n","",$msg);
            $buffer .= "$msg";
            if (!empty($this->timestamp)) {
                $buffer = sprintf("[%s] %s",date($this->timestamp, time()), $buffer);
            }
            if(strlen($buffer) > $this->max_line_size){
                $buffer = substr($buffer,0,$this->max_line_size - 3) . "...";
            }
            fwrite($this->file_handles['master'], $buffer."\n");
        }
    }

    public function print_to_console($msg, $tag = false)
    {
        if ($this->print_to_console) {
            if (is_array($this->print_to_console)) {
                if (in_array($tag, $this->print_to_console)) {
                    echo $this->get_indent();
                    if ($tag) {
                        echo "$tag: ";
                    }
                    echo "$msg\n";
                }
            }
            else {
                echo $this->get_indent();
                if ($tag) {
                    echo "$tag: ";
                }
                echo "$msg\n";
            }
        }
    }

    public function print_totals()
    {
        $totals = array();
        foreach ($this->run_log as $entry) {
            if ($entry['type'] == 'start' && $entry['ended']) {
                $totals[$entry['value']]['duration'] += $entry['duration'];
                $totals[$entry['value']]['count'] += 1;
            }
        }

        if ($this->file_handle) {
            foreach ($totals as $name => $details) {
                fwrite($this->file_handle,$name.": ".number_format($details['duration'],4)."sec,  ".$details['count']." calls \n");
            }
        }
    }

    private function get_indent()
    {
        $buf = "";
        for ($i = 0; $i < $this->indent; $i++) {
            $buf .= "  ";
        }
        return $buf;
    }


    function  __destruct()
    {
        foreach ($this->file_handles as $handle) {
            fclose($handle);
        }
    }
}
