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
$labels['encryption'] = 'Amgryptiad';
$labels['enigmacerts'] = 'Tystysgrifau S/MIME';
$labels['enigmakeys'] = 'Allweddi PGP';
$labels['keysfromto'] = 'Allweddi $from i $to o $count';
$labels['keyname'] = 'Enw';
$labels['keyid'] = 'ID Allwedd';
$labels['keyuserid'] = 'ID Defnyddiwr';
$labels['keytype'] = 'Math o allwedd';
$labels['fingerprint'] = 'Olion bys';
$labels['subkeys'] = 'Isallweddi';
$labels['basicinfo'] = 'Gwybodaeth sylfaenol';
$labels['userids'] = 'Defnyddwyr Ychwanegol';
$labels['typepublickey'] = 'allwedd gyhoeddus';
$labels['typekeypair'] = 'pâr allweddi';
$labels['keyattfound'] = 'Mae\'r neges hwn yn cynnwys allwedd(i) PGP.';
$labels['keyattimport'] = 'Mewnforio allwedd(i)';
$labels['typesign'] = 'Llofnodi';
$labels['typeencrypt'] = 'Amgryptio';
$labels['typecert'] = 'Tystio';
$labels['typeauth'] = 'Dilysiad';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algorithm';
$labels['subkeycreated'] = 'Crëwyd';
$labels['subkeyexpires'] = 'Yn darfod';
$labels['subkeyusage'] = 'Defnydd';
$labels['expiresnever'] = 'byth';
$labels['unknown'] = 'anhysbys';
$labels['uservalid'] = 'Dilys';
$labels['userid'] = 'ID';
$labels['valid'] = 'dilys';
$labels['supportencryption'] = 'Galluogi amgryptio neges a llofnodi';
$labels['supportsignatures'] = 'Galluogi dilys llofnodion neges';
$labels['supportdecryption'] = 'Galluogi dadgryptio neges';
$labels['signdefault'] = 'Llofnodi pob neges yn ddiofyn';
$labels['encryptdefault'] = 'Amgryptio pob neges yn ddiofyn';
$labels['attachpubkeydefault'] = 'Atodi fy allwedd PGP cyhoeddus yn ddiofyn';
$labels['passwordtime'] = 'Cadw cyfrineiriau allweddi preifat am';
$labels['nminutes'] = '$m munud';
$labels['wholesession'] = 'y sesiwn i gyd';
$labels['createkeys'] = 'Creu pâr allweddi newydd';
$labels['importkeys'] = 'Mewnforio allwedd(i)';
$labels['exportkeys'] = 'Allforio allwedd(i)';
$labels['keyactions'] = 'Gweithredoedd allwedd...';
$labels['keyremove'] = 'Dileu';
$labels['keydisable'] = 'Analluogi';
$labels['keyrevoke'] = 'Dirymu';
$labels['keysend'] = 'Danfon allwedd gyhoeddus mewn neges';
$labels['keychpass'] = 'Newid cyfrinair';
$labels['newkeyident'] = 'Personoliaeth:';
$labels['newkeypass'] = 'Cyfrinair:';
$labels['newkeypassconfirm'] = 'Cadarnhau cyfrinair:';
$labels['newkeysize'] = 'Maint allwedd:';
$labels['key2048'] = '2048 did - diofyn';
$labels['key4096'] = '4096 did - mwy cadarn';
$labels['keygenerating'] = 'Yn creu allweddi...';
$labels['encryptionoptions'] = 'Dewisiadau amgryptio...';
$labels['encryptmsg'] = 'Amgryptio\'r neges hwn';
$labels['signmsg'] = 'Llofnodi\'r neges yma yn ddigidol';
$labels['enterkeypasstitle'] = 'Rhowch yr ymadrodd cyfrin';
$labels['enterkeypass'] = 'Mae angen ymadrodd cyfrin i ddatgloi allwedd breifat ($keyid) ar gyfer defnyddiwr: $user:';
$labels['arialabelkeyexportoptions'] = 'Dewisiadau allforio allweddi';
$labels['attachpubkeymsg'] = 'Atodi fy allwedd cyhoeddus';
$labels['keyexportprompt'] = 'Ydych eisiau cynnwys allweddi cyfrinachol yn y ffeil OpenPGP i\'w gadw?';
$labels['onlypubkeys'] = 'Allforio Allweddi Cyhoeddus yn unig';
$labels['withprivkeys'] = 'Allforio Allweddi Cyfrinachol';
$labels['findkey'] = 'Chwilio ar weinydd(wyr) allweddi';
$labels['keyimportlabel'] = 'Mewnforio o ffeil';
$labels['keyimportsearchlabel'] = 'Chwilio ar weinydd(wyr) allweddi';
$messages['sigvalid'] = 'Llofnod wedi ddilysu o $sender.';
$messages['sigvalidpartial'] = 'Llofnod wedi ddilysu o $sender, ond ni arwyddywyd rhan o gorff y neges';
$messages['siginvalid'] = 'Llofnod annilys o $sender.';
$messages['sigunverified'] = 'Llofnod heb ei ddilysu. Tystysgrif heb ei ddilys. ID Tystysgrif: $keyid.';
$messages['signokey'] = 'Llofnod heb ei ddilysu. Allwedd gyhoeddus heb ei ganfod. ID Allwedd: $keyid.';
$messages['sigerror'] = 'Llofnod heb ei ddilysu. Gwall mewnol.';
$messages['decryptok'] = 'Neges wedi ei ddadgryptio.';
$messages['decrypterror'] = 'Dadgryptio wedi methu.';
$messages['decryptnokey'] = 'Dadgryptio wedi methu. Allwedd breifat heb ei ganfod: ID Allwedd: $keyid.';
$messages['decryptnopass'] = 'Dadgryptio wedi methu. Mae angen cyfrinair.';
$messages['decryptpartial'] = 'Dadgryptiwyd y neges, ond roedd darn o gorff y neges heb ei amgryptio';
$messages['signerror'] = 'Llofnodi wedi methu.';
$messages['signnokey'] = 'Llofnodi wedi methu. Allwedd breifat heb ei ganfod.';
$messages['signnopass'] = 'Llofnodi wedi methu. Mae angen cyfrinair.';
$messages['encrypterror'] = 'Amgryptio wedi methu.';
$messages['encryptnokey'] = 'Amgryptio wedi methu. Allwedd cyhoeddus heb ei ganfod ar gyfer $email.';
$messages['nokeysfound'] = 'Ni ganfuwyd unrhyw allweddi';
$messages['keynotfound'] = 'Ni ganfuwyd yr allwedd!';
$messages['keyopenerror'] = 'Methwyd cael gwybodaeth allwedd! Gwall mewnol.';
$messages['keylisterror'] = 'Methwyd cael rhestr allweddi! Gwall mewnol';
$messages['keysimportfailed'] = 'Methwyd mewnforio allwedd(i)! Gwall mewnol.';
$messages['keysimportsuccess'] = 'Mewnforiwyd allwedd(i) yn llwyddiannus. Mewnforiwyd: $new, heb newid: $old.';
$messages['keyremoving'] = 'Yn dileu allwedd(i)...';
$messages['keyremoveconfirm'] = 'Ydych chi\'n siwr eich bod am ddileu yr allwedd(i) dewiswyd?';
$messages['keyremovesuccess'] = 'Allwedd(i) wedi ei dileu yn llwyddiannus';
$messages['keyremoveerror'] = 'Methwyd dileu yr allwedd(i) dewiswyd.';
$messages['keyimporttext'] = 'Fe allwch chi mewnforio allwedd(i) preifat a chyhoeddus neu lofnodion pallu mewn fformat ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Gallwch chwilio am allweddi cyhoeddus yn ol adnabydd allwedd, enw defnyddiwr neu gyfeiriad ebost ac yna eu mewnforio yn uniongyrchol.';
$messages['formerror'] = 'Llenwch y ffurflen os gwelwch yn dda. Mae angen llenwi pob maes!';
$messages['passwordsdiffer'] = 'Nid yw\'r cyfrineiriau yn cyfateb!';
$messages['keygenerateerror'] = 'Methwyd creu pâr allweddi';
$messages['keygeneratesuccess'] = 'Creuwyd a mewnforiwyd pâr allweddi yn llwyddiannus.';
$messages['keygennosupport'] = 'Nid yw\'ch porwr gwe yn cefnogi amgryptio. Methwyd creu pâr allweddi!';
$messages['noidentselected'] = 'Rhaid i chi ddewis o leiaf un hunaniaeth i\'r allwedd!';
$messages['nonameident'] = 'Rhaid i\'r personoliaeth gael enw defnyddiwr wedi ei ddiffinio!';
?>
