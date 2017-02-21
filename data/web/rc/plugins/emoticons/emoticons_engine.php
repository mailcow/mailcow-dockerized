<?php

/**
 * @license GNU GPLv3+
 * @author Thomas Bruederli
 * @author Aleksander Machniak
 */
class emoticons_engine
{
    const IMG_PATH = 'program/js/tinymce/plugins/emoticons/img/';

    /**
     * Replaces TinyMCE's emoticon images with plain-text representation
     *
     * @param string $html HTML content
     *
     * @return string HTML content
     */
    public static function icons2text($html)
    {
        $emoticons = array(
            '8-)' => 'smiley-cool',
            ':-#' => 'smiley-foot-in-mouth',
            ':-*' => 'smiley-kiss',
            ':-X' => 'smiley-sealed',
            ':-P' => 'smiley-tongue-out',
            ':-@' => 'smiley-yell',
            ":'(" => 'smiley-cry',
            ':-(' => 'smiley-frown',
            ':-D' => 'smiley-laughing',
            ':-)' => 'smiley-smile',
            ':-S' => 'smiley-undecided',
            ':-$' => 'smiley-embarassed',
            'O:-)' => 'smiley-innocent',
            ':-|' => 'smiley-money-mouth',
            ':-O' => 'smiley-surprised',
            ';-)' => 'smiley-wink',
        );

        foreach ($emoticons as $idx => $file) {
            // <img title="Cry" src="http://.../program/js/tinymce/plugins/emoticons/img/smiley-cry.gif" border="0" alt="Cry" />
            $file      = preg_quote(self::IMG_PATH . $file . '.gif', '/');
            $search[]  = '/<img (title="[a-z ]+" )?src="[^"]+' . $file . '"[^>]+\/>/i';
            $replace[] = $idx;
        }

        return preg_replace($search, $replace, $html);
    }

    /**
     * Replace common plain text emoticons with empticon <img> tags
     *
     * @param string $text Text
     *
     * @return string Converted text
     */
    public static function text2icons($text)
    {
        // This is a lookbehind assertion which will exclude html entities
        // E.g. situation when ";)" in "&quot;)" shouldn't be replaced by the icon
        // It's so long because of assertion format restrictions
        $entity = '(?<!&'
            . '[a-zA-Z0-9]{2}' . '|' . '#[0-9]{2}' . '|'
            . '[a-zA-Z0-9]{3}' . '|' . '#[0-9]{3}' . '|'
            . '[a-zA-Z0-9]{4}' . '|' . '#[0-9]{4}' . '|'
            . '[a-zA-Z0-9]{5}' . '|'
            . '[a-zA-Z0-9]{6}' . '|'
            . '[a-zA-Z0-9]{7}'
            . ')';

        // map of emoticon replacements
        $map = array(
            '/(?<!mailto):D/'   => self::img_tag('smiley-laughing.gif',    ':D'    ),
            '/:-D/'             => self::img_tag('smiley-laughing.gif',    ':-D'   ),
            '/:\(/'             => self::img_tag('smiley-frown.gif',       ':('    ),
            '/:-\(/'            => self::img_tag('smiley-frown.gif',       ':-('   ),
            '/'.$entity.';\)/'  => self::img_tag('smiley-wink.gif',        ';)'    ),
            '/'.$entity.';-\)/' => self::img_tag('smiley-wink.gif',        ';-)'   ),
            '/8\)/'             => self::img_tag('smiley-cool.gif',        '8)'    ),
            '/8-\)/'            => self::img_tag('smiley-cool.gif',        '8-)'   ),
            '/(?<!mailto):O/i'  => self::img_tag('smiley-surprised.gif',   ':O'    ),
            '/(?<!mailto):-O/i' => self::img_tag('smiley-surprised.gif',   ':-O'   ),
            '/(?<!mailto):P/i'  => self::img_tag('smiley-tongue-out.gif',  ':P'    ),
            '/(?<!mailto):-P/i' => self::img_tag('smiley-tongue-out.gif',  ':-P'   ),
            '/(?<!mailto):@/i'  => self::img_tag('smiley-yell.gif',        ':@'    ),
            '/(?<!mailto):-@/i' => self::img_tag('smiley-yell.gif',        ':-@'   ),
            '/O:\)/i'           => self::img_tag('smiley-innocent.gif',    'O:)'   ),
            '/O:-\)/i'          => self::img_tag('smiley-innocent.gif',    'O:-)'  ),
            '/(?<!O):\)/'       => self::img_tag('smiley-smile.gif',       ':)'    ),
            '/(?<!O):-\)/'      => self::img_tag('smiley-smile.gif',       ':-)'   ),
            '/(?<!mailto):\$/'  => self::img_tag('smiley-embarassed.gif',  ':$'    ),
            '/(?<!mailto):-\$/' => self::img_tag('smiley-embarassed.gif',  ':-$'   ),
            '/(?<!mailto):\*/i'  => self::img_tag('smiley-kiss.gif',       ':*'    ),
            '/(?<!mailto):-\*/i' => self::img_tag('smiley-kiss.gif',       ':-*'   ),
            '/(?<!mailto):S/i'  => self::img_tag('smiley-undecided.gif',   ':S'    ),
            '/(?<!mailto):-S/i' => self::img_tag('smiley-undecided.gif',   ':-S'   ),
        );

        return preg_replace(array_keys($map), array_values($map), $text);
    }

    protected static function img_tag($ico, $title)
    {
        return html::img(array('src' => './' . self::IMG_PATH . $ico, 'title' => $title));
    }

    /**
     * Replace emoticon icons <img> 'src' attribute, so it can
     * be replaced with real file by Mail_Mime.
     *
     * @param string &$html HTML content
     *
     * @return array List of image files
     */
    public static function replace(&$html)
    {
        // Replace this:
        // <img src="http[s]://.../tinymce/plugins/emoticons/img/smiley-cool.gif" ... />
        // with this:
        // <img src="/path/on/server/.../tinymce/plugins/emoticons/img/smiley-cool.gif" ... />

        $rcube      = rcube::get_instance();
        $assets_dir = $rcube->config->get('assets_dir');
        $path       = unslashify($assets_dir ?: INSTALL_PATH) . '/' . self::IMG_PATH;
        $offset     = 0;
        $images     = array();

        // remove any null-byte characters before parsing
        $html = preg_replace('/\x00/', '', $html);

        if (preg_match_all('# src=[\'"]([^\'"]+)#', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $m) {
                // find emoticon image tags
                if (preg_match('#'. self::IMG_PATH . '(.*)$#', $m[0], $imatches)) {
                    $image_name = $imatches[1];

                    // sanitize image name so resulting attachment doesn't leave images dir
                    $image_name = preg_replace('/[^a-zA-Z0-9_\.\-]/i', '', $image_name);
                    $image_file = $path . $image_name;

                    // Add the same image only once
                    $images[$image_name] = $image_file;

                    $html    = substr_replace($html, $image_file, $m[1] + $offset, strlen($m[0]));
                    $offset += strlen($image_file) - strlen($m[0]);
                }
            }
        }

        return $images;
    }
}
