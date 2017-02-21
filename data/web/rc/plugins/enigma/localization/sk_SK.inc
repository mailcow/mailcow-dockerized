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
$labels['encryption'] = 'Šifrovanie';
$labels['enigmacerts'] = 'Certifikáty S/MIME';
$labels['enigmakeys'] = 'Kľúče PGP';
$labels['keysfromto'] = 'Kľúče $from až $to z počtu: $count';
$labels['keyname'] = 'Názov';
$labels['keyid'] = 'ID kľúča';
$labels['keyuserid'] = 'ID používateľa';
$labels['keytype'] = 'Typ kľúča';
$labels['fingerprint'] = 'Odtlačok';
$labels['subkeys'] = 'Podradené kľúče';
$labels['basicinfo'] = 'Základná informácia';
$labels['userids'] = 'Ďalší používatelia';
$labels['typepublickey'] = 'verejný kľúč';
$labels['typekeypair'] = 'pár kľúčov';
$labels['keyattfound'] = 'Táto správa obsahuje priložené kľúče PGP.';
$labels['keyattimport'] = 'Importovať kľúč(e)';
$labels['typesign'] = 'Podpísať';
$labels['typeencrypt'] = 'Zašifrovať';
$labels['typecert'] = 'Certifikovať';
$labels['typeauth'] = 'Autentifikácia';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritmus';
$labels['subkeycreated'] = 'Vytvorené';
$labels['subkeyexpires'] = 'Expiruje';
$labels['subkeyusage'] = 'Využitie';
$labels['expiresnever'] = 'nikdy';
$labels['unknown'] = 'neznáme';
$labels['uservalid'] = 'Platné';
$labels['userid'] = 'ID';
$labels['valid'] = 'platné';
$labels['supportencryption'] = 'Zapnúť šifrovanie a podpisovanie správ';
$labels['supportsignatures'] = 'Zapnúť overovanie podpisov v správe';
$labels['supportdecryption'] = 'Zapnúť dešifrovanie správ';
$labels['signdefault'] = 'Predvolene podpisovať všetky správy';
$labels['encryptdefault'] = 'Predvolene šifrovať všetky správy';
$labels['attachpubkeydefault'] = 'Predvolene pripájať môj verejný PGP kľúč';
$labels['passwordtime'] = 'Uchovávať heslá súkromného kľúča počas';
$labels['nminutes'] = '$m minút/minúty';
$labels['wholesession'] = 'celej relácie';
$labels['createkeys'] = 'Vytvoriť nový pár kľúčov';
$labels['importkeys'] = 'Importovať kľúč(e)';
$labels['exportkeys'] = 'Exportovať kľúč(e)';
$labels['keyactions'] = 'Akcie kľúča...';
$labels['keyremove'] = 'Odstrániť';
$labels['keydisable'] = 'Vypnúť';
$labels['keyrevoke'] = 'Odvolať';
$labels['keysend'] = 'Odoslať verejný kľúč v správe';
$labels['keychpass'] = 'Zmeniť heslo';
$labels['newkeyident'] = 'Identita:';
$labels['newkeypass'] = 'Heslo:';
$labels['newkeypassconfirm'] = 'Potvrdiť heslo:';
$labels['newkeysize'] = 'Veľkosť kľúča:';
$labels['key2048'] = '2048 bitov – predvolené';
$labels['key4096'] = '4096 bitov - bezpečnejšie';
$labels['keygenerating'] = 'Generovanie kľúčov...';
$labels['encryptionoptions'] = 'Nastavenia šifrovania...';
$labels['encryptmsg'] = 'Zašifrovať túto správu';
$labels['signmsg'] = 'Digitálne podpísať túto správu';
$labels['enterkeypasstitle'] = 'Zadať kľúčovú frázu';
$labels['enterkeypass'] = 'Na odomknutie tajného kľúča je potrebná kľúčová fráza ($keyid) pre používateľa: $user.';
$labels['arialabelkeyexportoptions'] = 'Nastavenia exportu kľúčov';
$labels['attachpubkeymsg'] = 'Pripojiť môj verejný kľúč';
$labels['keyexportprompt'] = 'Chcete do uloženého súboru s kľúčmi OpenPGP zahrnúť aj tajné kľúče?';
$labels['onlypubkeys'] = 'Exportovať len verejné kľúče';
$labels['withprivkeys'] = 'Exportovať tajné kľúče';
$labels['findkey'] = 'Hľadať na serveri/serveroch s kľúčom';
$labels['keyimportlabel'] = 'Importovať zo súboru';
$labels['keyimportsearchlabel'] = 'Hľadať na serveri/serveroch s kľúčom';
$messages['sigvalid'] = 'Overený podpis od $sender.';
$messages['sigvalidpartial'] = 'Overený podpis od $sender, ale časť tela podpísaná nebola.';
$messages['siginvalid'] = 'Neplatný podpis od $sender.';
$messages['sigunverified'] = 'Neoverený podpis, certifikát nebol overený. ID certifikátu: $keyid.';
$messages['signokey'] = 'Neoverený podpis. Verejný kľúč sa nenašiel. ID kľúča: $keyid.';
$messages['sigerror'] = 'Neoverený podpis. Interná chyba.';
$messages['decryptok'] = 'Správa bola dešifrovaná.';
$messages['decrypterror'] = 'Dešifrovanie nebolo úspešné.';
$messages['decryptnokey'] = 'Dešifrovanie nebolo úspešné. Súkromný kľúč sa nenašiel. ID kľúča: $keyid.';
$messages['decryptnopass'] = 'Dešifrovanie nebolo úspešné. Vyžaduje sa kľúčové heslo.';
$messages['decryptpartial'] = 'Správa bola dešifrovaná, ale časť jej tela nebola zašifrovaná.';
$messages['signerror'] = 'Vloženie podpisu bolo neúspešné.';
$messages['signnokey'] = 'Vloženie podpisu bolo neúspešné. Nenašiel sa súkromný kľúč.';
$messages['signnopass'] = 'Vloženie podpisu nebolo úspešné. Vyžaduje sa kľúčové heslo.';
$messages['encrypterror'] = 'Šifrovanie nebolo úspešné.';
$messages['encryptnokey'] = 'Šifrovanie nebolo úspešné. Nenašiel sa verejný kľúč pre $email.';
$messages['nokeysfound'] = 'Nenašli sa žiadne kľúče';
$messages['keynotfound'] = 'Kľúč sa nenašiel!';
$messages['keyopenerror'] = 'Nemožno získať informácie o kľúči! Interná chyba.';
$messages['keylisterror'] = 'Nemožno zostaviť zoznam kľúčov! Interná chyba.';
$messages['keysimportfailed'] = 'Nemožno naimportovať kľúč(e)! Interná chyba.';
$messages['keysimportsuccess'] = 'Import kľúča (kľúčov) bol úspešný.Naimportované položky: $new, bez zmeny: $old.';
$messages['keyremoving'] = 'Odstraňovanie kľúča (kľúčov)...';
$messages['keyremoveconfirm'] = 'Naozaj chcete vymazať vybraný kľúč/kľúče?';
$messages['keyremovesuccess'] = 'Vymazanie kľúča (kľúčov) bolo úspešné.';
$messages['keyremoveerror'] = 'Nemožno vymazať vybraný kľúč (vybrané kľúče).';
$messages['keyimporttext'] = 'Môžete naimportovať súkromné a verejné kľúče alebo podpisy pre odvolanie vo formáte ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Verejné kľúče môžete vyhľadávať podľa identifikátora kľúča, mena používateľa alebo e-mailovej adresy a potom ich priamo importovať.';
$messages['formerror'] = 'Prosím vyplňte formulár. Je nutné vyplniť všetky polia!';
$messages['passwordsdiffer'] = 'Heslá sa nezhodujú!';
$messages['keygenerateerror'] = 'Nepodarilo sa vygenerovať pár kľúčov';
$messages['keygeneratesuccess'] = 'Pár kľúčov bol úspešne vygenerovaný a naimportovaný.';
$messages['keygennosupport'] = 'Váš internetový prehliadač nepodporuje kryptografiu. Nemožno vygenerovať pár kľúčov!';
$messages['noidentselected'] = 'Pre tento kľúč si musíte vybrať aspoň jednu identitu!';
$messages['nonameident'] = 'Identita musí mať definované meno používateľa!';
?>
