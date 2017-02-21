<?php

/**
 * Copy a new users identities and contacts from a nearby Squirrelmail installation
 *
 * @version 1.6
 * @author Thomas Bruederli, Johannes Hessellund, pommi, Thomas Lueder
 */
class squirrelmail_usercopy extends rcube_plugin
{
    public $task = 'login';

    private $prefs            = null;
    private $identities_level = 0;
    private $abook            = array();

    public function init()
    {
        $this->add_hook('user_create', array($this, 'create_user'));
        $this->add_hook('identity_create', array($this, 'create_identity'));
    }

    public function create_user($p)
    {
        $rcmail = rcmail::get_instance();

        // Read plugin's config
        $this->initialize();

        // read prefs and add email address
        $this->read_squirrel_prefs($p['user']);
        if (($this->identities_level == 0 || $this->identities_level == 2)
            && $rcmail->config->get('squirrelmail_set_alias')
            && $this->prefs['email_address']
        ) {
            $p['user_email'] = $this->prefs['email_address'];
        }

        return $p;
    }

    public function create_identity($p)
    {
        $rcmail = rcmail::get_instance();

        // prefs are set in create_user()
        if ($this->prefs) {
            if ($this->prefs['full_name']) {
                $p['record']['name'] = $this->prefs['full_name'];
            }

            if (($this->identities_level == 0 || $this->identities_level == 2) && $this->prefs['email_address']) {
                $p['record']['email'] = $this->prefs['email_address'];
            }

            if ($this->prefs['___signature___']) {
                $p['record']['signature'] = $this->prefs['___signature___'];
            }

            if ($this->prefs['reply_to']) {
                $p['record']['reply-to'] = $this->prefs['reply_to'];
            }

            if (($this->identities_level == 0 || $this->identities_level == 1)
                && isset($this->prefs['identities']) && $this->prefs['identities'] > 1
            ) {
                for ($i = 1; $i < $this->prefs['identities']; $i++) {
                    unset($ident_data);
                    $ident_data = array('name' => '', 'email' => ''); // required data

                    if ($this->prefs['full_name'.$i]) {
                        $ident_data['name'] = $this->prefs['full_name'.$i];
                    }

                    if ($this->identities_level == 0 && $this->prefs['email_address'.$i]) {
                        $ident_data['email'] = $this->prefs['email_address'.$i];
                    }
                    else {
                        $ident_data['email'] = $p['record']['email'];
                    }

                    if ($this->prefs['reply_to'.$i]) {
                        $ident_data['reply-to'] = $this->prefs['reply_to'.$i];
                    }

                    if ($this->prefs['___sig'.$i.'___']) {
                        $ident_data['signature'] = $this->prefs['___sig'.$i.'___'];
                    }

                    // insert identity
                    $rcmail->user->insert_identity($ident_data);
                }
            }

            // copy address book
            $contacts  = $rcmail->get_address_book(null, true);
            $addresses = array();
            $groups    = array();

            if ($contacts && !empty($this->abook)) {
                foreach ($this->abook as $rec) {
                    // #1487096: handle multi-address and/or too long items
                    // #1487858: convert multi-address contacts into groups
                    $emails   = preg_split('/[;,]/', $rec['email'], -1, PREG_SPLIT_NO_EMPTY);
                    $group_id = null;

                    // create group for addresses
                    if (count($emails) > 1) {
                        if (!($group_id = $groups[$rec['name']])) {
                            if ($group = $contacts->create_group($rec['name'])) {
                                $group_id = $group['id'];
                                $groups[$rec['name']] = $group_id;
                            }
                        }
                    }

                    // create contacts
                    foreach ($emails as $email) {
                        if (!($contact_id = $addresses[$email]) && rcube_utils::check_email(rcube_utils::idn_to_ascii($email))) {
                            $rec['email'] = rcube_utils::idn_to_utf8($email);
                            if ($contact_id = $contacts->insert($rec, true)) {
                                $addresses[$email] = $contact_id;
                            }
                        }

                        if ($group_id && $contact_id) {
                            $contacts->add_to_group($group_id, array($contact_id));
                        }
                    }
                }
            }

            // mark identity as complete for following hooks
            $p['complete'] = true;
        }

        return $p;
    }

    private function initialize()
    {
        $rcmail = rcmail::get_instance();

        // Load plugin's config file
        $this->load_config();

        // Set identities_level for operations of this plugin
        $ilevel = $rcmail->config->get('squirrelmail_identities_level');
        if ($ilevel === null) {
            $ilevel = $rcmail->config->get('identities_level', 0);
        }

        $this->identities_level = intval($ilevel);
    }

