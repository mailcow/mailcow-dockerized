<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   CSV to vCard data conversion                                        |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * CSV to vCard data converter
 *
 * @package    Framework
 * @subpackage Addressbook
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_csv2vcard
{
    /**
     * CSV to vCard fields mapping
     *
     * @var array
     */
    protected $csv2vcard_map = array(
        // MS Outlook 2010
        'anniversary'           => 'anniversary',
        'assistants_name'       => 'assistant',
        'assistants_phone'      => 'phone:assistant',
        'birthday'              => 'birthday',
        'business_city'         => 'locality:work',
        'business_countryregion' => 'country:work',
        'business_fax'          => 'phone:work,fax',
        'business_phone'        => 'phone:work',
        'business_phone_2'      => 'phone:work2',
        'business_postal_code'  => 'zipcode:work',
        'business_state'        => 'region:work',
        'business_street'       => 'street:work',
        //'business_street_2'     => '',
        //'business_street_3'     => '',
        'car_phone'             => 'phone:car',
        'categories'            => 'groups',
        //'children'              => '',
        'company'               => 'organization',
        //'company_main_phone'    => '',
        'department'            => 'department',
        'email_2_address'       => 'email:other',
        //'email_2_type'          => '',
        'email_3_address'       => 'email:other',
        //'email_3_type'          => '',
        'email_address'         => 'email:pref',
        //'email_type'            => '',
        'first_name'            => 'firstname',
        'gender'                => 'gender',
        'home_city'             => 'locality:home',
        'home_countryregion'    => 'country:home',
        'home_fax'              => 'phone:home,fax',
        'home_phone'            => 'phone:home',
        'home_phone_2'          => 'phone:home2',
        'home_postal_code'      => 'zipcode:home',
        'home_state'            => 'region:home',
        'home_street'           => 'street:home',
        //'home_street_2'         => '',
        //'home_street_3'         => '',
        //'initials'              => '',
        //'isdn'                  => '',
        'job_title'             => 'jobtitle',
        //'keywords'              => '',
        //'language'              => '',
        'last_name'             => 'surname',
        //'location'              => '',
        'managers_name'         => 'manager',
        'middle_name'           => 'middlename',
        //'mileage'               => '',
        'mobile_phone'          => 'phone:cell',
        'notes'                 => 'notes',
        //'office_location'       => '',
        'other_city'            => 'locality:other',
        'other_countryregion'   => 'country:other',
        'other_fax'             => 'phone:other,fax',
        'other_phone'           => 'phone:other',
        'other_postal_code'     => 'zipcode:other',
        'other_state'           => 'region:other',
        'other_street'          => 'street:other',
        //'other_street_2'        => '',
        //'other_street_3'        => '',
        'pager'                 => 'phone:pager',
        'primary_phone'         => 'phone:pref',
        //'profession'            => '',
        //'radio_phone'           => '',
        'spouse'                => 'spouse',
        'suffix'                => 'suffix',
        'title'                 => 'title',
        'web_page'              => 'website:homepage',

        // Thunderbird
        'birth_day'             => 'birthday-d',
        'birth_month'           => 'birthday-m',
        'birth_year'            => 'birthday-y',
        'display_name'          => 'displayname',
        'fax_number'            => 'phone:fax',
        'home_address'          => 'street:home',
        //'home_address_2'        => '',
        'home_country'          => 'country:home',
        'home_zipcode'          => 'zipcode:home',
        'mobile_number'         => 'phone:cell',
        'nickname'              => 'nickname',
        'organization'          => 'organization',
        'pager_number'          => 'phone:pager',
        'primary_email'         => 'email:pref',
        'secondary_email'       => 'email:other',
        'web_page_1'            => 'website:homepage',
        'web_page_2'            => 'website:other',
        'work_phone'            => 'phone:work',
        'work_address'          => 'street:work',
        //'work_address_2'        => '',
        'work_country'          => 'country:work',
        'work_zipcode'          => 'zipcode:work',
        'last'                  => 'surname',
        'first'                 => 'firstname',
        'work_city'             => 'locality:work',
        'work_state'            => 'region:work',
        'home_city_short'       => 'locality:home',
        'home_state_short'      => 'region:home',

        // Atmail
        'date_of_birth'         => 'birthday',
        'email'                 => 'email:pref',
        'home_mobile'           => 'phone:cell',
        'home_zip'              => 'zipcode:home',
        'info'                  => 'notes',
        'user_photo'            => 'photo',
        'url'                   => 'website:homepage',
        'work_company'          => 'organization',
        'work_dept'             => 'departament',
        'work_fax'              => 'phone:work,fax',
        'work_mobile'           => 'phone:work,cell',
        'work_title'            => 'jobtitle',
        'work_zip'              => 'zipcode:work',
        'group'                 => 'groups',

        // GMail
        'groups'                => 'groups',
        'group_membership'      => 'groups',
        'given_name'            => 'firstname',
        'additional_name'       => 'middlename',
        'family_name'           => 'surname',
        'name'                  => 'displayname',
        'name_prefix'           => 'prefix',
        'name_suffix'           => 'suffix',
    );

    /**
     * CSV label to text mapping for English
     *
     * @var array
     */
    protected $label_map = array(
        // MS Outlook 2010
        'anniversary'       => "Anniversary",
        'assistants_name'   => "Assistant's Name",
        'assistants_phone'  => "Assistant's Phone",
        'birthday'          => "Birthday",
        'business_city'     => "Business City",
        'business_countryregion' => "Business Country/Region",
        'business_fax'      => "Business Fax",
        'business_phone'    => "Business Phone",
        'business_phone_2'  => "Business Phone 2",
        'business_postal_code' => "Business Postal Code",
        'business_state'    => "Business State",
        'business_street'   => "Business Street",
        //'business_street_2' => "Business Street 2",
        //'business_street_3' => "Business Street 3",
        'car_phone'         => "Car Phone",
        'categories'        => "Categories",
        //'children'          => "Children",
        'company'           => "Company",
        //'company_main_phone' => "Company Main Phone",
        'department'        => "Department",
        //'directory_server'  => "Directory Server",
        'email_2_address'   => "E-mail 2 Address",
        //'email_2_type'      => "E-mail 2 Type",
        'email_3_address'   => "E-mail 3 Address",
        //'email_3_type'      => "E-mail 3 Type",
        'email_address'     => "E-mail Address",
        //'email_type'        => "E-mail Type",
        'first_name'        => "First Name",
        'gender'            => "Gender",
        'home_city'         => "Home City",
        'home_countryregion' => "Home Country/Region",
        'home_fax'          => "Home Fax",
        'home_phone'        => "Home Phone",
        'home_phone_2'      => "Home Phone 2",
        'home_postal_code'  => "Home Postal Code",
        'home_state'        => "Home State",
        'home_street'       => "Home Street",
        //'home_street_2'     => "Home Street 2",
        //'home_street_3'     => "Home Street 3",
        //'initials'          => "Initials",
        //'isdn'              => "ISDN",
        'job_title'         => "Job Title",
        //'keywords'          => "Keywords",
        //'language'          => "Language",
        'last_name'         => "Last Name",
        //'location'          => "Location",
        'managers_name'     => "Manager's Name",
        'middle_name'       => "Middle Name",
        //'mileage'           => "Mileage",
        'mobile_phone'      => "Mobile Phone",
        'notes'             => "Notes",
        //'office_location'   => "Office Location",
        'other_city'        => "Other City",
        'other_countryregion' => "Other Country/Region",
        'other_fax'         => "Other Fax",
        'other_phone'       => "Other Phone",
        'other_postal_code' => "Other Postal Code",
        'other_state'       => "Other State",
        'other_street'      => "Other Street",
        //'other_street_2'    => "Other Street 2",
        //'other_street_3'    => "Other Street 3",
        'pager'             => "Pager",
        'primary_phone'     => "Primary Phone",
        //'profession'        => "Profession",
        //'radio_phone'       => "Radio Phone",
        'spouse'            => "Spouse",
        'suffix'            => "Suffix",
        'title'             => "Title",
        'web_page'          => "Web Page",

        // Thunderbird
        'birth_day'         => "Birth Day",
        'birth_month'       => "Birth Month",
        'birth_year'        => "Birth Year",
        'display_name'      => "Display Name",
        'fax_number'        => "Fax Number",
        'home_address'      => "Home Address",
        //'home_address_2'    => "Home Address 2",
        'home_country'      => "Home Country",
        'home_zipcode'      => "Home ZipCode",
        'mobile_number'     => "Mobile Number",
        'nickname'          => "Nickname",
        'organization'      => "Organization",
        'pager_number'      => "Pager Namber",
        'primary_email'     => "Primary Email",
        'secondary_email'   => "Secondary Email",
        'web_page_1'        => "Web Page 1",
        'web_page_2'        => "Web Page 2",
        'work_phone'        => "Work Phone",
        'work_address'      => "Work Address",
        //'work_address_2'    => "Work Address 2",
        'work_city'         => "Work City",
        'work_country'      => "Work Country",
        'work_state'        => "Work State",
        'work_zipcode'      => "Work ZipCode",

        // Atmail
        'date_of_birth'     => "Date of Birth",
        'email'             => "Email",
        //'email_2'         => "Email2",
        //'email_3'         => "Email3",
        //'email_4'         => "Email4",
        //'email_5'         => "Email5",
        'home_mobile'       => "Home Mobile",
        'home_zip'          => "Home Zip",
        'info'              => "Info",
        'user_photo'        => "User Photo",
        'url'               => "URL",
        'work_company'      => "Work Company",
        'work_dept'         => "Work Dept",
        'work_fax'          => "Work Fax",
        'work_mobile'       => "Work Mobile",
        'work_title'        => "Work Title",
        'work_zip'          => "Work Zip",
        'group'             => "Group",

        // GMail
        'groups'            => "Groups",
        'group_membership'  => "Group Membership",
        'given_name'        => "Given Name",
        'additional_name'   => "Additional Name",
        'family_name'       => "Family Name",
        'name'              => "Name",
        'name_prefix'       => "Name Prefix",
        'name_suffix'       => "Name Suffix",
    );

    /**
     * Special fields map for GMail format
     *
     * @var array
     */
    protected $gmail_label_map = array(
        'E-mail' => array(
            'Value' => array(
                'home' => 'email:home',
                'work' => 'email:work',
                '*'    => 'email:other',
            ),
        ),
        'Phone' => array(
            'Value' => array(
                'home'    => 'phone:home',
                'homefax' => 'phone:homefax',
                'main'    => 'phone:pref',
                'pager'   => 'phone:pager',
                'mobile'  => 'phone:cell',
                'work'    => 'phone:work',
                'workfax' => 'phone:workfax',
            ),
        ),
        'Relation' => array(
            'Value' => array(
                'spouse' => 'spouse',
            ),
        ),
        'Website' => array(
            'Value' => array(
                'profile'  => 'website:profile',
                'blog'     => 'website:blog',
                'homepage' => 'website:homepage',
                'work'     => 'website:work',
            ),
        ),
        'Address' => array(
            'Street' => array(
                'home' => 'street:home',
                'work' => 'street:work',
            ),
            'City' => array(
                'home' => 'locality:home',
                'work' => 'locality:work',
            ),
            'Region' => array(
                'home' => 'region:home',
                'work' => 'region:work',
            ),
            'Postal Code' => array(
                'home' => 'zipcode:home',
                'work' => 'zipcode:work',
            ),
            'Country' => array(
                'home' => 'country:home',
                'work' => 'country:work',
            ),
        ),
        'Organization' => array(
            'Name' => array(
                '' => 'organization',
            ),
            'Title' => array(
                '' => 'jobtitle',
            ),
            'Department' => array(
                '' => 'department',
            ),
        ),
    );


    protected $local_label_map = array();
    protected $vcards          = array();
    protected $map             = array();
    protected $gmail_map       = array();


    /**
     * Class constructor
     *
     * @param string $lang File language
     */
    public function __construct($lang = 'en_US')
    {
        // Localize fields map
        if ($lang && $lang != 'en_US') {
            if (file_exists(RCUBE_LOCALIZATION_DIR . "$lang/csv2vcard.inc")) {
                include RCUBE_LOCALIZATION_DIR . "$lang/csv2vcard.inc";
            }

            if (!empty($map)) {
                $this->local_label_map = array_merge($this->label_map, $map);
            }
        }

        $this->label_map = array_flip($this->label_map);
        $this->local_label_map = array_flip($this->local_label_map);
    }

    /**
     * Import contacts from CSV file
     *
     * @param string $csv Content of the CSV file
     */
    public function import($csv)
    {
        // convert to UTF-8
        $head      = substr($csv, 0, 4096);
        $charset   = rcube_charset::detect($head, RCUBE_CHARSET);
        $csv       = rcube_charset::convert($csv, $charset);
        $csv       = preg_replace(array('/^[\xFE\xFF]{2}/', '/^\xEF\xBB\xBF/', '/^\x00+/'), '', $csv); // also remove BOM
        $head      = '';
        $prev_line = false;

        $this->map       = array();
        $this->gmail_map = array();

        // Parse file
        foreach (preg_split("/[\r\n]+/", $csv) as $line) {
            if (!empty($prev_line)) {
                $line = '"' . $line;
            }

            $elements = $this->parse_line($line);

            if (empty($elements)) {
                continue;
            }

            // Parse header
            if (empty($this->map)) {
                $this->parse_header($elements);
                if (empty($this->map)) {
                    break;
                }
            }
            // Parse data row
            else {
                // handle multiline elements (e.g. Gmail)
                if (!empty($prev_line)) {
                    $first = array_shift($elements);

                    if ($first[0] == '"') {
                        $prev_line[count($prev_line)-1] = '"' . $prev_line[count($prev_line)-1] . "\n" . substr($first, 1);
                    }
                    else {
                        $prev_line[count($prev_line)-1] .= "\n" . $first;
                    }

                    $elements = array_merge($prev_line, $elements);
                }

                $last_element = $elements[count($elements)-1];
                if ($last_element[0] == '"') {
                    $elements[count($elements)-1] = substr($last_element, 1);
                    $prev_line = $elements;
                    continue;
                }
                $this->csv_to_vcard($elements);
                $prev_line = false;
            }
        }
    }

    /**
     * Export vCards
     *
     * @return array rcube_vcard List of vcards
     */
    public function export()
    {
        return $this->vcards;
    }

    /**
     * Parse CSV file line
     */
    protected function parse_line($line)
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $fields = rcube_utils::explode_quoted_string(',', $line);

        // remove quotes if needed
        if (!empty($fields)) {
            foreach ($fields as $idx => $value) {
                if (($len = strlen($value)) > 1 && $value[0] == '"' && $value[$len-1] == '"') {
                    // remove surrounding quotes
                    $value = substr($value, 1, -1);
                    // replace doubled quotes inside the string with single quote
                    $value = str_replace('""', '"', $value);

                    $fields[$idx] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * Parse CSV header line, detect fields mapping
     */
    protected function parse_header($elements)
    {
        $map1 = array();
        $map2 = array();
        $size = count($elements);

        // check English labels
        for ($i = 0; $i < $size; $i++) {
            $label = $this->label_map[$elements[$i]];
            if ($label && !empty($this->csv2vcard_map[$label])) {
                $map1[$i] = $this->csv2vcard_map[$label];
            }
        }

        // check localized labels
        if (!empty($this->local_label_map)) {
            for ($i = 0; $i < $size; $i++) {
                $label = $this->local_label_map[$elements[$i]];

                // special localization label
                if ($label && $label[0] == '_') {
                    $label = substr($label, 1);
                }

                if ($label && !empty($this->csv2vcard_map[$label])) {
                    $map2[$i] = $this->csv2vcard_map[$label];
                }
            }
        }

        $this->map = count($map1) >= count($map2) ? $map1 : $map2;

        // support special Gmail format
        foreach ($this->gmail_label_map as $key => $items) {
            $num = 1;
            while (($_key = "$key $num - Type") && ($found = array_search($_key, $elements)) !== false) {
                $this->gmail_map["$key:$num"] = array('_key' => $key, '_idx' => $found);
                foreach (array_keys($items) as $item_key) {
                    $_key = "$key $num - $item_key";
                    if (($found = array_search($_key, $elements)) !== false) {
                        $this->gmail_map["$key:$num"][$item_key] = $found;
                    }
                }

                $num++;
            }
        }
    }

    /**
     * Convert CSV data row to vCard
     */
    protected function csv_to_vcard($data)
    {
        $contact = array();
        foreach ($this->map as $idx => $name) {
            $value = $data[$idx];
            if ($value !== null && $value !== '') {
                if (!empty($contact[$name])) {
                    $contact[$name]   = (array) $contact[$name];
                    $contact[$name][] = $value;
                }
                else {
                   $contact[$name] = $value;
                }
            }
        }

        // Gmail format support
        foreach ($this->gmail_map as $idx => $item) {
            $type = preg_replace('/[^a-z]/', '', strtolower($data[$item['_idx']]));
            $key  = $item['_key'];

            unset($item['_idx']);
            unset($item['_key']);

            foreach ($item as $item_key => $item_idx) {
                $value = $data[$item_idx];
                if ($value !== null && $value !== '') {
                    foreach (array($type, '*') as $_type) {
                        if ($data_idx = $this->gmail_label_map[$key][$item_key][$_type]) {
                            $value = explode(' ::: ', $value);

                            if (!empty($contact[$data_idx])) {
                                $contact[$data_idx]   = array_merge((array) $contact[$data_idx], $value);
                            }
                            else {
                                $contact[$data_idx] = $value;
                            }
                            break;
                        }
                    }
                }
            }
        }

        if (empty($contact)) {
            return;
        }

        // Handle special values
        if (!empty($contact['birthday-d']) && !empty($contact['birthday-m']) && !empty($contact['birthday-y'])) {
            $contact['birthday'] = $contact['birthday-y'] .'-' .$contact['birthday-m'] . '-' . $contact['birthday-d'];
        }

        if (!empty($contact['groups'])) {
            // categories/groups separator in vCard is ',' not ';'
            $contact['groups'] = str_replace(',', '', $contact['groups']);
            $contact['groups'] = str_replace(';', ',', $contact['groups']);

            if (!empty($this->gmail_map)) {
                // remove "* " added by GMail
                $contact['groups'] = str_replace('* ', '', $contact['groups']);
                // replace strange delimiter
                $contact['groups'] = str_replace(' ::: ', ',', $contact['groups']);
            }
        }

        // Empty dates, e.g. "0/0/00", "0000-00-00 00:00:00"
        foreach (array('birthday', 'anniversary') as $key) {
            if (!empty($contact[$key])) {
                $date = preg_replace('/[0[:^word:]]/', '', $contact[$key]);
                if (empty($date)) {
                    unset($contact[$key]);
                }
            }
        }

        if (!empty($contact['gender']) && ($gender = strtolower($contact['gender']))) {
            if (!in_array($gender, array('male', 'female'))) {
                unset($contact['gender']);
            }
        }

        // Convert address(es) to rcube_vcard data
        foreach ($contact as $idx => $value) {
            $name = explode(':', $idx);
            if (in_array($name[0], array('street', 'locality', 'region', 'zipcode', 'country'))) {
                $contact['address:'.$name[1]][$name[0]] = $value;
                unset($contact[$idx]);
            }
        }

        // Create vcard object
        $vcard = new rcube_vcard();
        foreach ($contact as $name => $value) {
            $name = explode(':', $name);
            if (is_array($value) && $name[0] != 'address') {
                foreach ((array) $value as $val) {
                    $vcard->set($name[0], $val, $name[1]);
                }
            }
            else {
                $vcard->set($name[0], $value, $name[1]);
            }
        }

        // add to the list
        $this->vcards[] = $vcard;
    }
}
