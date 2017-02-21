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
$labels['encryption'] = 'Brongla';
$labels['enigmacerts'] = 'S/MIME skjal';
$labels['enigmakeys'] = 'PGP lyklar';
$labels['keysfromto'] = 'Lyklar frá $from til $to av $count';
$labels['keyname'] = 'Navn';
$labels['keyid'] = 'Lykla ID';
$labels['keyuserid'] = 'Brúkara ID';
$labels['keytype'] = 'Lykla týpa';
$labels['fingerprint'] = 'Fingramerki';
$labels['subkeys'] = 'Undir lyklar';
$labels['basicinfo'] = 'Grund upplýsingar';
$labels['userids'] = 'Eyka brúkarar';
$labels['typepublickey'] = 'almenna lykil';
$labels['typekeypair'] = 'lykla par';
$labels['keyattfound'] = 'Hesi boð innihalda viðheftar PGP lyklar';
$labels['keyattimport'] = 'Innflyt lyk(lar/il)';
$labels['typesign'] = 'Undirskriva';
$labels['typeencrypt'] = 'Brongla';
$labels['typecert'] = 'Vátta';
$labels['typeauth'] = 'Samgildi';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritma';
$labels['subkeycreated'] = 'Stovna';
$labels['subkeyexpires'] = 'Gongur út';
$labels['subkeyusage'] = 'Brúksla';
$labels['expiresnever'] = 'aldrin';
$labels['unknown'] = 'ókent';
$labels['uservalid'] = 'Gildigt';
$labels['userid'] = 'ID';
$labels['valid'] = 'Gildigt';
$labels['supportencryption'] = 'Tendra boð brongling og undirskriving';
$labels['supportsignatures'] = 'Tendra váttan av undirskriftum';
$labels['supportdecryption'] = 'Tendra boð óbrongsling';
$labels['signdefault'] = 'Undirskriva øll boð vanliga';
$labels['encryptdefault'] = 'Brongla øll boð vanligt';
$labels['passwordtime'] = 'Goym loyniorð hjá loyndarlykilum fyri';
$labels['nminutes'] = '$m minutt(ir)';
$labels['wholesession'] = 'øll setanin';
$labels['createkeys'] = 'Skapa nýtt lykla par';
$labels['importkeys'] = 'Innflyt lyk(lar/il)';
$labels['exportkeys'] = 'Flyt út lyklar';
$labels['keyactions'] = 'Lykla gerðir';
$labels['keyremove'] = 'Strika';
$labels['keydisable'] = 'Sløkk';
$labels['keyrevoke'] = 'Sig úr gildi';
$labels['keysend'] = 'Send almennan lykil í einum boðum';
$labels['keychpass'] = 'Broyt loyniorð';
$labels['newkeyident'] = 'Samleiki:';
$labels['newkeypass'] = 'Loyniorð:';
$labels['newkeypassconfirm'] = 'Vátta loyniorð:';
$labels['newkeysize'] = 'Lykla stødd:';
$labels['key2048'] = '2048 bit - vanligt';
$labels['key4096'] = '4096 bits - meiri trygt';
$labels['keygenerating'] = 'Framleiður lyklar...';
$labels['encryptionoptions'] = 'Bronglu møguleikar';
$labels['encryptmsg'] = 'Brongla hesi boð';
$labels['signmsg'] = 'Undirskriva hesi boð';
$labels['enterkeypasstitle'] = 'Skriva loyniorð';
$labels['enterkeypass'] = 'Tað er tørvur á einum loyniorðið fyri at stongja upp loyndar-lykilin ($keyid) hjá brúkara:';
$labels['arialabelkeyexportoptions'] = 'Lykla út flytingar møguleikar';
$messages['sigvalid'] = 'Váttað undirskrift frá $sender.';
$messages['siginvalid'] = 'Ógildig undirskrift frá $sender.';
$messages['sigunverified'] = 'Óvátta undirskrift: skjal er ikki vátta. Skjal ID: $keyid.';
$messages['signokey'] = 'Óvátta undirskrift. Almennur lykil var ikki funnin. Lykla ID: $keyid.';
$messages['sigerror'] = 'Óvátta boð. Innanhýsis feilur.';
$messages['decryptok'] = 'Boðini eru óbrongla';
$messages['decrypterror'] = 'Feilur kom í óbrongslingina.';
$messages['decryptnokey'] = 'Óbrongsling riggaði ikki. Loyndarlykilin var ikki funnin. Lykila ID: $keyid.';
$messages['decryptnopass'] = 'Óbrongslan riggaði ikki. Lykla loyniorð er neyðugt.';
$messages['signerror'] = 'Undirskriving riggaði ikki.';
$messages['signnokey'] = 'Undirskriving riggaði ikki. Eingin privat lyki funnin';
$messages['signnopass'] = 'Undirskriving riggaði ikki. Lykla loyniorð er neyðugt.';
$messages['encrypterror'] = 'Brongsling riggaði ikk';
$messages['encryptnokey'] = 'Brongsling riggaði ikki. Almennur lykil var ikki funnin fyri $email';
$messages['nokeysfound'] = 'Eingir lyklar vóru funnir';
$messages['keyopenerror'] = 'Bar ikki til at fáa upplýsingar um lyklar! Innanhýsis feilur';
$messages['keylisterror'] = 'Bar ikki til at lista lyklar! Innanhýsis feilur';
$messages['keysimportfailed'] = 'Bar ikki til at innflyta lyk(il/lar)! Innanhýsis feilur';
$messages['keysimportsuccess'] = 'Lykar innfluttir. Innflutt: $new, óbroyttir: $old.';
$messages['keyremoving'] = 'Strikar lyk(il/lar)';
$messages['keyremoveconfirm'] = 'Ert tú vísur í, at tú vilt strika hesar lyklar?';
$messages['keyremovesuccess'] = 'Lyk(il/lar) eru strikaðir';
$messages['keyremoveerror'] = 'Ber ikki til at strika lyk(il/lar)';
$messages['keyimporttext'] = 'Tú kanst innflyta almennar og privat lyklar ella taka aftur undirskriftir í ASCII-Armor formati';
$messages['formerror'] = 'Vinarliga fylla allan formin. Øll felt eru kravd!';
$messages['passwordsdiffer'] = 'Loyniorðini eru ikki eins!';
$messages['keygenerateerror'] = 'Feilur kom í, tá roynt var at framleiða lykla par';
$messages['keygeneratesuccess'] = 'Eitt lykla-par var framleitt og inn flutt';
$messages['keygennosupport'] = 'Tín kagari dugur ikki dulmálsvísindi. Ber ikki til at framleiða lykla par';
$messages['nonameident'] = 'samleiki má hava eitt brúkaranavn';
?>
