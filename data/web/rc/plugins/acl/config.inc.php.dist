<?php

// Default look of access rights table
// In advanced mode all access rights are displayed separately
// In simple mode access rights are grouped into four groups: read, write, delete, full 
$config['acl_advanced_mode'] = false;

// LDAP addressbook that would be searched for user names autocomplete.
// That should be an array refering to the $config['ldap_public'] array key
// or complete addressbook configuration array.
$config['acl_users_source'] = '';

// The LDAP attribute which will be used as ACL user identifier
$config['acl_users_field'] = 'mail';

// The LDAP search filter will be &'d with search queries
$config['acl_users_filter'] = '';

// Enable LDAP groups in user autocompletion.
// Note: LDAP addressbook defined in acl_users_source must include groups config
$config['acl_groups'] = false;

// Prefix added to the group name to build IMAP ACL identifier
$config['acl_group_prefix'] = 'group:';

// The LDAP attribute (or field name) which will be used as ACL group identifier
$config['acl_group_field'] = 'name';

// Include the following 'special' access control subjects in the ACL dialog;
// Defaults to array('anyone', 'anonymous') (not when set to an empty array)
// Example: array('anyone') to exclude 'anonymous'.
// Set to an empty array to exclude all special aci subjects.
$config['acl_specials'] = array('anyone', 'anonymous');
