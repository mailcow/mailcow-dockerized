<?php

/**
 +-----------------------------------------------------------------------+
 | plugins/enigma/localization/<lang>.inc                                |
 |                                                                       |
 | Localization file of the Roundcube Webmail ACL plugin                 |
 | Copyright (C) 2012-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/roundcube/roundcube-webmail/plugin-enigma/
*/

$labels = array();
$labels['encryption'] = 'Encryption';
$labels['enigmacerts'] = 'S/MIME Certificates';
$labels['enigmakeys'] = 'PGP Keys';
$labels['keysfromto'] = 'Keys $from to $to of $count';
$labels['keyname'] = 'Name';
$labels['keyid'] = 'Key ID';
$labels['keyuserid'] = 'User ID';
$labels['keytype'] = 'Key type';
$labels['fingerprint'] = 'Fingerprint';
$labels['subkeys'] = 'Subkeys';
$labels['basicinfo'] = 'Basic Information';
$labels['userids'] = 'Additional Users';
$labels['typepublickey'] = 'public key';
$labels['typekeypair'] = 'key pair';
$labels['keyattfound'] = 'This message contains attached PGP key(s).';
$labels['keyattimport'] = 'Import key(s)';
$labels['typesign'] = 'Sign';
$labels['typeencrypt'] = 'Encrypt';
$labels['typecert'] = 'Certify';
$labels['typeauth'] = 'Authentication';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algorithm';
$labels['subkeycreated'] = 'Created';
$labels['subkeyexpires'] = 'Expires';
$labels['subkeyusage'] = 'Usage';
$labels['expiresnever'] = 'never';
$labels['unknown'] = 'unknown';
$labels['uservalid'] = 'Valid';
$labels['userid'] = 'ID';
$labels['valid'] = 'valid';

$labels['supportencryption'] = 'Enable message encryption and signing';
$labels['supportsignatures'] = 'Enable message signatures verification';
$labels['supportdecryption'] = 'Enable message decryption';
$labels['signdefault'] = 'Sign all messages by default';
$labels['encryptdefault'] = 'Encrypt all messages by default';
$labels['attachpubkeydefault'] = 'Attach my public PGP key by default';
$labels['passwordtime'] = 'Keep private key passwords for';
$labels['nminutes'] = '$m minute(s)';
$labels['wholesession'] = 'the whole session';

$labels['createkeys'] = 'Create a new key pair';
$labels['importkeys'] = 'Import key(s)';
$labels['exportkeys'] = 'Export key(s)';
$labels['keyactions'] = 'Key actions...';
$labels['keyremove'] = 'Remove';
$labels['keydisable'] = 'Disable';
$labels['keyrevoke'] = 'Revoke';
$labels['keysend'] = 'Send public key in a message';
$labels['keychpass'] = 'Change password';

$labels['newkeyident'] = 'Identity:';
$labels['newkeypass'] = 'Password:';
$labels['newkeypassconfirm'] = 'Confirm password:';
$labels['newkeysize'] = 'Key size:';
$labels['key2048'] = '2048 bits - default';
$labels['key4096'] = '4096 bits - more secure';
$labels['keygenerating'] = 'Generating keys...';

$labels['encryptionoptions'] = 'Encryption options...';
$labels['encryptmsg'] = 'Encrypt this message';
$labels['signmsg'] = 'Digitally sign this message';

$labels['enterkeypasstitle'] = 'Enter key passphrase';
$labels['enterkeypass'] = 'A passphrase is needed to unlock the secret key ($keyid) for user: $user.';
$labels['arialabelkeyexportoptions'] = 'Keys export options';
$labels['attachpubkeymsg'] = 'Attach my public key';

$labels['keyexportprompt'] = 'Do you want to include secret keys in the saved OpenPGP keys file?';
$labels['onlypubkeys'] = 'Export Public Keys Only';
$labels['withprivkeys'] = 'Export Secret Keys';
$labels['findkey'] = 'Search on key server(s)';
$labels['keyimportlabel'] = 'Import from file';
$labels['keyimportsearchlabel'] = 'Search on key server(s)';

$messages = array();
$messages['sigvalid'] = 'Verified signature from $sender.';
$messages['sigvalidpartial'] = 'Verified signature from $sender, but part of the body was not signed.';
$messages['siginvalid'] = 'Invalid signature from $sender.';
$messages['sigunverified'] = 'Unverified signature. Certificate not verified. Certificate ID: $keyid.';
$messages['signokey'] = 'Unverified signature. Public key not found. Key ID: $keyid.';
$messages['sigerror'] = 'Unverified signature. Internal error.';
$messages['decryptok'] = 'Message decrypted.';
$messages['decrypterror'] = 'Decryption failed.';
$messages['decryptnokey'] = 'Decryption failed. Private key not found. Key ID: $keyid.';
$messages['decryptbadpass'] = 'Decryption failed. Invalid password.';
$messages['decryptnopass'] = 'Decryption failed. Key password required.';
$messages['decryptpartial'] = 'Message decrypted, but part of the body was not encrypted.';
$messages['signerror'] = 'Signing failed.';
$messages['signnokey'] = 'Signing failed. Private key not found.';
$messages['signbadpass'] = 'Signing failed. Invalid password.';
$messages['signnopass'] = 'Signing failed. Key password required.';
$messages['encrypterror'] = 'Encryption failed.';
$messages['encryptnokey'] = 'Encryption failed. Public key not found for $email.';
$messages['nokeysfound'] = 'No keys found';
$messages['keynotfound'] = 'Key not found!';
$messages['keyopenerror'] = 'Unable to get key information! Internal error.';
$messages['keylisterror'] = 'Unable to list keys! Internal error.';
$messages['keysimportfailed'] = 'Unable to import key(s)! Internal error.';
$messages['keysimportsuccess'] = 'Key(s) imported successfully. Imported: $new, unchanged: $old.';
$messages['keyremoving'] = 'Removing key(s)...';
$messages['keyremoveconfirm'] = 'Are you sure, you want to delete selected key(s)?';
$messages['keyremovesuccess'] = 'Key(s) deleted successfully';
$messages['keyremoveerror'] = 'Unable to delete selected key(s).';
$messages['keyimporttext'] = 'You can import private and public key(s) or revocation signatures in ASCII-Armor format.';
$messages['keyimportsearchtext'] = 'You can search for public keys by key identifier, user name or email address and then import them directly.';

$messages['formerror'] = 'Please, fill the form. All fields are required!';
$messages['passwordsdiffer'] = 'Passwords do not match!';
$messages['keygenerateerror'] = 'Failed to generate a key pair';
$messages['keygeneratesuccess'] = 'A key pair generated and imported successfully.';
$messages['keygennosupport'] = 'Your web browser does not support cryptography. Unable to generate a key pair!';
$messages['noidentselected'] = 'You have to select at least one identity for the key!';

// removed in 1.3
$messages['nonameident'] = 'Identity must have a user name defined!';

?>
