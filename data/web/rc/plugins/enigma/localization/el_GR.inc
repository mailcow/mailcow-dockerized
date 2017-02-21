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
$labels['encryption'] = 'Κρυπτογράφηση';
$labels['enigmacerts'] = 'Πιστοποιητικά S/MIME';
$labels['enigmakeys'] = 'PGP Κλειδιά';
$labels['keysfromto'] = 'Κλειδιά $from έως $to από $count';
$labels['keyname'] = 'Όνομα';
$labels['keyid'] = 'Ταυτότητα κλειδιού';
$labels['keyuserid'] = 'Ταυτότητα Χρήστη';
$labels['keytype'] = 'Τύπος κλειδιού';
$labels['fingerprint'] = 'Δακτυλικό αποτύπωμα';
$labels['subkeys'] = 'Υποκλειδιά';
$labels['basicinfo'] = 'Βασικές πληροφορίες';
$labels['userids'] = 'Επιπλέον Χρήστες';
$labels['typepublickey'] = 'δημόσιο κλειδί';
$labels['typekeypair'] = 'ζεύγος κλειδιού';
$labels['keyattfound'] = 'Αυτό το μήνυμα περιέχει συνημμένο(α) κλειδί(ά) PGP.';
$labels['keyattimport'] = 'Εισαγωγή κλειδιού(ών)';
$labels['typesign'] = 'Υπογραφή';
$labels['typeencrypt'] = 'Κρυπτογράφηση';
$labels['typecert'] = 'Πιστοποίηση';
$labels['typeauth'] = 'Επαλήθευση';
$labels['subkeyid'] = 'Ταυτότητα';
$labels['subkeyalgo'] = 'Αλγόριθμος';
$labels['subkeycreated'] = 'Δημιουργήθηκε';
$labels['subkeyexpires'] = 'Λήγει';
$labels['subkeyusage'] = 'Χρήση';
$labels['expiresnever'] = 'ποτέ';
$labels['unknown'] = 'άγνωστο';
$labels['uservalid'] = 'Έγκυρο';
$labels['userid'] = 'Ταυτότητα';
$labels['valid'] = 'έγκυρο';
$labels['supportencryption'] = 'Ενεργοποιήστε την κρυπτογράφηση και υπογραφή μηνυμάτων';
$labels['supportsignatures'] = 'Ενεργοποίηση επαλήθευσης υπογραφών μηνύματος';
$labels['supportdecryption'] = 'Ενεργοποιήστε την κρυπτογράφηση μηνυμάτων';
$labels['signdefault'] = 'Υπογραφή όλων των μηνυμάτων από προεπιλογή';
$labels['encryptdefault'] = 'Κρυπτογράφηση όλων των μηνυμάτων από προεπιλογή';
$labels['attachpubkeydefault'] = 'Επισύναψη του δημόσιου PGP κλειδιού μου από προεπιλογή';
$labels['passwordtime'] = 'Διατήρηση των κωδικών του ιδιωτικού κλειδιού για';
$labels['nminutes'] = '$m λεπτό(ά)';
$labels['wholesession'] = 'ολόκληρη τη συνεδρία';
$labels['createkeys'] = 'Δημιουργία νέου ζεύγους κλειδιού';
$labels['importkeys'] = 'Εισαγωγή κλειδιού(ών)';
$labels['exportkeys'] = 'Εξαγωγή κλειδιού(ών)';
$labels['keyactions'] = 'Ενέργειες κλειδιού...';
$labels['keyremove'] = 'Διαγραφή';
$labels['keydisable'] = 'Απενεργοποίηση';
$labels['keyrevoke'] = 'Απόσυρση';
$labels['keysend'] = 'Αποστολή δημοσίου κλειδιού στο μήνυμα';
$labels['keychpass'] = 'Αλλαγή κωδικού';
$labels['newkeyident'] = 'Αναγνωριστικό:';
$labels['newkeypass'] = 'Κωδικός:';
$labels['newkeypassconfirm'] = 'Επιβεβαίωση κωδικού:';
$labels['newkeysize'] = 'Μέγεθος κλειδιού:';
$labels['key2048'] = '2048 bits - προκαθορισμένο';
$labels['key4096'] = '4096 bits - ασφαλέστερο';
$labels['keygenerating'] = 'Δημιουργία κλειδιών...';
$labels['encryptionoptions'] = 'Επιλογές κρυπτογράφησης...';
$labels['encryptmsg'] = 'Κρυπτογράφηση του μηνύματος';
$labels['signmsg'] = 'Ψηφιακή υπογραφή του μηνύματος';
$labels['enterkeypasstitle'] = 'Εισάσετε τον κωδικό του κλειδιού';
$labels['enterkeypass'] = 'Απαιτείται ένας κωδικός για το ξεκλειδωμα του κρυφού κλειδιού ($keyid) του χρήστη: $user.';
$labels['arialabelkeyexportoptions'] = 'Επιλογές εξαγωγής κλειδιών';
$labels['attachpubkeymsg'] = 'Επισύναψη του δημόσιου κλειδιού μου';
$labels['keyexportprompt'] = 'Θέλετε να συμπεριληφθούν τα μυστικά κλειδικά στο αποθηκευμένο αρχείο κλειδιών OpenPGP;';
$labels['onlypubkeys'] = 'Εξαγωγή Δημοσίων Κλειδιών Μόνο';
$labels['withprivkeys'] = 'Εξαγωγή Μυστικών Κλειδιών';
$labels['findkey'] = 'Αναζήτηση στον(ους) εξυπηρετητή(ές) κλειδιών';
$messages['sigvalid'] = 'Επαληθευμένη υπογραφή από τον  $sender.';
$messages['sigvalidpartial'] = 'Επαληθευμένη υπογραφή από τον $sender, αλλά τμήμα του κειμένου δεν έχει υπογραφεί.';
$messages['siginvalid'] = 'Άκυρη υπογραφή από τον $sender.';
$messages['sigunverified'] = 'Ανεπαλήθευτη υπογραφή. Το πιστοποιητικό δεν έχει επαληθευτεί. Ταυτότητα πιστοποιητικού: $keyid.';
$messages['signokey'] = 'Ανεπαλήθευτη υπογραφή. Το δημόσιο κλειδί δε βρέθηκε. Ταυτότητα κλειδιού: $keyid.';
$messages['sigerror'] = 'Ανεπαλήθευτη υπογραφή. Εσωτερικό σφάλμα.';
$messages['decryptok'] = 'Το μήνυμα αποκρυπτογραφήθηκε.';
$messages['decrypterror'] = 'Η αποκρυπτογράφηση απέτυχε.';
$messages['decryptnokey'] = 'Η αποκρυπτογράφηση απέτυχε. Το ιδιωτικό κλειδί δε βρέθηκε. Ταυτότητα κλειδιού: $keyid.';
$messages['decryptnopass'] = 'Η αποκρυπτογράφηση απέτυχε. Απαιτείται ο κωδικός του κλειδιού.';
$messages['decryptpartial'] = 'Το μήνυμα αποκρυπτογραφήθηκε, αλλά τμήμα του μηνύματος δεν ήταν κρυπτογραφημένο.';
$messages['signerror'] = 'Η υπογραφή απέτυχε.';
$messages['signnokey'] = 'Η υπογραφή απέτυχε. Το ιδιωτικό κλειδί δε βρέθηκε.';
$messages['signnopass'] = 'Η υπογραφή απέτυχε. Απαιτείται ο κωδικός του κλειδιού.';
$messages['encrypterror'] = 'Η κρυπτογράφηση απέτυχε.';
$messages['encryptnokey'] = 'Η κρυπτογράφηση απέτυχε. Το δημόσιο κλειδί του $email δε βρέθηκε.
 ';
