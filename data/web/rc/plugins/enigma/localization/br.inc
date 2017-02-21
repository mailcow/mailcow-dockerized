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
$labels['encryption'] = 'Enrinegañ';
$labels['enigmacerts'] = 'Testenioù S/MIME';
$labels['enigmakeys'] = 'Alc\'hwezioù PGP';
$labels['keysfromto'] = 'Alc\'hwezioù $from da $to war $count';
$labels['keyname'] = 'Anv';
$labels['keyid'] = 'Naoudi an alc\'hwez';
$labels['keyuserid'] = 'Naoudi an arveriad';
$labels['keytype'] = 'Rizh an alc\'hwez';
$labels['fingerprint'] = 'Roudoù-biz';
$labels['subkeys'] = 'Is-alc\'hwez';
$labels['basicinfo'] = 'Titouroù diazez';
$labels['userids'] = 'Arveriaded ouzhpenn';
$labels['typepublickey'] = 'alc\'hwez foran';
$labels['typekeypair'] = 'daouad alc\'hwezioù';
$labels['keyattfound'] = 'Alc\'hwezioù PGP a zo staget d\'ar gemennadenn-mañ.';
$labels['keyattimport'] = 'Enporzhiañ an alc\'hwez(ioù)';
$labels['typesign'] = 'Sinañ';
$labels['typeencrypt'] = 'Enrinegañ';
$labels['typecert'] = 'Testeniañ';
$labels['typeauth'] = 'Dilesa';
$labels['subkeyid'] = 'Naoudi';
$labels['subkeyalgo'] = 'Treol';
$labels['subkeycreated'] = 'Krouet';
$labels['subkeyexpires'] = 'Diamzeriñ';
$labels['subkeyusage'] = 'Arver';
$labels['expiresnever'] = 'morse';
$labels['unknown'] = 'dianav';
$labels['uservalid'] = 'Talvoudek';
$labels['userid'] = 'Naoudi';
$labels['valid'] = 'talvoudek';
$labels['supportencryption'] = 'Gweredekaat enrinegañ ha sinañ ar c\'hemennadennoù';
$labels['supportsignatures'] = 'Gweredekaat gwiriañ sinadur ar c\'hemennadennoù';
$labels['supportdecryption'] = 'Gweredekaat ezrinegañ ar c\'hemennadennoù';
$labels['signdefault'] = 'Sinañ an goll gemennadennoù dre ziouer';
$labels['encryptdefault'] = 'Enrinegañ an holl gemennadennoù dre ziouer ';
$labels['attachpubkeydefault'] = 'Kenstagañ ma alc\'hwez foran PGP dre ziouer';
$labels['passwordtime'] = 'Mirout gerioù-tremen an alc\'hwezioù prevez e-pad ';
$labels['nminutes'] = '$m a vunutennoù';
$labels['wholesession'] = 'an estez a-bezh';
$labels['createkeys'] = 'Krouiñ un daouad alc\'hwezioù nevez';
$labels['importkeys'] = 'Enporzhiañ un alc\'hwez';
$labels['exportkeys'] = 'Ezporzhiañ un alc\'hwez';
$labels['keyactions'] = 'Gweredoù an alc\'hwez...';
$labels['keyremove'] = 'Dilemel';
$labels['keydisable'] = 'Diweredekaat';
$labels['keyrevoke'] = 'Dizorniañ';
$labels['keysend'] = 'Kas an alc\'hwez foran en ur gemennadenn';
$labels['keychpass'] = 'Kemmañ ar ger-tremen';
$labels['newkeyident'] = 'Pivelezh:';
$labels['newkeypass'] = 'Ger-tremen:';
$labels['newkeypassconfirm'] = 'Kadarnaat ar ger-tremen:';
$labels['newkeysize'] = 'Ment an alc\'hwez:';
$labels['key2048'] = '2048 bit - dre ziouer';
$labels['key4096'] = '4096 bit - diogeloc\'h';
$labels['keygenerating'] = 'O c\'henel an alc\'hwezioù...';
$labels['encryptionoptions'] = 'Dibarzhioù enrinegañ...';
$labels['encryptmsg'] = 'Enrinegañ ar gemennadenn-mañ';
$labels['signmsg'] = 'Sinañ ent niverel ar gemennadenn-mañ';
$labels['enterkeypasstitle'] = 'Enankañ frazenn-dremen an alc\'hwez';
$labels['enterkeypass'] = 'Ur frazenn-dremen a zo dleet evit dibrennañ an alc\'hwez prevez ($keyid= evit an arveriad: $user';
$labels['arialabelkeyexportoptions'] = 'Dibarzhioù ezporzhiañ an alc\'hwezioù';
$labels['attachpubkeymsg'] = 'Stagañ ma alc\'hwez foran';
$labels['keyexportprompt'] = 'Fellout a ra deoc\'h enkorfañ an alc\'hwezioù kuzh er restr alc\'hwezioù OpenPGP enrollet?';
$labels['onlypubkeys'] = 'Ezporzhiañ an alc\'hwezhioù foran hepken';
$labels['withprivkeys'] = 'Ezporzhiañ an alc\'hwezioù kuzh';
$labels['findkey'] = 'Klask war an dafariad(où) alc\'hwezioù';
$messages['sigvalid'] = 'Sinadur gwiriet a-berzh $sender.';
$messages['sigvalidpartial'] = 'Sinadur gwiriet a-berzh $sender, met ul lodenn eus ar c\'horf n\'eo ket enrineget.';
$messages['siginvalid'] = 'Sinadur didalvoudek a-berzh $sender.';
$messages['sigunverified'] = 'Sinadur amwiriet. testeni amwiriet. Naoudi an testeni: $keyid.';
$messages['signokey'] = 'Sinadur amwiriet. Alc\'hwez foran ebet kavet. Naoudi an alc\'hwez: $keyid.';
$messages['sigerror'] = 'Sinadur amwiriet. Fazi diabarzh.';
$messages['decryptok'] = 'Kemennadenn ezrineget.';
$messages['decrypterror'] = 'Ezrinegañ c\'hwitet.';
$messages['decryptnokey'] = 'Ezrinegañ c\'hwitet. Alc\'hwez prevez ebet kavet. Naoudi an alc\'hwez: $keyid.';
$messages['decryptnopass'] = 'Ezrinegañ c\'hwitet. Frazenn-dremen azgoulennet.';
$messages['decryptpartial'] = 'Kemennadenn ezrineget, met ul lodenn eus ar c\'horf ne oa ket enrineget.';
$messages['signerror'] = 'Sinadur c\'hwitet.';
$messages['signnokey'] = 'Sinadur c\'hwitet. Alc\'hwez prevez ebet kavet.';
$messages['signnopass'] = 'Sinadur c\'hwitet. Frazenn-dremen azgoulennet.';
$messages['encrypterror'] = 'Enrinegañ c\'hwitet.';
$messages['encryptnokey'] = 'Enrinegañ c\'hwitet. N\'eo ket bet kavet an alc\'hwez foran evit $email.';
$messages['nokeysfound'] = 'Alc\'hwez ebet kavet';
$messages['keynotfound'] = 'N\'eo ket bet kavet an alc\'hwez!';
$messages['keyopenerror'] = 'N\'haller ket kaout titouroù an alc\'hwez! Fazi diabarzh.';
$messages['keylisterror'] = 'N\'haller ket rollañ an alc\'hwezioù! Fazi diabarzh.';
$messages['keysimportfailed'] = 'N\'haller ket enporzhiañ an alc\'hwez(ioù)! Fazi diabarzh.';
$messages['keysimportsuccess'] = 'Alc\'hwez(ioù) enporzhiet gant berzh. Enporzhiet: $new, digemm: $old.';
$messages['keyremoving'] = 'O tilemel an alc\'hwez(ioù)...';
$messages['keyremoveconfirm'] = 'Fellout a ra deoc\'h dilemel an alc\'hwez(ioù) diuzet?';
$messages['keyremovesuccess'] = 'Alc\'hwez(ioù) dilamet gant berzh';
$messages['keyremoveerror'] = 'N\'haller ket dilemel an alc\'hwez(ioù) diuzet.';
$messages['keyimporttext'] = 'Gallout a rit enporzhiañ alc\'hwez(ioù) foran ha prevez pe sinadurioù dizorniañ er mentrezh ASCII-Armor.';
$messages['formerror'] = 'Ret eo leuniañ ar furmskrid. An holl vaeziennoù a zo dleet!';
$messages['passwordsdiffer'] = 'Ne glot ket ar gerioù-tremen!';
$messages['keygenerateerror'] = 'C\'hwitadenn war ganedigezh un daouad alc\'hwezhioù';
$messages['keygeneratesuccess'] = 'Ganet hag enporzhiet eo bet un daouad alc\'hwezioù gant berzh. ';
$messages['keygennosupport'] = 'N\'eo ket skoret an enrinegañ gant ho merdeer web. N\'haller ket genel un alc\'hwez nevez.';
$messages['nonameident'] = 'Ret eo d\'ar pivelezh kaout un anv arveriad despizet!';
?>
