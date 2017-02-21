<?php

/**
 * Detects VCard attachments and show a button to add them to address book
 * Adds possibility to attach a contact vcard to mail messages
 *
 * @license GNU GPLv3+
 * @author Thomas Bruederli, Aleksander Machniak
 */
class vcard_attachments extends rcube_plugin
{
    public $task = 'mail';

    private $message;
    private $vcard_parts  = array();
    private $vcard_bodies = array();

    function init()
    {
        $rcmail = rcmail::get_instance();

        if ($rcmail->action == 'show' || $rcmail->action == 'preview') {
            $this->add_hook('message_load', array($this, 'message_load'));
            $this->add_hook('template_object_messagebody', array($this, 'html_output'));
        }
        else if ($rcmail->action == 'upload') {
            $this->add_hook('attachment_from_uri', array($this, 'attach_vcard'));
        }
        else if ($rcmail->action == 'compose' && !$rcmail->output->framed) {
            $skin_path = $this->local_skin_path();
            $btn_class = strpos($skin_path, 'classic') ? 'button' : 'listbutton';

            $this->add_texts('localization', true);
            $this->include_stylesheet($skin_path . '/style.css');
            $this->include_script('vcardattach.js');
            $this->add_button(
                array(
                    'type'     => 'link',
                    'label'    => 'vcard_attachments.vcard',
                    'command'  => 'attach-vcard',
                    'class'    => $btn_class . ' vcard disabled',
                    'classact' => $btn_class . ' vcard',
                    'title'    => 'vcard_attachments.attachvcard',
                    'innerclass' => 'inner',
                ),
                'compose-contacts-toolbar');
        }
        else if (!$rcmail->output->framed && (!$rcmail->action || $rcmail->action == 'list')) {
            $icon = 'plugins/vcard_attachments/' .$this->local_skin_path(). '/vcard.png';
            $rcmail->output->set_env('vcard_icon', $icon);
            $this->include_script('vcardattach.js');
        }

        $this->register_action('plugin.savevcard', array($this, 'save_vcard'));
    }

    /**
     * Check message bodies and attachments for vcards
     */
    function message_load($p)
    {
        $this->message = $p['object'];

        // handle attachments vcard attachments
        foreach ((array)$this->message->attachments as $attachment) {
            if ($this->is_vcard($attachment)) {
                $this->vcard_parts[] = $attachment->mime_id;
            }
        }
        // the same with message bodies
        foreach ((array)$this->message->parts as $part) {
            if ($this->is_vcard($part)) {
                $this->vcard_parts[]  = $part->mime_id;
                $this->vcard_bodies[] = $part->mime_id;
            }
        }

        if ($this->vcard_parts) {
            $this->add_texts('localization');
        }
    }

    /**
     * This callback function adds a box below the message content
     * if there is a vcard attachment available
     */
    function html_output($p)
    {
        $attach_script = false;

        foreach ($this->vcard_parts as $part) {
            $vcards = rcube_vcard::import($this->message->get_part_content($part, null, true));

            // successfully parsed vcards?
            if (empty($vcards)) {
                continue;
            }

            // remove part's body
            if (in_array($part, $this->vcard_bodies)) {
                $p['content'] = '';
            }

            foreach ($vcards as $idx => $vcard) {
                // skip invalid vCards
                if (empty($vcard->email) || empty($vcard->email[0])) {
                    continue;
                }

                $display = $vcard->displayname . ' <'.$vcard->email[0].'>';

                // add box below message body
                $p['content'] .= html::p(array('class' => 'vcardattachment'),
                    html::a(array(
                        'href'    => "#",
                        'onclick' => "return plugin_vcard_save_contact('" . rcube::JQ($part.':'.$idx) . "')",
                        'title'   => $this->gettext('addvcardmsg'),
                        ),
                        html::span(null, rcube::Q($display)))
                );
            }

            $attach_script = true;
        }

        if ($attach_script) {
            $this->include_script('vcardattach.js');
            $this->include_stylesheet($this->local_skin_path() . '/style.css');
        }

        return $p;
    }

    /**
     * Handler for request action
     */
    function save_vcard()
    {
        $this->add_texts('localization', true);

        $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox    = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $mime_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_POST);

        $rcmail  = rcmail::get_instance();
        $message = new rcube_message($uid, $mbox);

        if ($uid && $mime_id) {
            list($mime_id, $index) = explode(':', $mime_id);
            $part = $message->get_part_content($mime_id, null, true);
        }

        $error_msg = $this->gettext('vcardsavefailed');

