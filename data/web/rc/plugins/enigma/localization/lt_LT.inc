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
$labels['encryption'] = 'Šifravimas';
$labels['enigmacerts'] = 'S/MIME liudijimai';
$labels['enigmakeys'] = 'PGP raktai';
$labels['keysfromto'] = '$from – $to raktai iš $count';
$labels['keyname'] = 'Vardas';
$labels['keyid'] = 'Rakto ID';
$labels['keyuserid'] = 'Naudotojo ID';
$labels['keytype'] = 'Rakto tipas';
$labels['fingerprint'] = 'Kontrolinis kodas';
$labels['subkeys'] = 'Porakčiai';
$labels['basicinfo'] = 'Pagrindinė informacija';
$labels['userids'] = 'Papildomi naudotojai';
$labels['typepublickey'] = 'viešasis raktas';
$labels['typekeypair'] = 'raktų pora';
$labels['keyattfound'] = 'Šiame laiške aptiktas PGP raktas (raktai).';
$labels['keyattimport'] = 'Importuoti raktą (raktus)';
$labels['typesign'] = 'Pasirašyti';
$labels['typeencrypt'] = 'Šifruoti';
$labels['typecert'] = 'Liudyti';
$labels['typeauth'] = 'Autentikacija';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritmas';
$labels['subkeycreated'] = 'Sukurtas';
$labels['subkeyexpires'] = 'Galioja iki';
$labels['subkeyusage'] = 'Naudotinas';
$labels['expiresnever'] = 'neterminuotai';
$labels['unknown'] = 'nežinoma';
$labels['uservalid'] = 'Galioja';
$labels['userid'] = 'ID';
$labels['valid'] = 'galioja';
$labels['supportencryption'] = 'Įgalinti laiškų šifravimą ir pasirašymą';
$labels['supportsignatures'] = 'Įgalinti laiškų parašų tikrinimą';
$labels['supportdecryption'] = 'Įgalinti laiškų dešifravimą';
$labels['signdefault'] = 'Numatytai pasirašinėti visus laiškus';
$labels['encryptdefault'] = 'Numatytai šifruoti visus laiškus';
$labels['attachpubkeydefault'] = 'Visada prisegti mano viešąjį raktą prie laiško.';
$labels['passwordtime'] = 'Saugoti privačiųjų raktų slaptažodžius';
$labels['nminutes'] = '$m min.';
$labels['wholesession'] = 'visą seanso laiką';
$labels['createkeys'] = 'Kurti naują raktų porą';
$labels['importkeys'] = 'Importuoti raktus';
$labels['exportkeys'] = 'Eksportuoti raktus';
$labels['keyactions'] = 'Raktų veiksmai…';
$labels['keyremove'] = 'Šalinti';
$labels['keydisable'] = 'Išjungti';
$labels['keyrevoke'] = 'Atšaukti';
$labels['keysend'] = 'Siųsti viešąjį raktą kartu su laišku';
$labels['keychpass'] = 'Keisti slaptažodį';
$labels['newkeyident'] = 'Tapatybė:';
$labels['newkeypass'] = 'Slaptažodis:';
$labels['newkeypassconfirm'] = 'Patvirtinkite slaptažodį:';
$labels['newkeysize'] = 'Rakto dydis:';
$labels['key2048'] = '2048 bitai – numatytasis';
$labels['key4096'] = '4096 bitai – saugesnis';
$labels['keygenerating'] = 'Raktai generuojami…';
$labels['encryptionoptions'] = 'Šifravimo parinktys…';
$labels['encryptmsg'] = 'Šifruoti šį laišką';
$labels['signmsg'] = 'Pasirašyti šį laišką';
$labels['enterkeypasstitle'] = 'Įveskite rakto slaptafrazę';
$labels['enterkeypass'] = 'Naudotojo $user slaptajam raktui ($keyid) atrakinti reikalinga slaptafrazė.';
$labels['arialabelkeyexportoptions'] = 'Raktų eksportavimo parinktys';
$labels['attachpubkeymsg'] = 'Prisegti mano viešąjį raktą';
$labels['keyexportprompt'] = 'Ar norite įkelti privačiuosius raktus, saugojamus OpenPGP raktų faile?';
$labels['onlypubkeys'] = 'Eksportuoti tik viešuosius raktus';
$labels['withprivkeys'] = 'Eksportuoti privačiuosius raktus';
$labels['findkey'] = 'Ieškoti raktų serveryje(-iuose)';
$labels['keyimportlabel'] = 'Importuoti iš failo';
$labels['keyimportsearchlabel'] = 'Ieškoti raktų serveryje(-iuose)';
$messages['sigvalid'] = 'Galiojantis $sender parašas.';
$messages['sigvalidpartial'] = '$sender parašas patvirtintas, bet už dalį laiško nebuvo pasirašyta.';
$messages['siginvalid'] = 'Negaliojantis $sender parašas.';
$messages['sigunverified'] = 'Parašo galiojimas nepatikrintas. Nepatvirtintas liudijimas. Liudijimo ID: $keyid.';
$messages['signokey'] = 'Parašo galiojimas nepatikrintas. Nerastas viešasis raktas. Rakto ID: $keyid.';
$messages['sigerror'] = 'Parašo galiojimas nepatikrintas. Įvyko vidinė klaida.';
$messages['decryptok'] = 'Laiškas iššifruotas.';
$messages['decrypterror'] = 'Iššifruoti nepavyko.';
$messages['decryptnokey'] = 'Iššifruoti nepavyko. Nerastas privatusis raktas. Rakto ID: $keyid.';
$messages['decryptnopass'] = 'Nepavyko dešifruoti, reikalingas rakto slaptažodis.';
$messages['decryptpartial'] = 'Laiškas dešifruotas, nors dalis laiško ir nebuvo užšifruota.';
$messages['signerror'] = 'Pasirašyti nepavyko.';
$messages['signnokey'] = 'Pasirašyti nepavyko. Nerastas privatusis raktas.';
$messages['signnopass'] = 'Nepavyko pasirašyti, reikalingas rakto slaptažodis.';
$messages['encrypterror'] = 'Užšifruoti nepavyko.';
$messages['encryptnokey'] = 'Užšifruoti nepavyko. Nerastas viešasis $email raktas.';
$messages['nokeysfound'] = 'Raktų nerasta';
$messages['keynotfound'] = 'Raktas nerastas!';
$messages['keyopenerror'] = 'Nepavyko gauti rakto duomenų! Įvyko vidinė klaida.';
$messages['keylisterror'] = 'Nepavyko išvardinti raktų! Įvyko vidinė klaida.';
$messages['keysimportfailed'] = 'Nepavyko importuoti rakto (raktų)! Įvyko vidinė klaida.';
$messages['keysimportsuccess'] = 'Raktai importuoti sėkmingai. Nauji raktai: $new, nepakitę: $old.';
$messages['keyremoving'] = 'Raktų šalinimas…';
$messages['keyremoveconfirm'] = 'Ar tikrai norite pašalinti pažymėtus raktus?';
$messages['keyremovesuccess'] = 'Raktai sėkmingai pašalinti';
$messages['keyremoveerror'] = 'Pasirinktų raktų pašalinti nepavyko.';
$messages['keyimporttext'] = 'Galite importuoti „ASCII-Armor“ formato privačiuosius ir viešuosius raktus bei atšaukimo liudijimus.';
$messages['keyimportsearchtext'] = 'Galite ieškoti viešųjų raktų pagal rakto identifikatorių, vartotojo vardą arba el. pašto adresą ir juos tiesiogiai importuoti.';
$messages['formerror'] = 'Prašome užpildyti formą. Visi laukai privalomi!';
$messages['passwordsdiffer'] = 'Slaptažodžiai nesutampa!';
$messages['keygenerateerror'] = 'Nepavyko sugeneruoti raktų poros.';
$messages['keygeneratesuccess'] = 'Raktų pora sėkmingai sugeneruota ir importuota.';
$messages['keygennosupport'] = 'Jūsų naršyklėje kriptografija nepalaikoma. Raktų poros sugeneruoti nepavyko!';
$messages['nonameident'] = 'Tapatybei turi būti nurodytas naudotojo vardas!';
?>
