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
$labels['encryption'] = 'Fshehtëzim';
$labels['enigmacerts'] = 'Dëshmi S/MIME';
$labels['enigmakeys'] = 'Kyçe PGP';
$labels['keysfromto'] = 'Kyçat nga $from deri në $to nga $count gjithsej';
$labels['keyname'] = 'Emër';
$labels['keyid'] = 'ID Kyçi';
$labels['keyuserid'] = 'ID Përdoruesi';
$labels['keytype'] = 'Lloj kyçi';
$labels['fingerprint'] = 'Shenja gishtash';
$labels['subkeys'] = 'Nënkyç';
$labels['basicinfo'] = 'Informacion bazë';
$labels['userids'] = 'Përdorues Shtesë';
$labels['typepublickey'] = 'kyç publik';
$labels['typekeypair'] = 'çift kyçesh';
$labels['keyattfound'] = 'Ky mesazh përmban kyçin(et) PGP të bashkëngjitur.';
$labels['keyattimport'] = 'Importo kyç(e)';
$labels['typesign'] = 'Nënshkruaje';
$labels['typeencrypt'] = 'Fshehtëzoje';
$labels['typecert'] = 'Dëshmoje';
$labels['typeauth'] = 'Mirëfilltësim';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritëm';
$labels['subkeycreated'] = 'Krijuar më';
$labels['subkeyexpires'] = 'Skadon më';
$labels['subkeyusage'] = 'Përdorim';
$labels['expiresnever'] = 'kurrë';
$labels['unknown'] = 'i panjohur';
$labels['uservalid'] = 'I vlefshëm';
$labels['userid'] = 'ID';
$labels['valid'] = 'i vlefshëm';
$labels['supportencryption'] = 'Aktivizo fshehtëzim dhe nënshkrim mesazhesh';
$labels['supportsignatures'] = 'Aktivizo verifikim nënshkrimesh mesazhi';
$labels['supportdecryption'] = 'Aktivizo çfshehtëzim mesazhi';
$labels['signdefault'] = 'Si parazgjedhje, nënshkruaji krejt mesazhet';
$labels['encryptdefault'] = 'Si parazgjedhje, fshehtëzoji krejt mesazhet';
$labels['attachpubkeydefault'] = 'Bashkëngjite kyçin tim publik, si parazgjedhje';
$labels['passwordtime'] = 'Fjalëkalimet e kyçeve privatë mbaji për';
$labels['nminutes'] = '$m minutë(a)';
$labels['wholesession'] = 'krejt sesionin';
$labels['createkeys'] = 'Krijoni një çift të ri kyçesh';
$labels['importkeys'] = 'Importo kyç(e)';
$labels['exportkeys'] = 'Eksporto kyç(e)';
$labels['keyactions'] = 'Veprime kyçesh…';
$labels['keyremove'] = 'Hiqe';
$labels['keydisable'] = 'Çaktivizoje';
$labels['keyrevoke'] = 'Shfuqizoje';
$labels['keysend'] = 'Dërgo kyç publik në mesazh';
$labels['keychpass'] = 'Ndryshoni fjalëkalimin';
$labels['newkeyident'] = 'Identitet';
$labels['newkeypass'] = 'Fjalëkalim:';
$labels['newkeypassconfirm'] = 'Ripohoni fjalëkalimin:';
$labels['newkeysize'] = 'Madhësi kyçi:';
$labels['key2048'] = '2048 bite - parazgjedhje';
$labels['key4096'] = '4096 bite - më i sigurt';
$labels['keygenerating'] = 'Po prodhohen kyçe…';
$labels['encryptionoptions'] = 'Mundësi fshehtëzimi…';
$labels['encryptmsg'] = 'Fshehtëzoje këtë mesazj';
$labels['signmsg'] = 'Nënshkruaje në mënyrë dixhitale këtë mesazh';
$labels['enterkeypasstitle'] = 'Jepni frazëkalim kyçi';
$labels['enterkeypass'] = 'Frazëkalimi është i nevojshëm për të zhbllokuar kyçin e fshehtë ($keyid) për përdoruesin: $user.';
$labels['arialabelkeyexportoptions'] = 'Mundësi eksportimi kyçesh';
$labels['attachpubkeymsg'] = 'Bashkëngjite kyçin tim publik';
$labels['keyexportprompt'] = 'Doni të përfshihen kyçe të fshehtë te skedari i ruajtur për kyçe OpenPGP?';
$labels['onlypubkeys'] = 'Eksporto Vetëm Kyçe Publikë';
$labels['withprivkeys'] = 'Eksporto Kyçe të Fshehtë';
$labels['findkey'] = 'Kërko në shërbyes kyçesh';
$labels['keyimportlabel'] = 'Importo prej skedari';
$labels['keyimportsearchlabel'] = 'Kërko në shërbyes kyçesh';
$messages['sigvalid'] = 'Nënshkrim nga $sender i verifikuar.';
$messages['sigvalidpartial'] = 'Nënshkrim nga $sender i verifikuar, por s’pati të nënshkruar pjesë lënde.';
$messages['siginvalid'] = 'Nënshkrim  i pavlefshëm nga $sender.';
$messages['sigunverified'] = 'Nënshkrim i paverifikuar. Dëshmia s’u verifikua. ID Dëshmie: $keyid.';
$messages['signokey'] = 'Nënshkrim i paverifikuar. S’u gjet kyç. ID Kyçi: $keyid.';
$messages['sigerror'] = 'Nënshkrim i paverifikuar. Gabim i brendshëm.';
$messages['decryptok'] = 'Mesazhi u çfshehtëzua.';
$messages['decrypterror'] = 'Çfshehtëzimi dështoi.';
$messages['decryptnokey'] = 'Çfshehtëzimi dështoi. S’u gjet kyç privat. ID Kyçi: $keyid.';
$messages['decryptbadpass'] = 'Shfshehtëzimi dështoi. Fjalëkalim i pavlefshëm.';
$messages['decryptnopass'] = 'Çfshehtëzimi dështoi. Lypset fjalëkalim kyçi.';
$messages['decryptpartial'] = 'Mesazhi u çfshehtëzua, por një pjesë e lëndë s’u çfshehtëzua.';
$messages['signerror'] = 'Nënshkrimi dështoi.';
$messages['signnokey'] = 'Nënshkrimi dështoi. S’u gjet kyç privat.';
$messages['signbadpass'] = 'Hyrja dështoi. Fjalëkalim i pavlefshëm.';
$messages['signnopass'] = 'Nënshkrimi dështoi. Lypset fjalëkalim kyçi.';
$messages['encrypterror'] = 'Fshehtëzimi dështoi.';
$messages['encryptnokey'] = 'Fshehtëzimi dështoi. S’u gjet kyç publik për $email';
$messages['nokeysfound'] = 'S’u gjetën kyçe';
$messages['keynotfound'] = 'S’u gjet kyç!';
$messages['keyopenerror'] = 'I pazoti të marrë të dhëna kyçi! Gabim i brendshëm.';
$messages['keylisterror'] = 'I pazoti të paraqesë kyçe! Gabim i brendshëm.';
$messages['keysimportfailed'] = 'I pazoti të importojë kyç(e)! Gabim i brendshëm.';
$messages['keysimportsuccess'] = 'Kyçi(et) u importuan me sukses. Të importuar: $new, të pandryshuar: $old.';
$messages['keyremoving'] = 'Po hiqet kyçi(et)…';
$messages['keyremoveconfirm'] = 'Jeni i sigurt se doni të fshihet kyçi(et) i përzgjedhur?';
$messages['keyremovesuccess'] = 'Kyçi(et) u fshi me sukses';
$messages['keyremoveerror'] = 'I pazoti të fshijë kyçin(et) e përzgjedhur?';
$messages['keyimporttext'] = 'Mund të importoni kyç(e) privatë dhe publikë ose nënshkrime shfuqizimesh në formatin ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Për kyçe publikë mund të kërkoni sipas identifikuesi kyçi, emri përdoruesi ose adrese email dhe mandej t’i importoni ata drejtpërsëdrejti.';
$messages['formerror'] = 'Ju lutemi, plotësoni formularin. Janë të domosdoshme krejt fushat!';
$messages['passwordsdiffer'] = 'Fjalëkalimet nuk përputhen!';
$messages['keygenerateerror'] = 'Dështoi prodhimi i një çifti kyçesh';
$messages['keygeneratesuccess'] = 'U prodhua dhe importua me sukses një çift kyçesh.';
$messages['keygennosupport'] = 'Shfletuesi juaj nuk e mbulon kriptografinë. I pazoti të prodhojë një çift kyçesh!';
$messages['noidentselected'] = 'Duhet të përzgjidhni të paktën një identitet për kyçin!';
$messages['nonameident'] = 'Identiteti duhet të ketë të përkufizuar një emër përdoruesi!';
?>
