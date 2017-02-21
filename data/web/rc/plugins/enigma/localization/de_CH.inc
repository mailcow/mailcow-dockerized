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
$labels['encryption'] = 'Verschlüsselung';
$labels['enigmacerts'] = 'S/MIME Zertifikate';
$labels['enigmakeys'] = 'PGP Schlüssel';
$labels['keysfromto'] = 'Schlüssel $from bis $to von $count';
$labels['keyname'] = 'Schlüssel';
$labels['keyid'] = 'Schlüssel ID';
$labels['keyuserid'] = 'Benutzer ID';
$labels['keytype'] = 'Schlüsseltyp';
$labels['fingerprint'] = 'Fingerprint';
$labels['subkeys'] = 'Sub-Schlüssel';
$labels['basicinfo'] = 'Allgemeine Informationen';
$labels['userids'] = 'Zusätzliche Benutzer';
$labels['typepublickey'] = 'Öffentlicher Schlüssel';
$labels['typekeypair'] = 'Schlüsselpaar';
$labels['keyattfound'] = 'Diese Nachricht enthält angehängte PGP Schlüssel.';
$labels['keyattimport'] = 'Schlüssel importieren';
$labels['typesign'] = 'Signieren';
$labels['typeencrypt'] = 'Verschlüsseln';
$labels['typecert'] = 'Bestätigen';
$labels['typeauth'] = 'Authentifizierung';
$labels['subkeyid'] = 'Sub-Schlüssel ID';
$labels['subkeyalgo'] = 'Algorithmus';
$labels['subkeycreated'] = 'Erstellt am';
$labels['subkeyexpires'] = 'Läuft aus';
$labels['subkeyusage'] = 'Verwendet für';
$labels['expiresnever'] = 'nie';
$labels['unknown'] = 'unbekannt';
$labels['uservalid'] = 'Gültigkeit';
$labels['userid'] = 'ID';
$labels['valid'] = 'gültig';
$labels['supportencryption'] = 'Nachrichtenverschlüsselung und Signierung aktivieren';
$labels['supportsignatures'] = 'Verifizierung der Nachrichtensignatur aktivieren';
$labels['supportdecryption'] = 'Entschlüsselung von Nachrichten aktivieren';
$labels['signdefault'] = 'Nachrichten immer signieren';
$labels['encryptdefault'] = 'Nachrichten immer verschlüsseln';
$labels['attachpubkeydefault'] = 'Öffentlichen Schlüssel in der Nachricht anfügen';
$labels['passwordtime'] = 'Private Schlüssel behalten für';
$labels['nminutes'] = '$m Minuten';
$labels['wholesession'] = 'die gesamte Sitzung';
$labels['createkeys'] = 'Neues Schlüsselpaar erstellen';
$labels['importkeys'] = 'Schlüssel importieren';
$labels['exportkeys'] = 'Schlüssel exportieren';
$labels['keyactions'] = 'Aktionen...';
$labels['keyremove'] = 'Entfernen';
$labels['keydisable'] = 'Deaktivieren';
$labels['keyrevoke'] = 'Widerrufen';
$labels['keysend'] = 'Öffentlichen Schlüssel versenden';
$labels['keychpass'] = 'Passwort ändern';
$labels['newkeyident'] = 'Identität:';
$labels['newkeypass'] = 'Passwort:';
$labels['newkeypassconfirm'] = 'Passwort bestätigen:';
$labels['newkeysize'] = 'Schlüssellänge:';
$labels['key2048'] = '2048 bits - standard';
$labels['key4096'] = '4096 bits - sicher';
$labels['keygenerating'] = 'Schlüssel generieren...';
$labels['encryptionoptions'] = 'Verschlüsselungsoptionen';
$labels['encryptmsg'] = 'Diese Nachricht verschlüsseln';
$labels['signmsg'] = 'Diese Nachricht digital signieren';
$labels['enterkeypasstitle'] = 'Bitte Schlüssel Passwort eingeben';
$labels['enterkeypass'] = 'Ein Passwort wird benötigt um den privaten Schlüssel ($keyid) für Benutzer: $user zu entsperren';
$labels['arialabelkeyexportoptions'] = 'Optionen für Schlüsselexport';
$labels['attachpubkeymsg'] = 'Meinen öffentlichen Schlüssel anfügen';
$messages['sigvalid'] = 'Signatur von $sender wurde erfolgreich überprüft.';
$messages['sigvalidpartial'] = 'Signatur von $sender wurde überprüft, aber ein Teil der Nachricht wurde nicht signiert.';
$messages['siginvalid'] = 'Ungültige Signatur von $sender.';
$messages['sigunverified'] = 'Unbestätigte Signatur. Zertifikat nicht überprüft. Zertifikat-ID: $keyid. ';
$messages['signokey'] = 'Unbestätigte Signatur. Öffentlicher Schlüssel wurde nicht gefunden. Schlüssel-ID: $keyid.';
$messages['sigerror'] = 'Unbestätigte Signatur. Interner Fehler.';
$messages['decryptok'] = 'Nachricht entschlüsselt.';
$messages['decrypterror'] = 'Entschlüsselung fehlgeschlagen.';
$messages['decryptnokey'] = 'Entschlüsselung fehlgeschlagen. Privater Schlüssel wurde nicht gefunden. Schlüssel-ID: $keyid.';
$messages['decryptnopass'] = 'Entschlüsselung fehlgeschlagen. Schlüsselpasswort wird benötigt.';
$messages['decryptpartial'] = 'Nachricht entschlüsselt, aber ein Teil der Nachricht wurde nicht verschlüsselt.';
$messages['signerror'] = 'Signierung fehlgeschlagen.';
$messages['signnokey'] = 'Signierung fehlgeschlagen. Privater Schlüssel wurde nicht gefunden.';
$messages['signnopass'] = 'Signierung fehlgeschlagen. Schlüsselpasswort wird benötigt.';
$messages['encrypterror'] = 'Verschlüsselung fehlgeschlagen.';
$messages['encryptnokey'] = 'Verschlüsselung fehlgeschlagen. Öffentlicehr Schlüssel für $email nicht gefunden';
$messages['nokeysfound'] = 'Keine Schlüssel gefunden';
$messages['keynotfound'] = 'Schlüssel nicht gefunden!';
$messages['keyopenerror'] = 'Abrufen der Schlüsselinformationen nicht möglich. Interner Fehler.';
$messages['keylisterror'] = 'Auflisten der Schlüsselinformationen nicht möglich! Interner Fehler.';
$messages['keysimportfailed'] = 'Schlüsselimport fehlgeschlagen! Interner Fehler.';
$messages['keysimportsuccess'] = 'Schlüsselimport erfolgreich. Importiert: $new, unverändert: $old.';
$messages['keyremoving'] = 'Schlüssel entfernen...';
$messages['keyremoveconfirm'] = 'Bist du dir sicher, dass du die ausgewählten Schlüssel entfernen willst?';
$messages['keyremovesuccess'] = 'Schlüssel erfolgreich gelöscht.';
$messages['keyremoveerror'] = 'Löschung der Schlüssel nicht möglich.';
$messages['keyimporttext'] = 'Private und öffentliche Schlüssel sowie zurückgerufene Signaturen können im ASCII-Armor Format importiert werden.';
$messages['formerror'] = 'Bitte, alle Eingabefelder ausfüllen. Alle Eingabefelder werden benötigt!';
$messages['passwordsdiffer'] = 'Passwörter stimmen nicht überein.';
$messages['keygenerateerror'] = 'Schlüsselgenerierung fehlgeschlagen.';
$messages['keygeneratesuccess'] = 'Schlüsselpaar erfolgreich generiert und importiert.';
$messages['keygennosupport'] = 'Dein Browser unterstützt keine Kryptopgraphiefunktionen. Konnte Schlüsselpaar nicht generieren!';
$messages['nonameident'] = 'Identität muss einen Benutzernamen definiert haben.';
?>