$messages['nokeysfound'] = 'Δε βρέθηκαν κλειδιά';
$messages['keynotfound'] = 'Το κλειδί δε βρέθηκε!';
$messages['keyopenerror'] = 'Αδυναμία ανάκτησης των πληροφοριών του κλειδιού! Εσωτερικό σφάλμα.';
$messages['keylisterror'] = 'Αδυναμία ανάκτησης λίστας κλειδιού! Εσωτερικό σφάλμα.';
$messages['keysimportfailed'] = 'Αδυναμία εισαγωγής του κλειδιού(ών)! Εσωτερικό σφάλμα.';
$messages['keysimportsuccess'] = 'Το κλειδί(ά) εισήχθη επιτυχώς. Εισήχθη: $new, αναλλοίωτο: $old.';
$messages['keyremoving'] = 'Διαφραφή κλειδιού(ών)...';
$messages['keyremoveconfirm'] = 'Θέλετε σίγουρα να διαγράψετε το επιλεγμένο κλειδί(ά);';
$messages['keyremovesuccess'] = 'Το κλειδί(ά) διαγράφηκε επιτυχώς.';
$messages['keyremoveerror'] = 'Αδυναμία διαγραφής του επιλεγμένου κλειδιού(ών).';
$messages['keyimporttext'] = 'Μπορείτε να εισάγετε ιδιωτικά ή δημόσια κλειδιά ή να ανακτήσετε υπογραφές σε μοργή ASCII-Armor.';
$messages['formerror'] = 'Παρακαλώ συμπληρώστε όλα τα πεδία της φόρμας!';
$messages['passwordsdiffer'] = 'Οι κωδικοί δεν ταιριάζουν μεταξύ τους!';
$messages['keygenerateerror'] = 'Αποτυχία δημιουργίας ζεύγους κλειδιών.';
$messages['keygeneratesuccess'] = 'Το ζεύγος κλειδιών δημιουργήθηκε και εισήχθη επιτυχώς.';
$messages['keygennosupport'] = 'To πρόγραμμα περιήγησής σας δεν υποστηρίζει κρυπτογράφηση. Δεν ήταν δυνατή η δημιουργία ενός ζεύγος κλειδιών!';
$messages['nonameident'] = 'Η Ταυτότητα πρέπει να έχει ορισμένο όνομα χρήστη!';
?>
