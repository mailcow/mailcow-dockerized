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
$labels['encryption'] = 'Şifreleme';
$labels['enigmacerts'] = 'S/MIME Sertifikaları';
$labels['enigmakeys'] = 'PGP Anahtarları';
$labels['keysfromto'] = 'Anahtarlar $from - $to / $count';
$labels['keyname'] = 'Ad';
$labels['keyid'] = 'Anahtar Kodu';
$labels['keyuserid'] = 'Kullanıcı Kodu';
$labels['keytype'] = 'Anahtar türü';
$labels['fingerprint'] = 'Parmak izi';
$labels['subkeys'] = 'Alt anahtarlar';
$labels['basicinfo'] = 'Temel Bilgiler';
$labels['userids'] = 'Ek Kullanıcılar';
$labels['typepublickey'] = 'Genel Anahtar';
$labels['typekeypair'] = 'Anahtar çifti';
$labels['keyattfound'] = 'İleti ekinde PGP anahtarları var.';
$labels['keyattimport'] = 'Anahtar(lar)ı içe aktar.';
$labels['typesign'] = 'İmza';
$labels['typeencrypt'] = 'Şifrele';
$labels['typecert'] = 'Belgele';
$labels['typeauth'] = 'Kimlik Doğrulaması';
$labels['subkeyid'] = 'Kod';
$labels['subkeyalgo'] = 'Algoritma';
$labels['subkeycreated'] = 'Oluşturuldu';
$labels['subkeyexpires'] = 'Geçerlilik süresi';
$labels['subkeyusage'] = 'Kullanım';
$labels['expiresnever'] = 'asla';
$labels['unknown'] = 'bilinmeyen';
$labels['uservalid'] = 'Geçerli';
$labels['userid'] = 'Kod';
$labels['valid'] = 'geçerli';
$labels['supportencryption'] = 'İletiler şifrelensin ve imzalansın';
$labels['supportsignatures'] = 'İleti imzaları doğrulansın';
$labels['supportdecryption'] = 'İleti şifreleri çözülsün';
$labels['signdefault'] = 'Varsayılan olarak tüm iletiler imzalansın';
$labels['encryptdefault'] = 'Varsayılan olarak tüm iletiler şifrelensin';
$labels['attachpubkeydefault'] = 'Varsayılan olarak PGP anahtarı eklensin';
$labels['passwordtime'] = 'Parolalar için özel anahtarları tut';
$labels['nminutes'] = '$m dakika';
$labels['wholesession'] = 'tüm oturum';
$labels['createkeys'] = 'Yeni anahtar çifti oluştur';
$labels['importkeys'] = 'Anahtar(lar)ı içe aktar';
$labels['exportkeys'] = 'Anahtar(lar)ı dışa aktar';
$labels['keyactions'] = 'Anahtar işlemler...';
$labels['keyremove'] = 'Kaldır';
$labels['keydisable'] = 'Devre dışı';
$labels['keyrevoke'] = 'Geri al';
$labels['keysend'] = 'İletiyle birlikte ortak anahtar gönderilsin';
$labels['keychpass'] = 'Parolayı değiştir';
$labels['newkeyident'] = 'Kimlik:';
$labels['newkeypass'] = 'Parola:';
$labels['newkeypassconfirm'] = 'Parola Onayı:';
$labels['newkeysize'] = 'Anahtar uzunluğu:';
$labels['key2048'] = '2048 bit-Varsayılan';
$labels['key4096'] = '4096 bit- daha güvenli';
$labels['keygenerating'] = 'Anahtarlar oluştur...';
$labels['encryptionoptions'] = 'Şifreleme ayarları...';
$labels['encryptmsg'] = 'Bu iletiyi şifrele';
$labels['signmsg'] = 'İletiyi dijital imzala';
$labels['enterkeypasstitle'] = 'Anahtar sözcüğünüzü yazın';
$labels['enterkeypass'] = 'Gizli anahtarı ($keyid) açmak için $user kullanıcısının anahtar sözcüğü gerekli.';
$labels['arialabelkeyexportoptions'] = 'Anahtar dışa aktarma ayarları';
$labels['attachpubkeymsg'] = 'Genel anahtarımı ekle';
$labels['keyexportprompt'] = 'OpenPGP anahtar dosyasına gizli anahtarları da  kaydetmek istiyor musunuz?';
$labels['onlypubkeys'] = 'Yalnız Genel Anahtarları Dışa Aktar';
$labels['withprivkeys'] = 'Gizli Anahtarları Dışa Aktar';
$labels['findkey'] = 'Anahtarı sunucu(lar)da ara';
$labels['keyimportlabel'] = 'Dosyadan içe aktar';
$labels['keyimportsearchlabel'] = 'Anahtarı sunucu(lar)da ara';
$messages['sigvalid'] = '$sender imzası geçerli.';
$messages['sigvalidpartial'] = '$sender imzası doğrulandı, ancak metnin tümü imzalanmamış.';
$messages['siginvalid'] = '$sender imzası geçersiz.';
$messages['sigunverified'] = 'İmza doğrulanmamış. Sertifika doğrulanmamış. Sertifika Kodu: $keyid.';
$messages['signokey'] = 'İmza doğrulanmamış. Genel anahtar bulunamadı. Anahtar Kodu: $keyid.';
$messages['sigerror'] = 'Sorun var! İmza doğrulanmamış.';
$messages['decryptok'] = 'İleti şifresi çözüldü.';
$messages['decrypterror'] = 'Şifre çözülemedi.';
$messages['decryptnokey'] = 'Şifre çözülemedi. Özel anahtar bulunamadı. Anahtar Kodu: $keyid.';
$messages['decryptbadpass'] = 'Şifre çözülemedi. Parola geçersiz.';
$messages['decryptnopass'] = 'Şifre çözülemedi. Anahtar parola gerekli.';
$messages['decryptpartial'] = 'İletinin şifresi çözüldü, fakat metnin tümü şifrelenmemiş.';
$messages['signerror'] = 'İmzalama geçersiz.';
$messages['signnokey'] = 'İmzalanamadı. Özel anahtar bulunamadı.';
$messages['signbadpass'] = 'İmzalanamadı. Parola geçersiz.';
$messages['signnopass'] = 'İmzalanamadı. Anahtar parola gerekli.';
$messages['encrypterror'] = 'Şifrelenemedi.';
$messages['encryptnokey'] = 'Şifrelenemedi. Genel anahtar $email içinde bulunamadı.';
$messages['nokeysfound'] = 'Anahtarlar bulunamadı';
$messages['keynotfound'] = 'Anahtar buunamadı!';
$messages['keyopenerror'] = 'Sorun var! Anahtar bilgileri alınamıyor.';
$messages['keylisterror'] = 'Sorun var! Anahtar listesi yok.';
$messages['keysimportfailed'] = 'Sorun var! Anahtar(lar) aktarılamıyor.';
$messages['keysimportsuccess'] = 'Anahtar(lar) içe aktarıldı. İçe Aktarılan: $new, Değişmeyen: $old.';
$messages['keyremoving'] = 'Anahtar(lar) kaldırılıyor...';
$messages['keyremoveconfirm'] = 'Seçilmiş anahtar(lar)ı silmek istediğinize emin misiniz?';
$messages['keyremovesuccess'] = 'Anahtar(lar) silindi';
$messages['keyremoveerror'] = 'Anahtar(lar) silinemedi.';
$messages['keyimporttext'] = 'Özel ve genel anahtar(lar)ı içe aktarabilir ya da ASCII-Armor biçimindeki imzanızı geri çekebilirsiniz.';
$messages['keyimportsearchtext'] = 'Genel anahtarları, anahtar tanımlayıcı, e-posta ya da kullanıcı adına göre arayarak doğrudan içe aktarabilirsiniz.';
$messages['formerror'] = 'Formun doldurun. Tüm alanlar zorunludur!';
$messages['passwordsdiffer'] = 'Parola ile onayı aynı değil!';
$messages['keygenerateerror'] = 'Anahtar çifti oluşturulamadı.';
$messages['keygeneratesuccess'] = 'Anahtar çiftiniz oluşturuldu ve içe aktarıldı.';
$messages['keygennosupport'] = 'Tarayıcınız şifrelemeyi desteklemiyor. Anahtar çifti oluşturulamıyor.';
$messages['noidentselected'] = 'Anahtar için en az bir kimlik seçmelisiniz!';
$messages['nonameident'] = 'Kimlik için bir kullanıcı adı belirtmelisiniz!';
?>