        if ($part && ($vcards = rcube_vcard::import($part))
            && ($vcard = $vcards[$index]) && $vcard->displayname && $vcard->email
        ) {
            $CONTACTS = $this->get_address_book();
            $email    = $vcard->email[0];
            $contact  = $vcard->get_assoc();
            $valid    = true;

            // skip entries without an e-mail address or invalid
            if (empty($email) || !$CONTACTS->validate($contact, true)) {
                $valid = false;
            }
            else {
                // We're using UTF8 internally
                $email = rcube_utils::idn_to_utf8($email);

                // compare e-mail address
                $existing = $CONTACTS->search('email', $email, 1, false);
                // compare display name
                if (!$existing->count && $vcard->displayname) {
                    $existing = $CONTACTS->search('name', $vcard->displayname, 1, false);
                }

                if ($existing->count) {
                    $rcmail->output->command('display_message', $this->gettext('contactexists'), 'warning');
                    $valid = false;
                }
            }

            if ($valid) {
                $plugin = $rcmail->plugins->exec_hook('contact_create', array('record' => $contact, 'source' => null));
                $contact = $plugin['record'];

                if (!$plugin['abort'] && $CONTACTS->insert($contact))
                    $rcmail->output->command('display_message', $this->gettext('addedsuccessfully'), 'confirmation');
                else
                    $rcmail->output->command('display_message', $error_msg, 'error');
            }
        }
        else {
            $rcmail->output->command('display_message', $error_msg, 'error');
        }

        $rcmail->output->send();
    }

    /**
     * Checks if specified message part is a vcard data
     *
     * @param rcube_message_part Part object
     *
     * @return boolean True if part is of type vcard
     */
    function is_vcard($part)
    {
        return (
            // Content-Type: text/vcard;
            $part->mimetype == 'text/vcard' ||
            // Content-Type: text/x-vcard;
            $part->mimetype == 'text/x-vcard' ||
            // Content-Type: text/directory; profile=vCard;
            ($part->mimetype == 'text/directory' && (
                ($part->ctype_parameters['profile'] &&
                    strtolower($part->ctype_parameters['profile']) == 'vcard')
            // Content-Type: text/directory; (with filename=*.vcf)
                    || ($part->filename && preg_match('/\.vcf$/i', $part->filename))
                )
            )
        );
    }

    /**
     * Getter for default (writable) addressbook
     */
    private function get_address_book()
    {
        if ($this->abook) {
            return $this->abook;
        }

        $rcmail = rcmail::get_instance();
        $abook  = $rcmail->config->get('default_addressbook');

        // Get configured addressbook
        $CONTACTS = $rcmail->get_address_book($abook, true);

        // Get first writeable addressbook if the configured doesn't exist
        // This can happen when user deleted the addressbook (e.g. Kolab folder)
        if ($abook === null || $abook === '' || !is_object($CONTACTS)) {
            $source   = reset($rcmail->get_address_sources(true));
            $CONTACTS = $rcmail->get_address_book($source['id'], true);
        }

        return $this->abook = $CONTACTS;
    }

    /**
     * Attaches a contact vcard to composed mail
     */
    public function attach_vcard($args)
    {
        if (preg_match('|^vcard://(.+)$|', $args['uri'], $m)) {
            list($cid, $source) = explode('-', $m[1]);

            $vcard  = $this->get_contact_vcard($source, $cid, $filename);
            $params = array(
                'filename' => $filename,
                'mimetype' => 'text/vcard',
            );

            if ($vcard) {
                $args['attachment'] = rcmail_save_attachment($vcard, null, $args['compose_id'], $params);
            }
        }

        return $args;
    }

    /**
     * Get vcard data for specified contact
     */
    private function get_contact_vcard($source, $cid, &$filename = null)
    {
        $rcmail  = rcmail::get_instance();
        $source  = $rcmail->get_address_book($source);
        $contact = $source->get_record($cid, true);

        if ($contact) {
            $fieldmap = $source ? $source->vcard_map : null;

            if (empty($contact['vcard'])) {
                $vcard = new rcube_vcard('', RCUBE_CHARSET, false, $fieldmap);
                $vcard->reset();

                foreach ($contact as $key => $values) {
                    list($field, $section) = explode(':', $key);
                    // avoid unwanted casting of DateTime objects to an array
                    // (same as in rcube_contacts::convert_save_data())
                    if (is_object($values) && is_a($values, 'DateTime')) {
                        $values = array($values);
                    }

                    foreach ((array) $values as $value) {
                        if (is_array($value) || is_a($value, 'DateTime') || @strlen($value)) {
                            $vcard->set($field, $value, strtoupper($section));
                        }
                    }
                }

                $contact['vcard'] = $vcard->export();
            }

            $name     = rcube_addressbook::compose_list_name($contact);
            $filename = (self::parse_filename($name) ?: 'contact') . '.vcf';

            // fix folding and end-of-line chars
            $vcard = preg_replace('/\r|\n\s+/', '', $contact['vcard']);
            $vcard = preg_replace('/\n/', rcube_vcard::$eol, $vcard);

            return rcube_vcard::rfc2425_fold($vcard) . rcube_vcard::$eol;
        }
    }

    /**
     * Helper function to convert contact name into filename
     */
    static private function parse_filename($str)
    {
        $str = preg_replace('/[\t\n\r\0\x0B:\/]+\s*/', ' ', $str);

        return trim($str, " ./_");
    }
}
