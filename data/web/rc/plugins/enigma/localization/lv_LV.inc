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
$labels['encryption'] = 'Šifrēšana';
$labels['enigmacerts'] = 'S/MIME sertifikāti';
$labels['enigmakeys'] = 'PGP atslēgas';
$labels['keysfromto'] = 'Atslēgas $from līdz $to no $count';
$labels['keyname'] = 'Vārds';
$labels['keyid'] = 'Atslēgas ID';
$labels['keyuserid'] = 'Lietotāja ID';
$labels['keytype'] = 'Atslēgas tips';
$labels['fingerprint'] = 'Pirksta nospiedums';
$labels['subkeys'] = 'Apakšatslēgas';
$labels['basicinfo'] = 'Pamatinformācija';
$labels['userids'] = 'Papildus lietotāji';
$labels['typepublickey'] = 'publiskā atslēga';
$labels['typekeypair'] = 'atslēgu pāris';
$labels['keyattfound'] = 'Šis e-pasts satur pievienotu PGP atslēgu(as);';
$labels['keyattimport'] = 'Importēt atslēgu(as).';
$labels['typesign'] = 'Paraksts';
$labels['typeencrypt'] = 'Šifrēt';
$labels['typecert'] = 'Sertifikāts';
$labels['typeauth'] = 'Autentifikācija';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritms';
$labels['subkeycreated'] = 'Izveidots';
$labels['subkeyexpires'] = 'Termiņš';
$labels['subkeyusage'] = 'Pielietojums';
$labels['expiresnever'] = 'nekad';
$labels['unknown'] = 'neiznāms';
$labels['uservalid'] = 'Derīgs';
$labels['userid'] = 'ID';
$labels['valid'] = 'derīgs';
$labels['supportencryption'] = 'Ieslēgt e-pastu šifrēšanu un parakstīšanu';
$labels['supportsignatures'] = 'Ieslēgt e-pastu parakstu pārbaudi';
$labels['supportdecryption'] = 'Ieslēgt e-pastu atšifrēšanu';
$labels['signdefault'] = 'Pēc noklusējuma parakstīt visus e-pastus';
$labels['encryptdefault'] = 'Pēc noklusējuma šifrēt visas vēstules';
$labels['attachpubkeydefault'] = 'Pēc noklusējuma pievienot manu publisko PGP atslēgu';
$labels['passwordtime'] = 'Saglabāt privāto atslēgu paroles';
$labels['nminutes'] = '$m minūte(s)';
$labels['wholesession'] = 'visai sessijai';
$labels['createkeys'] = 'Izveidot jaunu atslēgu pāri';
$labels['importkeys'] = 'Importēt atslēgu(as).';
$labels['exportkeys'] = 'Eksportēt atslēgu(as)';
$labels['keyactions'] = 'Darbības ar atslēgu ...';
$labels['keyremove'] = 'Dzēst';
$labels['keydisable'] = 'Atspējot';
$labels['keyrevoke'] = 'Atsaukt';
$labels['keysend'] = 'Ar e-pastu sūtīt arī publisko atslēgu';
$labels['keychpass'] = 'Mainīt paroli';
$labels['newkeyident'] = 'Identitāte:';
$labels['newkeypass'] = 'Parole:';
$labels['newkeypassconfirm'] = 'Apstiprināt paroli:';
$labels['newkeysize'] = 'Atslēgas stiprums:';
$labels['key2048'] = '2048 biti - pec noklusējuma';
$labels['key4096'] = '4096 biti - papildus drošība';
$labels['keygenerating'] = 'Ģenerē atslēgas...';
$labels['encryptionoptions'] = 'Šifrēšanas iestatījumi...';
$labels['encryptmsg'] = 'Šifrēt šo e-pastu';
$labels['signmsg'] = 'Digitāli parakstīt šo e-pastu';
$labels['enterkeypasstitle'] = 'Ievadiet atslēgas paroli';
$labels['enterkeypass'] = 'Lai atbloķētu lietotāja $user šifrēto atslēgu ($keyid), ir nepieciešama parole.';
$labels['arialabelkeyexportoptions'] = 'Atslēgu eksportēšanas iestatījumi';
$labels['attachpubkeymsg'] = 'Pievienot manu publisko atslēgu';
$messages['sigvalid'] = 'Pārbaudīts $sender paraksts.';
$messages['sigvalidpartial'] = 'Pārbaudīts $sender paraksts, bet daļa no e-pasta nav parakstīta.';
$messages['siginvalid'] = 'Nederīgs paraksts no $sender.';
$messages['sigunverified'] = 'Nepārbaudīts paraksts. Sertifikāts nav pārbaudīts. Sertifikāta ID: $keyid.';
$messages['signokey'] = 'Nepārbaudīts paraksts. Publiskā atslēga nav atrasta. Atslēgas ID: $keyid.';
$messages['sigerror'] = 'Nepārbaudīts paraksts. Iekšējā kļūda.';
$messages['decryptok'] = 'E-pasts atšifrēts.';
$messages['decrypterror'] = 'Atšifrēšana neizdevās.';
$messages['decryptnokey'] = 'Atšifrēšana neizdevās. Privātā atslēga netika atrasta. Atslēgas ID: $keyid.';
$messages['decryptnopass'] = 'Atšifrēšana neizdevās. Nepieciešama atslēgas parole.';
$messages['decryptpartial'] = 'E-pasts ir atšifrēts, bet daļa no tā nemaz nebija šifrēta.';
$messages['signerror'] = 'Sūtīšana neizdevās.';
$messages['signnokey'] = 'Nosūtīt neizdevās. Nav atrasta privātā atslēga.';
$messages['signnopass'] = 'Nosūtīt neizdevās. Ir nepieciešama atslēgas parole.';
$messages['encrypterror'] = 'Šifrēšana neizdevās.';
$messages['encryptnokey'] = 'Šifrēšana neizdevās. Epastam $email netika atrasta publiskā atslēga.';
$messages['nokeysfound'] = 'Nav atrasta neviena atslēga';
$messages['keyopenerror'] = 'Nav iespējams iegūt atslēgas informāciju! Iekšējā kļūda.';
$messages['keylisterror'] = 'Nav iespējams parādīt atslēgu sarakstu! Iekšējā kļūda.';
$messages['keysimportfailed'] = 'Nav iespējams ieimportēt atslēgu(s)! Iekšējā kļūda.';
$messages['keysimportsuccess'] = 'Atslēgas ieimportētas veiksmīgi. Ieimportēts: $new, nemainīts: $old.';
$messages['keyremoving'] = 'Dzēš atslēgu(as)...';
$messages['keyremoveconfirm'] = 'Vai Jūs esat pārliecināti ka gribat dzēst atzīmētās astlēgas?';
$messages['keyremovesuccess'] = 'Atslēga(s) nodzēsta veiksmīgi.';
$messages['keyremoveerror'] = 'Atzīmēto atslēgu(as) neizdevās nodzēst.';
$messages['keyimporttext'] = 'Jūs varat importēt privātās un publiskās atslēgas vai anulēšanas parakstus ASCII-Armor formātā.';
$messages['formerror'] = 'Lūdzu aipildiet formu. Visi lauki ir obligāti!';
$messages['passwordsdiffer'] = 'Paroles nesakrīt!';
$messages['keygenerateerror'] = 'Neizdevās uzģenerēt atslēgu pāri.';
$messages['keygeneratesuccess'] = 'Atslēgu pāris veiksmīgi uzģenerēts un ieimportēts.';
$messages['keygennosupport'] = 'Nav iespējams uzģenerēt atslēgu pāri, jo Jūsu tīmekļa pārlūks neatbalsta kriptogrāfijas iespējas!';
$messages['nonameident'] = 'Identitātei jābūt ar definētu lietotāja vārdu!';
?>
