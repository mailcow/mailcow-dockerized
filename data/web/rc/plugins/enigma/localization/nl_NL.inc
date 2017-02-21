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
$labels['encryption'] = 'Versleuteling';
$labels['enigmacerts'] = 'S/MIME-certificaten';
$labels['enigmakeys'] = 'PGP-sleutels';
$labels['keysfromto'] = 'Sleutels $from t/m $to van $count';
$labels['keyname'] = 'Naam';
$labels['keyid'] = 'Sleutel-ID';
$labels['keyuserid'] = 'Gebruiker-ID';
$labels['keytype'] = 'Sleuteltype';
$labels['fingerprint'] = 'Vingerafdruk';
$labels['subkeys'] = 'Subsleutels';
$labels['basicinfo'] = 'Algemene informatie';
$labels['userids'] = 'Extra gebruikers';
$labels['typepublickey'] = 'publieke sleutel';
$labels['typekeypair'] = 'sleutelpaar';
$labels['keyattfound'] = 'Dit bericht bevat bijgevoegde PGP-sleutel(s).';
$labels['keyattimport'] = 'Importeer sleutel(s)';
$labels['typesign'] = 'Onderteken';
$labels['typeencrypt'] = 'Versleutel';
$labels['typecert'] = 'Certificeren';
$labels['typeauth'] = 'Authenticatie';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritme';
$labels['subkeycreated'] = 'Aangemaakt';
$labels['subkeyexpires'] = 'Verloopt';
$labels['subkeyusage'] = 'Gebruik';
$labels['expiresnever'] = 'nooit';
$labels['unknown'] = 'onbekend';
$labels['uservalid'] = 'Geldig';
$labels['userid'] = 'ID';
$labels['valid'] = 'geldig';
$labels['supportencryption'] = 'Berichtversleuteling en -ondertekening inschakelen';
$labels['supportsignatures'] = 'Verificatie van berichtondertekening inschakelen';
$labels['supportdecryption'] = 'Berichtontsleuteling inschakelen';
$labels['signdefault'] = 'Onderteken standaard alle berichten';
$labels['encryptdefault'] = 'Versleutel standaard alle berichten';
$labels['attachpubkeydefault'] = 'Voeg mijn publieke PGP-sleutel standaard bij';
$labels['passwordtime'] = 'Onthoud geheime sleutelwachtwoorden voor';
$labels['nminutes'] = '$m minuten';
$labels['wholesession'] = 'de hele sessie';
$labels['createkeys'] = 'Maak een nieuw sleutelpaar aan';
$labels['importkeys'] = 'Importeer sleutel(s)';
$labels['exportkeys'] = 'Exporteer sleutel(s)';
$labels['keyactions'] = 'Sleutelacties...';
$labels['keyremove'] = 'Verwijderen';
$labels['keydisable'] = 'Uitschakelen';
$labels['keyrevoke'] = 'Intrekken';
$labels['keysend'] = 'Verstuur publieke sleutel in een bericht';
$labels['keychpass'] = 'Wijzig wachtwoord';
$labels['newkeyident'] = 'Identiteit:';
$labels['newkeypass'] = 'Wachtwoord:';
$labels['newkeypassconfirm'] = 'Bevestig wachtwoord:';
$labels['newkeysize'] = 'Sleutelgrootte:';
$labels['key2048'] = '2048 bits - standaard';
$labels['key4096'] = '4096 bits - veiliger';
$labels['keygenerating'] = 'Sleutels genereren...';
$labels['encryptionoptions'] = 'Versleutelingsopties...';
$labels['encryptmsg'] = 'Versleutel dit bericht';
$labels['signmsg'] = 'Onderteken dit bericht digitaal';
$labels['enterkeypasstitle'] = 'Voer sleutelwachtwoord in';
$labels['enterkeypass'] = 'Een wachtwoord is vereist om de geheime sleutel ($keyid) te openen voor gebruiker: $user.';
$labels['arialabelkeyexportoptions'] = 'Opties voor sleutels exporteren';
$labels['attachpubkeymsg'] = 'Voeg mijn publieke sleutel bij';
$labels['keyexportprompt'] = 'Wilt u geheime sleutels bewaren in het opgeslagen OpenPGP-sleutelbestand?';
$labels['onlypubkeys'] = 'Alleen publieke sleutels exporteren';
$labels['withprivkeys'] = 'Geheime sleutels exporteren';
$labels['findkey'] = 'Zoek op keyserver(s)';
$labels['keyimportlabel'] = 'Importeer van bestand';
$labels['keyimportsearchlabel'] = 'Zoek op keyserver(s)';
$messages['sigvalid'] = 'Ondertekening van $sender geverifieerd.';
$messages['sigvalidpartial'] = 'Ondertekening van $sender geverifieerd, maar een deel van de inhoud was niet ondertekend.';
$messages['siginvalid'] = 'Ongeldige ondertekening van $sender.';
$messages['sigunverified'] = 'Niet-geverifieerde ondertekening. Certificaat niet geverifieerd. Certificaat-ID: $keyid.';
$messages['signokey'] = 'Niet-geverifieerde ondertekening. Publieke sleutel niet gevonden. Sleutel-ID: $keyid.';
$messages['sigerror'] = 'Niet-geverifieerde ondertekening. Interne fout.';
$messages['decryptok'] = 'Bericht ontsleuteld.';
$messages['decrypterror'] = 'Ontsleutelen mislukt.';
$messages['decryptnokey'] = 'Ontsleutelen mislukt. Geheime sleutel niet gevonden. Sleutel-ID: $keyid.';
$messages['decryptnopass'] = 'Ontsleutelen mislukt. Sleutelwachtwoord vereist.';
$messages['decryptpartial'] = 'Bericht ontsleuteld, maar een deel van de inhoud was niet versleuteld.';
$messages['signerror'] = 'Ondertekenen mislukt.';
$messages['signnokey'] = 'Ondertekenen mislukt. Geheime sleutel niet gevonden.';
$messages['signnopass'] = 'Ondertekenen mislukt. Sleutelwachtwoord vereist.';
$messages['encrypterror'] = 'Versleutelen mislukt.';
$messages['encryptnokey'] = 'Onsleutelen mislukt. Publieke sleutel niet gevonden voor $email.';
$messages['nokeysfound'] = 'Geen sleutels gevonden';
$messages['keynotfound'] = 'Sleutel niet gevonden';
$messages['keyopenerror'] = 'Sleutelinformatie kan niet worden opgehaald! Interne fout.';
$messages['keylisterror'] = 'Sleutels kunnen niet worden weergegeven! Interne fout.';
$messages['keysimportfailed'] = 'Sleutel(s) kunnen niet worden geïmporteerd! Interne fout.';
$messages['keysimportsuccess'] = 'Sleutel(s) succesvol geïmporteerd. Geïmporteerd: $new, ongewijzigd: $old.';
$messages['keyremoving'] = 'Sleutel(s) verwijderen...';
$messages['keyremoveconfirm'] = 'Weet u zeker dat u de geselecteerde sleutel(s) wilt verwijderen?';
$messages['keyremovesuccess'] = 'Sleutel(s) succesvol verwijderd';
$messages['keyremoveerror'] = 'Geselecteerde sleutel(s) kunnen niet worden verwijderd.';
$messages['keyimporttext'] = 'U kunt geheime en publieke sleutel(s) of intrek-ondertekeningen importeren in ASCII-Armor formaat.';
$messages['keyimportsearchtext'] = 'U kunt naar publieke sleutels zoeken op basis van sleutel identifier, gebruikersnaam of e-mailadres en ze vervolgens direct importeren.';
$messages['formerror'] = 'Vul alstublieft het formulier in. Alle velden zijn vereist!';
$messages['passwordsdiffer'] = 'Wachtwoorden komen niet overeen!';
$messages['keygenerateerror'] = 'Genereren van een sleutelpaar is mislukt';
$messages['keygeneratesuccess'] = 'Een sleutelpaar is succesvol gegenereerd en geïmporteerd.';
$messages['keygennosupport'] = 'Uw webbrowser ondersteunt geen cryptografie. Sleutelpaar genereren is mislukt!';
$messages['nonameident'] = 'Identiteit moet een gebruikersnaam hebben!';
?>
