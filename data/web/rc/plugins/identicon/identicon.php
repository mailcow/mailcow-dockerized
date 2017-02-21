<?php

/**
 * Identicon
 *
 * Plugin to display a unique github-like identification icons
 * for contacts/addresses that do not have a photo image.
 *
 * @todo: Make it optional and configurable via user preferences
 * @todo: Make color palettes match the curren skin
 * @todo: Implement optional SVG generator
 *
 * @license GNU GPLv3+
 * @author Aleksander Machniak <alec@alec.pl>
 * @website http://roundcube.net
 */
class identicon extends rcube_plugin
{
    public $task = 'addressbook';


    /**
     * Plugin initilization.
     */
    function init()
    {
        $this->add_hook('contact_photo', array($this, 'contact_photo'));
    }

    /**
     * 'contact_photo' hook handler to inject an identicon image
     */
    function contact_photo($args)
    {
        // pre-conditions, exit if photo already exists or invalid input
        if (!empty($args['url']) || !empty($args['data'])
            || (empty($args['record']) && empty($args['email']))
        ) {
            return $args;
        }

        $rcmail = rcmail::get_instance();

        // supporting edit/add action may be tricky, let's not do this
        if ($rcmail->action == 'show' || $rcmail->action == 'photo') {
            $email = $args['email'];
            if (!$email && $args['record']) {
                $addresses = rcube_addressbook::get_col_values('email', $args['record'], true);
                if (!empty($addresses)) {
                    $email = $addresses[0];
                }
            }

            if ($email) {
                require_once __DIR__ . '/identicon_engine.php';

                $identicon = new identicon_engine($email);

                if ($rcmail->action == 'show') {
                    // set photo URL using data-uri
                    if (($icon = $identicon->getBinary()) && ($icon = base64_encode($icon))) {
                        $mimetype    =$identicon->getMimetype();
                        $args['url'] = sprintf('data:%s;base64,%s', $mimetype, $icon);
                    }
                }
                else {
                    // send the icon to the browser
                    $identicon = new identicon_engine($email);
                    if ($identicon->sendOutput()) {
                        exit;
                    }
                }
            }
        }

        return $args;
    }
}