    private function read_squirrel_prefs($uname)
    {
        $rcmail = rcmail::get_instance();

        /**** File based backend ****/
        if ($rcmail->config->get('squirrelmail_driver') == 'file' && ($srcdir = $rcmail->config->get('squirrelmail_data_dir'))) {
            if (($hash_level = $rcmail->config->get('squirrelmail_data_dir_hash_level')) > 0) {
                $srcdir = slashify($srcdir).chunk_split(substr(base_convert(crc32($uname), 10, 16), 0, $hash_level), 1, '/');
            }
            $file_charset = $rcmail->config->get('squirrelmail_file_charset');

            $prefsfile = slashify($srcdir) . $uname . '.pref';
            $abookfile = slashify($srcdir) . $uname . '.abook';
            $sigfile   = slashify($srcdir) . $uname . '.sig';
            $sigbase   = slashify($srcdir) . $uname . '.si';

            if (is_readable($prefsfile)) {
                $this->prefs = array();
                foreach (file($prefsfile) as $line) {
                    list($key, $value) = explode('=', $line);
                    $this->prefs[$key] = $this->convert_charset(rtrim($value), $file_charset);
                }

                // also read signature file if exists
                if (is_readable($sigfile)) {
                    $sig = file_get_contents($sigfile);
                    $this->prefs['___signature___'] = $this->convert_charset($sig, $file_charset);
                }

                if (isset($this->prefs['identities']) && $this->prefs['identities'] > 1) {
                    for ($i=1; $i < $this->prefs['identities']; $i++) {
                        // read signature file if exists
                        if (is_readable($sigbase.$i)) {
                            $sig = file_get_contents($sigbase.$i);
                            $this->prefs['___sig'.$i.'___'] = $this->convert_charset($sig, $file_charset);
                        }
                    }
                }

                // parse addres book file
                if (filesize($abookfile)) {
                    foreach(file($abookfile) as $line) {
                        $line = $this->convert_charset(rtrim($line), $file_charset);
                        list($rec['name'], $rec['firstname'], $rec['surname'], $rec['email']) = explode('|', $line);
                        if ($rec['name'] && $rec['email']) {
                            $this->abook[] = $rec;
                        }
                    }
                }
            }
        }
        // Database backend
        else if ($rcmail->config->get('squirrelmail_driver') == 'sql') { 
            $this->prefs = array();

            // connect to squirrelmail database
            $db = rcube_db::factory($rcmail->config->get('squirrelmail_dsn'));

            $db->set_debug($rcmail->config->get('sql_debug'));
            $db->db_connect('r'); // connect in read mode

            // retrieve prefs
            $userprefs_table = $rcmail->config->get('squirrelmail_userprefs_table');
            $address_table   = $rcmail->config->get('squirrelmail_address_table');
            $db_charset      = $rcmail->config->get('squirrelmail_db_charset');

            if ($db_charset) {
                $db->query('SET NAMES '.$db_charset);
            }

            $sql_result = $db->query('SELECT * FROM ' . $db->quote_identifier($userprefs_table)
                .' WHERE `user` = ?', $uname); // ? is replaced with emailaddress

            while ($sql_array = $db->fetch_assoc($sql_result) ) { // fetch one row from result
                $this->prefs[$sql_array['prefkey']] = rcube_charset::convert(rtrim($sql_array['prefval']), $db_charset);
            }

            // retrieve address table data
            $sql_result = $db->query('SELECT * FROM ' . $db->quote_identifier($address_table)
                .' WHERE `owner` = ?', $uname); // ? is replaced with emailaddress

            // parse addres book
            while ($sql_array = $db->fetch_assoc($sql_result) ) { // fetch one row from result
                $rec['name']      = rcube_charset::convert(rtrim($sql_array['nickname']), $db_charset);
                $rec['firstname'] = rcube_charset::convert(rtrim($sql_array['firstname']), $db_charset);
                $rec['surname']   = rcube_charset::convert(rtrim($sql_array['lastname']), $db_charset);
                $rec['email']     = rcube_charset::convert(rtrim($sql_array['email']), $db_charset);
                $rec['notes']     = rcube_charset::convert(rtrim($sql_array['label']), $db_charset);

                if ($rec['name'] && $rec['email']) {
                    $this->abook[] = $rec;
                }
            }
        } // end if 'sql'-driver
    }

    private function convert_charset($str, $charset = null)
    {
        if (!$charset) {
            return utf8_encode($sig);
        }

        return rcube_charset::convert($str, $charset, RCMAIL_CHARSET);
    }
}
