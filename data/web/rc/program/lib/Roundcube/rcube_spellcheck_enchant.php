<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) 2011-2013, Kolab Systems AG                             |
 | Copyright (C) 20011-2013, The Roundcube Dev Team                      |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Spellchecking backend implementation to work with Enchant           |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Spellchecking backend implementation to work with Pspell
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_spellcheck_enchant extends rcube_spellcheck_engine
{
    private $enchant_broker;
    private $enchant_dictionary;
    private $matches = array();

    /**
     * Return a list of languages supported by this backend
     *
     * @see rcube_spellcheck_engine::languages()
     */
    function languages()
    {
        $this->init();

        $langs = array();
        if ($dicts = enchant_broker_list_dicts($this->enchant_broker)) {
            foreach ($dicts as $dict) {
                $langs[] = preg_replace('/-.*$/', '', $dict['lang_tag']);
            }
        }

        return array_unique($langs);
    }

    /**
     * Initializes Enchant dictionary
     */
    private function init()
    {
        if (!$this->enchant_broker) {
            if (!extension_loaded('enchant')) {
                $this->error = "Enchant extension not available";
                return;
            }

            $this->enchant_broker = enchant_broker_init();
        }

        if (!enchant_broker_dict_exists($this->enchant_broker, $this->lang)) {
            $this->error = "Unable to load dictionary for selected language using Enchant";
            return;
        }

        $this->enchant_dictionary = enchant_broker_request_dict($this->enchant_broker, $this->lang);
    }

    /**
     * Set content and check spelling
     *
     * @see rcube_spellcheck_engine::check()
     */
    function check($text)
    {
        $this->init();

        if (!$this->enchant_dictionary) {
            return array();
        }

        // tokenize
        $text = preg_split($this->separator, $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

        $diff       = 0;
        $matches    = array();

        foreach ($text as $w) {
            $word = trim($w[0]);
            $pos  = $w[1] - $diff;
            $len  = mb_strlen($word);

            // skip exceptions
            if ($this->dictionary->is_exception($word)) {
            }
            else if (!enchant_dict_check($this->enchant_dictionary, $word)) {
                $suggestions = enchant_dict_suggest($this->enchant_dictionary, $word);

                if (sizeof($suggestions) > self::MAX_SUGGESTIONS) {
                    $suggestions = array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
                }

                $matches[] = array($word, $pos, $len, null, $suggestions);
            }

            $diff += (strlen($word) - $len);
        }

        $this->matches = $matches;
        return $matches;
    }

    /**
     * Returns suggestions for the specified word
     *
     * @see rcube_spellcheck_engine::get_words()
     */
    function get_suggestions($word)
    {
        $this->init();

        if (!$this->enchant_dictionary) {
            return array();
        }

        $suggestions = enchant_dict_suggest($this->enchant_dictionary, $word);

        if (sizeof($suggestions) > self::MAX_SUGGESTIONS)
            $suggestions = array_slice($suggestions, 0, self::MAX_SUGGESTIONS);

        return is_array($suggestions) ? $suggestions : array();
    }

    /**
     * Returns misspelled words
     *
     * @see rcube_spellcheck_engine::get_suggestions()
     */
    function get_words($text = null)
    {
        $result = array();

        if ($text) {
            // init spellchecker
            $this->init();

            if (!$this->enchant_dictionary) {
                return array();
            }

            // With Enchant we don't need to get suggestions to return misspelled words
            $text = preg_split($this->separator, $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

            foreach ($text as $w) {
                $word = trim($w[0]);

                // skip exceptions
                if ($this->dictionary->is_exception($word)) {
                    continue;
                }

                if (!enchant_dict_check($this->enchant_dictionary, $word)) {
                    $result[] = $word;
                }
            }

            return $result;
        }

        foreach ($this->matches as $m) {
            $result[] = $m[0];
        }

        return $result;
    }
}
