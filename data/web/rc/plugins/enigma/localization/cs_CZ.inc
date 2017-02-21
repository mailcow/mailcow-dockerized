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
$labels['encryption'] = 'Šifrování';
$labels['enigmacerts'] = 'S/MIME certifikáty';
$labels['enigmakeys'] = 'PGP klíče';
$labels['keysfromto'] = 'Klíče $from až $to z počtu: $count';
$labels['keyname'] = 'Jméno';
$labels['keyid'] = 'ID Klíče';
$labels['keyuserid'] = 'ID uživatele';
$labels['keytype'] = 'Typ klíče';
$labels['fingerprint'] = 'Otisk prstu';
$labels['subkeys'] = 'Podklíče';
$labels['basicinfo'] = 'Základní informace';
$labels['userids'] = 'Další uživatelé';
$labels['typepublickey'] = 'veřejný klíč';
$labels['typekeypair'] = 'pár klíčů';
$labels['keyattfound'] = 'Tato zpráva má přiložený/é PGP klíč(e).';
$labels['keyattimport'] = 'Importovat klíč(e)';
$labels['typesign'] = 'Podepsat';
$labels['typeencrypt'] = 'Zašifrovat';
$labels['typecert'] = 'Certifikovat';
$labels['typeauth'] = 'Autentizace';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritmus';
$labels['subkeycreated'] = 'Vytvořený';
$labels['subkeyexpires'] = 'Vyprší';
$labels['subkeyusage'] = 'Použití';
$labels['expiresnever'] = 'nikdy';
$labels['unknown'] = 'neznámý';
$labels['uservalid'] = 'Správný';
$labels['userid'] = 'ID';
$labels['valid'] = 'správný';
$labels['supportencryption'] = 'Povolit podepisování a šifrování zpráv.';
$labels['supportsignatures'] = 'Povolit ověřování podpisů zpráv.';
$labels['supportdecryption'] = 'Povolit dešifrování zpráv.';
$labels['signdefault'] = 'Podepisovat všechny zprávy';
$labels['encryptdefault'] = 'Šifrovat všechny zprávy';
$labels['passwordtime'] = 'Uchovat hesla pro privátní klíče pro';
$labels['nminutes'] = '$m minut(a)';
$labels['wholesession'] = 'celá relace';
$labels['createkeys'] = 'Vytvořit nový pár klíčů';
$labels['importkeys'] = 'Importovat klíč(e)';
$labels['exportkeys'] = 'Exportovat klíč(e)';
$labels['keyactions'] = 'Akce s klíčem...';
$labels['keyremove'] = 'Smazat';
$labels['keydisable'] = 'Vypnout';
$labels['keyrevoke'] = 'Revokovat';
$labels['keysend'] = 'Odeslat veřejný klíč ve zprávě';
$labels['keychpass'] = 'Změnit heslo';
$labels['newkeyident'] = 'Identita:';
$labels['newkeypass'] = 'Heslo:';
$labels['newkeypassconfirm'] = 'Potvrdit heslo:';
$labels['newkeysize'] = 'Velikost klíče:';
$labels['key2048'] = '2048 bitů - výchozí';
$labels['key4096'] = '4093 bítů - více bezpečné';
$labels['keygenerating'] = 'Generuji klíče...';
$labels['encryptionoptions'] = 'Možnosti šifrování...';
$labels['encryptmsg'] = 'Zašifrovat tuto zprávu';
$labels['signmsg'] = 'Digitálně podepsat tuto zprávu';
$labels['enterkeypasstitle'] = 'Zadejte heslo pro klíč';
$labels['enterkeypass'] = 'Heslo je nutné k odemknutí soukromého klíče ($keyid) pro uživatele: $user.';
$labels['arialabelkeyexportoptions'] = 'Možnosti pro export klíčů';
$labels['attachpubkeymsg'] = 'Připojit můj veřejný klíč';
$labels['onlypubkeys'] = 'Exportovat pouze veřejné klíče';
$labels['withprivkeys'] = 'Exportovat soukromé klíče';
$labels['keyimportlabel'] = 'Importovat ze souboru';
$labels['keyimportsearchlabel'] = 'Hledat klíč na keyserveru';
$messages['sigvalid'] = 'Ověřený podpis od $sender.';
$messages['sigvalidpartial'] = 'Ověřený podpis od $sender, ale část těla není podepsána.';
$messages['siginvalid'] = 'Nesprávný podpis od $sender.';
$messages['sigunverified'] = 'Neověřený podpis. Certifikát není ověřen. ID certifikátu: $keyid.';
$messages['signokey'] = 'Neověřený podpis. Veřejný klíč nebyl nalezen. ID klíče: $keyid.';
$messages['sigerror'] = 'Neověřený podpis. Interní chyba.';
$messages['decryptok'] = 'Zpráva byla dešifrována.';
$messages['decrypterror'] = 'Dešifrování selhalo.';
$messages['decryptnokey'] = 'Dešifrování selhalo. Veřejný klíč nebyl nalezen. ID klíče: $keyid.';
$messages['decryptnopass'] = 'Dešifrování selhalo. Heslo pro klíč je požadováno.';
$messages['signerror'] = 'Podepisování selhalo.';
$messages['signnokey'] = 'Podepisování selhalo. Soukromý klíč nebyl nalezen.';
$messages['signnopass'] = 'Podepisování selhalo. Heslo pro klíč je požadováno.';
$messages['encrypterror'] = 'Šifrování selhalo.';
$messages['encryptnokey'] = 'Šifrování selhalo. Veřejný klíč pro email $email nebyl nalezen.';
$messages['nokeysfound'] = 'Nebyly nalezeny žádné klíče.';
$messages['keynotfound'] = 'Klíč nebyl nalezen!';
$messages['keyopenerror'] = 'Nemohu dostat informace o klíči! Interní chyba.';
$messages['keylisterror'] = 'Nemohu získat seznam klíčů! Interní chyba.';
$messages['keysimportfailed'] = 'Nemohu importovat klíč(e)! Interní chyba.';
$messages['keysimportsuccess'] = 'Klíč(e) byl(y) úspěšně naimportován(y). Importovány: $new, nezměněny: $old.';
$messages['keyremoving'] = 'Odstraňuji klíč(e)...';
$messages['keyremoveconfirm'] = 'Opravdu si přejete odstranit vybraný/é klíč(e)?';
$messages['keyremovesuccess'] = 'Klíč(e) byl(y) úspěšně odstraněn(y).';
$messages['keyremoveerror'] = 'Nemohu smazat vybraný/é klíč(e).';
$messages['keyimporttext'] = 'Můžete naimportovat soukromý/é a veřejný/é klíč(e) nebo podpisy pro revokaci ve formátu ASCII-Armor.';
$messages['formerror'] = 'Prosím vyplňte tento formulář. Všechna pole jsou požadována.';
$messages['passwordsdiffer'] = 'Zadaná hesla se neshodují!';
$messages['keygenerateerror'] = 'Selhalo generování páru klíčů';
$messages['keygeneratesuccess'] = 'Pár klíčů byl úspěšně vygenerován a naimportován.';
$messages['keygennosupport'] = 'Váš webový prohlížeč nepodporuje kryptografii. Nemohu vygenerovat pár klíčů.';
$messages['nonameident'] = 'Identita musí mít definované uživatelské jméno.';
?>
