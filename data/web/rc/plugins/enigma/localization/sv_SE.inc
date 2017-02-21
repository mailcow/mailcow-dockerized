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
$labels['encryption'] = 'Kryptering';
$labels['enigmacerts'] = 'S/MIME-certifikat';
$labels['enigmakeys'] = 'PGP-nycklar';
$labels['keysfromto'] = 'Nyckel $from till $to av $count';
$labels['keyname'] = 'Namn';
$labels['keyid'] = 'Nyckel-ID';
$labels['keyuserid'] = 'Användar-ID';
$labels['keytype'] = 'Nyckeltyp';
$labels['fingerprint'] = 'Fingeravtryck';
$labels['subkeys'] = 'Undernycklar';
$labels['basicinfo'] = 'Grundläggande information';
$labels['userids'] = 'Ytterligare användare';
$labels['typepublickey'] = 'offentlig nyckel';
$labels['typekeypair'] = 'nyckelpar';
$labels['keyattfound'] = 'Meddelandet innehåller en eller flera bifogade PGP-nycklar.';
$labels['keyattimport'] = 'Importera nyckel';
$labels['typesign'] = 'Signera';
$labels['typeencrypt'] = 'Kryptera';
$labels['typecert'] = 'Intyga';
$labels['typeauth'] = 'Autentisering';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritm';
$labels['subkeycreated'] = 'Skapad';
$labels['subkeyexpires'] = 'Utgår';
$labels['subkeyusage'] = 'Användning';
$labels['expiresnever'] = 'aldrig';
$labels['unknown'] = 'okänd';
$labels['uservalid'] = 'Giltig';
$labels['userid'] = 'ID';
$labels['valid'] = 'giltig';
$labels['supportencryption'] = 'Aktivera kryptering och signering av meddelanden';
$labels['supportsignatures'] = 'Aktivera signaturverifiering i meddelanden';
$labels['supportdecryption'] = 'Aktivera dekryptering av meddelanden';
$labels['signdefault'] = 'Signera alla meddelanden';
$labels['encryptdefault'] = 'Kryptera alla meddelanden';
$labels['attachpubkeydefault'] = 'Bifoga alltid min offentliga nyckel';
$labels['passwordtime'] = 'Använd lösenord till privat nyckel under';
$labels['nminutes'] = '$m min';
$labels['wholesession'] = 'Hela sessionen';
$labels['createkeys'] = 'Nytt nyckelpar ';
$labels['importkeys'] = 'Importera nycklar';
$labels['exportkeys'] = 'Exportera nycklar';
$labels['keyactions'] = 'Hantera nycklar...';
$labels['keyremove'] = 'Ta bort';
$labels['keydisable'] = 'Deaktivera';
$labels['keyrevoke'] = 'Upphäv';
$labels['keysend'] = 'Skicka offentlig nyckel i meddelande';
$labels['keychpass'] = 'Ändra lösenord';
$labels['newkeyident'] = 'Identitet';
$labels['newkeypass'] = 'Lösenord';
$labels['newkeypassconfirm'] = 'Bekräfta lösenord';
$labels['newkeysize'] = 'Nyckelstorlek';
$labels['key2048'] = '2048 bitar - standard';
$labels['key4096'] = '4096 bitar - säkrare';
$labels['keygenerating'] = 'Skapar nycklar...';
$labels['encryptionoptions'] = 'Krypteringsalternativ...';
$labels['encryptmsg'] = 'Kryptera meddelandet';
$labels['signmsg'] = 'Signera meddelandet';
$labels['enterkeypasstitle'] = 'Ange nyckelns lösenord';
$labels['enterkeypass'] = 'Lösenord behövs för att låsa upp den privata nyckeln ($keyid) för användare $user.';
$labels['arialabelkeyexportoptions'] = 'Exportalternativ för nycklar';
$labels['attachpubkeymsg'] = 'Bifoga min offentliga nyckel';
$labels['keyexportprompt'] = 'Inkludera privata nycklar i den sparade nyckelfilen för OpenPGP?';
$labels['onlypubkeys'] = 'Exportera endast offentliga nycklar';
$labels['withprivkeys'] = 'Exportera privata nycklar';
$labels['findkey'] = 'Sök nyckel på en eller flera servrar';
$labels['keyimportlabel'] = 'Importera från fil';
$labels['keyimportsearchlabel'] = 'Sök nyckel på en eller flera servrar';
$messages['sigvalid'] = 'Signatur verifierad för $sender.';
$messages['sigvalidpartial'] = 'Signatur verifierad för $sender, men meddelandet var delvis osignerat.';
$messages['siginvalid'] = 'Ogiltig signatur från $sender.';
$messages['sigunverified'] = 'Overifierad signatur. Certifikatet är inte verifierat. Certifikat-ID: $keyid.';
$messages['signokey'] = 'Overifierad signatur. Offentlig nyckel otillgänglig. Nyckel-ID: $keyid.';
$messages['sigerror'] = 'Overifierad signatur. Internt fel.';
$messages['decryptok'] = 'Meddelandet är dekrypterat.';
$messages['decrypterror'] = 'Dekrypteringen misslyckades.';
$messages['decryptnokey'] = 'Dekrypteringen misslyckades. Privat nyckel otillgänglig. Nyckel-ID: $keyid.';
$messages['decryptnopass'] = 'Dekrypteringen misslyckades. Ange nyckelns lösenord.';
$messages['decryptpartial'] = 'Meddelandet är dekrypterat, men meddelandet var delvis okrypterat.';
$messages['signerror'] = 'Signeringen misslyckades.';
$messages['signnokey'] = 'Signeringen misslyckades. Privat nyckel otillgänglig.';
$messages['signnopass'] = 'Signeringen misslyckades. Ange nyckelns lösenord.';
$messages['encrypterror'] = 'Krypteringen misslyckades.';
$messages['encryptnokey'] = 'Krypteringen misslyckades. Offentlig nyckel otillgänglig för $email.';
$messages['nokeysfound'] = 'Inga nycklar hittades.';
$messages['keynotfound'] = 'Nyckeln hittades inte!';
$messages['keyopenerror'] = 'Nyckelinformation kunde inte hämtas. Internt fel.';
$messages['keylisterror'] = 'Nycklar kunde inte listas. Internt fel.';
$messages['keysimportfailed'] = 'Nycklar kunde inte importeras. Internt fel.';
$messages['keysimportsuccess'] = 'Nycklar har importerats. Importerade: $new, oförändrade: $old.';
$messages['keyremoving'] = 'Tar bort nycklar...';
$messages['keyremoveconfirm'] = 'Vill du ta bort markerade nycklar?';
$messages['keyremovesuccess'] = 'Nycklar borttagna.';
$messages['keyremoveerror'] = 'Nycklarna kunde inte tas bort.';
$messages['keyimporttext'] = 'Privata och offentliga nycklar eller återkallande av signaturer kan importeras i formatet ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Offentlig nyckel kan sökas genom att ange identifierare, användarnamn eller mailadress, för att sedan importeras direkt.';
$messages['formerror'] = 'Fyll i formulärets samtliga fält.';
$messages['passwordsdiffer'] = 'Bekräftelsen av lösenordet stämmer inte.';
$messages['keygenerateerror'] = 'Nyckelpar kunde inte skapas';
$messages['keygeneratesuccess'] = 'Nyckelpar skapat och importerat.';
$messages['keygennosupport'] = 'Webbläsaren saknar krypteringsfunktion. Nyckelpar kunde inte skapas.';
$messages['noidentselected'] = 'Minst en identitet till nyckeln måste väljas!';
$messages['nonameident'] = 'Användarnamn måste anges.';
?>
