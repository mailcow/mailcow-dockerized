<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) 2008-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Spellchecking backend implementation to work with Pspell            |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Spellchecking backend implementation to work with Pspell
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_spellcheck_pspell extends rcube_spellcheck_engine
{
    private $plink;
    private $matches = array();

    /**
     * Return a list of languages supported by this backend
     *
     * @see rcube_spellcheck_engine::languages()
     */
    function languages()
    {
        $defaults = array('en');
        $langs = array();

        // get aspell dictionaries
        exec('aspell dump dicts', $dicts);
        if (!empty($dicts)) {
            $seen = array();
            foreach ($dicts as $lang) {
                $lang = preg_replace('/-.*$/', '', $lang);
                $langc = strlen($lang) == 2 ? $lang.'_'.strtoupper($lang) : $lang;
                if (!$seen[$langc]++)
                    $langs[] = $lang;
            }
            $langs = array_unique($langs);
        }
        else {
            $langs = $defaults;
        }

        return $langs;
    }

    /**
     * Initializes PSpell dictionary
     */
    private function init()
    {
        if (!$this->plink) {
            if (!extension_loaded('pspell')) {
                $this->error = "Pspell extension not available";
                return;
            }

            $this->plink = pspell_new($this->lang, null, null, RCUBE_CHARSET, PSPELL_FAST);
        }

        if (!$this->plink) {
            $this->error = "Unable to load Pspell engine for selected language";
        }
    }

    /**
     * Set content and check spelling
     *
     * @see rcube_spellcheck_engine::check()
     */
    function check($text)
    {
        $this->init();

        if (!$this->plink) {
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
            else if (!pspell_check($this->plink, $word)) {
                $suggestions = pspell_suggest($this->plink, $word);

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

        if (!$this->plink) {
            return array();
        }

        $suggestions = pspell_suggest($this->plink, $word);

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

            if (!$this->plink) {
                return array();
            }

            // With PSpell we don't need to get suggestions to return misspelled words
            $text = preg_split($this->separator, $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

            foreach ($text as $w) {
                $word = trim($w[0]);

                // skip exceptions
                if ($this->dictionary->is_exception($word)) {
                    continue;
                }

                if (!pspell_check($this->plink, $word)) {
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
