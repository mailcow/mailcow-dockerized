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
$labels['encryption'] = 'Salaus';
$labels['enigmacerts'] = 'S/MIME-varmenteet';
$labels['enigmakeys'] = 'PGP-avaimet';
$labels['keysfromto'] = 'Avaimet $from-$to/$count';
$labels['keyname'] = 'Nimi';
$labels['keyid'] = 'Avaintunniste';
$labels['keyuserid'] = 'Käyttäjätunniste';
$labels['keytype'] = 'Avaimen tyyppi';
$labels['fingerprint'] = 'Sormenjälki';
$labels['subkeys'] = 'Aliavaimet';
$labels['basicinfo'] = 'Perustiedot';
$labels['userids'] = 'Lisäkäyttäjät';
$labels['typepublickey'] = 'julkinen avain';
$labels['typekeypair'] = 'avainpari';
$labels['keyattfound'] = 'Tämä viesti sisältää yhden tai useamman PGP-avaimen.';
$labels['keyattimport'] = 'Tuo avaimia';
$labels['typesign'] = 'Allekirjoitus';
$labels['typeencrypt'] = 'Salaus';
$labels['typecert'] = 'Todistus';
$labels['typeauth'] = 'Tunnistautuminen';
$labels['subkeyid'] = 'Tunniste';
$labels['subkeyalgo'] = 'Algoritmi';
$labels['subkeycreated'] = 'Luotu';
$labels['subkeyexpires'] = 'Vanhenee';
$labels['subkeyusage'] = 'Käyttö';
$labels['expiresnever'] = 'ei koskaan';
$labels['unknown'] = 'tuntematon';
$labels['uservalid'] = 'Kelvollinen';
$labels['userid'] = 'Tunniste';
$labels['valid'] = 'kelvollinen';
$labels['supportencryption'] = 'Käytä viestien salausta ja allekirjoitusta';
$labels['supportsignatures'] = 'Käytä viestien allekirjoitusten vahvistusta';
$labels['supportdecryption'] = 'Ota käyttöön viestien salauksen purkaminen';
$labels['signdefault'] = 'Allekirjoita kaikki viestit oletuksena';
$labels['encryptdefault'] = 'Salaa kaikki viestit oletuksena';
$labels['attachpubkeydefault'] = 'Liitä julkinen PGP-avaimeni oletuksena';
$labels['passwordtime'] = 'Säilytä yksityisen avaimen salasanat';
$labels['nminutes'] = '$m minuutin ajan';
$labels['wholesession'] = 'koko istunnon ajan';
$labels['createkeys'] = 'Luo uusi avainpari';
$labels['importkeys'] = 'Tuo avaimia';
$labels['exportkeys'] = 'Vie avaimia';
$labels['keyactions'] = 'Avaintoiminnot...';
$labels['keyremove'] = 'Poista';
$labels['keydisable'] = 'Poista käytöstä';
$labels['keyrevoke'] = 'Kumoa';
$labels['keysend'] = 'Lähetä julkinen avain viestissä';
$labels['keychpass'] = 'Vaihda salasana';
$labels['newkeyident'] = 'Identiteetti:';
$labels['newkeypass'] = 'Salasana:';
$labels['newkeypassconfirm'] = 'Vahvista salasana:';
$labels['newkeysize'] = 'Avaimen koko:';
$labels['key2048'] = '2048 bittiä - oletus';
$labels['key4096'] = '4096 bittiä - turvallisempi';
$labels['keygenerating'] = 'Luodaan avaimia...';
$labels['encryptionoptions'] = 'Salausvalinnat...';
$labels['encryptmsg'] = 'Salaa tämä viesti';
$labels['signmsg'] = 'Lisää digitaalinen allekirjoitus tähän viestiin';
$labels['enterkeypasstitle'] = 'Anna tunnuslause';
$labels['arialabelkeyexportoptions'] = 'Avaimien vientivalinnat';
$labels['attachpubkeymsg'] = 'Liitä julkinen avaimeni';
$labels['findkey'] = 'Etsi avainpalvelimilta';
$messages['sigvalid'] = 'Vahvistettu allekirjoitus lähettäjältä $sender.';
$messages['siginvalid'] = 'Virheellinen allekirjoitus lähettäjältä $sender.';
$messages['sigunverified'] = 'Vahvistamaton allekirjoitus. Varmennetta ei ole vahvistettu. Varmenteen tunniste: $keyid.';
$messages['signokey'] = 'Vahvistamaton allekirjoitus. Julkista avainta ei löydy. Avaimen tunniste: $keyid.';
$messages['sigerror'] = 'Vahvistamaton allekirjoitus. Sisäinen virhe.';
$messages['decryptok'] = 'Viestin salaus purettu.';
$messages['decrypterror'] = 'Salauksen purkaminen epäonnistui.';
$messages['decryptnokey'] = 'Salauksen purkaminen epäonnistui. Yksityistä avainta ei löytynyt. Avaimen tunniste: $keyid.';
$messages['decryptnopass'] = 'Salauksen purkaminen epäonnistui. Avaimen salasana vaaditaan.';
$messages['signerror'] = 'Allekirjoittaminen epäonnistui.';
$messages['signnokey'] = 'Allekirjoittaminen epäonnistui. Yksityistä avainta ei löytynyt.';
$messages['signnopass'] = 'Allekirjoitus epäonnistui. Avaimen salasana vaaditaan.';
$messages['encrypterror'] = 'Salaaminen epäonnistui.';
$messages['encryptnokey'] = 'Salaaminen epäonnistui. Osoitteelle $email ei löytynyt julkista avainta.';
$messages['nokeysfound'] = 'Avaimia ei löytynyt';
$messages['keynotfound'] = 'Avainta ei löytynyt!';
$messages['keyopenerror'] = 'Avaintietojen noutaminen ei onnistu! Sisäinen virhe.';
$messages['keylisterror'] = 'Avainten listaaminen ei onnistu! Sisäinen virhe.';
$messages['keysimportfailed'] = 'Avaimen tuominen epäonnistui! Sisäinen virhe.';
$messages['formerror'] = 'Täytä lomake. Kaikki kentät ovat pakollisia!';
$messages['passwordsdiffer'] = 'Salasanat eivät täsmää!';
$messages['keygenerateerror'] = 'Avainparin luominen epäonnistui';
$messages['keygeneratesuccess'] = 'Avainpari luotiin ja tuotiin onnistuneesti.';
$messages['keygennosupport'] = 'Selaimesi ei tue salaustekniikkaa. Avainparin luominen ei onnistu!';
$messages['nonameident'] = 'Identiteetillä tulee olla käyttäjänimi määritettynä!';
?>
