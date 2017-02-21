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
$labels['encryption'] = 'Titkosítás';
$labels['enigmacerts'] = 'S/MIMI Tanusitványok';
$labels['enigmakeys'] = 'PGP Kulcsok';
$labels['keysfromto'] = 'Kulcsok $from - $to / $count ';
$labels['keyname'] = 'Név';
$labels['keyid'] = 'Kulcs ID';
$labels['keyuserid'] = 'Felhasználó ID';
$labels['keytype'] = 'Kulcs tipus';
$labels['fingerprint'] = 'Ujjlenyomat';
$labels['subkeys'] = 'Alkulcsok';
$labels['basicinfo'] = 'Alap információk';
$labels['userids'] = 'További felhasználok';
$labels['typepublickey'] = 'nyilvános kulcs';
$labels['typekeypair'] = 'kulcspár';
$labels['keyattfound'] = 'Ez az űzenet tartalmaz csatolt PGP kulcso(ka)t';
$labels['keyattimport'] = 'Kulcs(ok) importálása';
$labels['typesign'] = 'Aláírás';
$labels['typeencrypt'] = 'Titkosít';
$labels['typecert'] = 'Igazolni';
$labels['typeauth'] = 'Hitelesítés';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritmus';
$labels['subkeycreated'] = 'Létrehozva';
$labels['subkeyexpires'] = 'Lejár';
$labels['subkeyusage'] = 'Felhasználás';
$labels['expiresnever'] = 'soha';
$labels['unknown'] = 'ismeretlen';
$labels['uservalid'] = 'Érvényes';
$labels['userid'] = 'ID';
$labels['valid'] = 'érvényes';
$labels['supportencryption'] = 'Az üzenet titkosításának és aláíásának engedélyezése';
$labels['supportsignatures'] = 'Az űzenet álírásainak ellenörzésének engedélyezése';
$labels['supportdecryption'] = 'Az üzenet ki-titkosításának engedélyezése';
$labels['signdefault'] = 'Alapértelemzetten írja alá az üzeneteket';
$labels['encryptdefault'] = 'Titkosítsa az összes üzenetet alapértelemzetten';
$labels['attachpubkeydefault'] = 'Alapértelmezetten csatolja a publikus PGP kulcsomat.';
$labels['passwordtime'] = 'Jegyezze meg a privát kulcs jelszavát a következöhöz';
$labels['nminutes'] = '$m perc';
$labels['wholesession'] = 'az egész munkamenet';
$labels['createkeys'] = 'Új kulcs pár létrehozása';
$labels['importkeys'] = 'Kulcsok importálása';
$labels['exportkeys'] = 'Kulcs(ok) exportálása';
$labels['keyactions'] = 'Kulcs műveletek';
$labels['keyremove'] = 'Eltávolítás';
$labels['keydisable'] = 'Tilt';
$labels['keyrevoke'] = 'Visszavon';
$labels['keysend'] = 'Kűld el a nyilvános kulcsot az üzenetben';
$labels['keychpass'] = 'Jelszó módosítás';
$labels['newkeyident'] = 'Azonosító:';
$labels['newkeypass'] = 'Jelszó:';
$labels['newkeypassconfirm'] = 'Jelszó megerősítése';
$labels['newkeysize'] = 'Kulcs méret:';
$labels['key2048'] = '2048 bit - alapértelemzett';
$labels['key4096'] = '4096 bit - biztonságosabb';
$labels['keygenerating'] = 'Kulcsok generálása…';
$labels['encryptionoptions'] = 'Titkosítási beállítások...';
$labels['encryptmsg'] = 'Titkosítsa ezt az üzenetet';
$labels['signmsg'] = 'Ennek az üzenetnek  a digtális aláírása';
$labels['enterkeypasstitle'] = 'Írd be a jelmondatot';
$labels['enterkeypass'] = 'Jelmondat(passphrase) szükséges a titkos kulcs ($keyid)  felhasznlóhoz ($user) feloldásához. ';
$labels['arialabelkeyexportoptions'] = 'Kulcsok exportálásához opciók';
$labels['attachpubkeymsg'] = 'A nyilvános kulcsom csatolása';
$labels['keyexportprompt'] = 'A mentett OpenPGP kulcsoknál legyenek-e tárolva a titkos kulcsok is?';
$labels['onlypubkeys'] = 'Csak a nyilvános kulcsok exportálása';
$labels['withprivkeys'] = 'Titkos kucsok exportálása';
$labels['findkey'] = 'Keresés a kulcs szerver(ek)en';
$labels['keyimportlabel'] = 'Importálás fájlból';
$labels['keyimportsearchlabel'] = 'Keresés a kulcs szerver(ek)en';
$messages['sigvalid'] = 'A $sender feladó aláírása ellenőrízve.';
$messages['sigvalidpartial'] = 'A $sender feladó aláírása ellenőrízve, de részei az üzenetnek nem voltak aláírva.';
$messages['siginvalid'] = 'Érvénytelen aláírás  a következő feladótól: $sender';
$messages['sigunverified'] = 'Nem ellenörzött aláírás. A tanúsítvány nem ellenőrzött. Tanusítvány ID: $keyid.';
$messages['signokey'] = 'Nem ellenörzött aláírás. Nyilvános kulcs nem található. Kulcs ID: $keyid.';
$messages['sigerror'] = 'Nem ellenörzött aláírás. Belső hiba.';
$messages['decryptok'] = 'Üzenet ki-tikosítva.';
$messages['decrypterror'] = 'Visszafejtés sikertelen.';
$messages['decryptnokey'] = 'Visszafejtés sikertelen. Nyilvános nem található. Kulcs ID: $keyid.';
$messages['decryptbadpass'] = 'Visszafejtés sikertelen. Hibás jelszó.';
$messages['decryptnopass'] = 'Visszafejtés sikertelen. A kulcshoz jelszó kell.';
$messages['decryptpartial'] = 'Visszafejtés sikeres, de néhány része az üzenetnek nem volt titkosítva.';
$messages['signerror'] = 'Aláírás sikertelen.';
$messages['signnokey'] = 'Aláírás sikertelen. Nyilvános kulcs nem található.';
$messages['signbadpass'] = 'Aláírás sikertelen. Hibás jelszó.';
$messages['signnopass'] = 'Aláírás sikertelen. A kulcshoz jelszó kell.';
$messages['encrypterror'] = 'Titkosítás sikertelen.';
$messages['encryptnokey'] = 'Titkosítás sikertelen. Nyilvás kulcs nem található a következőhöz: $email.';
$messages['nokeysfound'] = 'Nem  találhatóak kulcsok';
$messages['keynotfound'] = 'A kulcs nem található!';
$messages['keyopenerror'] = 'A kulcs információk lekérésés nem lehetséges. Belső hiba.';
$messages['keylisterror'] = 'A kulcsok listázása nem sikerült. Belső hiba.';
$messages['keysimportfailed'] = 'A kulcs(ok) importálása sikertlelen. Belső hiba.';
$messages['keysimportsuccess'] = 'A kulcs(ok) importálása sikeres. Importálva: $new, változatlan: $old.';
$messages['keyremoving'] = 'Kulcs(ok) eltávolítása...';
$messages['keyremoveconfirm'] = 'Biztosan törölni szeretnéd a kiválasztott kulcso(ka)t?';
$messages['keyremovesuccess'] = 'A kulcs(ok) sikeresen törölve';
$messages['keyremoveerror'] = 'Nem sikerült a kiválasztott kulcso(ka)t törölni.';
$messages['keyimporttext'] = 'Importálhatod a privát és nyilvános kulcsodat vagy a visszavonási aláírásokatr ASCII-Armor formátumban.';
$messages['keyimportsearchtext'] = 'A publikus kulcsokat keresheted az azonsító, a felhasználó név vagy a email cím alapján, majd közvetlenül beimportálhatod.';
$messages['formerror'] = 'Tőltsd ki az ürlapot. Minden mező kitöltése kötelező!';
$messages['passwordsdiffer'] = 'A jelszavak nem egyeznek!';
$messages['keygenerateerror'] = 'Nem sikerült a kulcs-pár létrehozása';
$messages['keygeneratesuccess'] = 'A kulcs-pár létrejött és sikeresen beimportálódott.';
$messages['keygennosupport'] = 'A böngésződ nem támogatja a titkosítást. A kulcs-pár nem hozható létre!';
$messages['noidentselected'] = 'Ki kell választanod legalább egy identitást a kulcshoz!';
$messages['nonameident'] = 'Egy identitásnak kötelező felhasználó nevet beállítani!';
?>
