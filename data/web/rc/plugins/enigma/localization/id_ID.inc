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
$labels['encryption'] = 'Enkripsi';
$labels['enigmacerts'] = 'Sertifikat S/MIME';
$labels['enigmakeys'] = 'Kunci PGP';
$labels['keysfromto'] = 'Kunci $from sampai $to dari $count';
$labels['keyname'] = 'Nama';
$labels['keyid'] = 'ID Kunci';
$labels['keyuserid'] = 'ID Pengguna';
$labels['keytype'] = 'Tipe kunci';
$labels['fingerprint'] = 'Sidik jari';
$labels['subkeys'] = 'Subkunci';
$labels['basicinfo'] = 'Informasi dasar';
$labels['userids'] = 'Pengguna Tambahan';
$labels['typepublickey'] = 'kunci publik';
$labels['typekeypair'] = 'kunci pasangan';
$labels['keyattfound'] = 'Pesan ini memuat kunci PGP yang dilampirkan.';
$labels['keyattimport'] = 'Impor kunci';
$labels['typesign'] = 'Tandatangani';
$labels['typeencrypt'] = 'Enkripsi';
$labels['typecert'] = 'Sertifikasikan';
$labels['typeauth'] = 'Otentifikasi';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritma';
$labels['subkeycreated'] = 'Dibuat';
$labels['subkeyexpires'] = 'Kedaluarsa';
$labels['subkeyusage'] = 'Penggunaan';
$labels['expiresnever'] = 'tidak pernah';
$labels['unknown'] = 'tak diketahui';
$labels['uservalid'] = 'Valid';
$labels['userid'] = 'ID';
$labels['valid'] = 'valid';
$labels['supportencryption'] = 'Fungsikan enkripsi dan penandatanganan pesan';
$labels['supportsignatures'] = 'Fungsikan verifikasi tanda tangan pesan';
$labels['supportdecryption'] = 'Fungsikan dekripsi pesan';
$labels['signdefault'] = 'Tandatangani semua pesan secara baku';
$labels['encryptdefault'] = 'Enkripsikan semua pesan secara baku';
$labels['attachpubkeydefault'] = 'Lampirkan kunci PGP publik saya secara baku';
$labels['passwordtime'] = 'Simpan kata sandi kunci rahasia selama';
$labels['nminutes'] = '$m menit';
$labels['wholesession'] = 'seluruh sesi';
$labels['createkeys'] = 'Buat pasangan kunci baru';
$labels['importkeys'] = 'Impor kunci';
$labels['exportkeys'] = 'Ekspor kunci';
$labels['keyactions'] = 'Aksi kunci...';
$labels['keyremove'] = 'Hapus';
$labels['keydisable'] = 'Nonaktifkan';
$labels['keyrevoke'] = 'Cabut';
$labels['keysend'] = 'Kirim kunci publik dalam pesan';
$labels['keychpass'] = 'Ubah kata sandi';
$labels['newkeyident'] = 'Identitas:';
$labels['newkeypass'] = 'Kata sandi:';
$labels['newkeypassconfirm'] = 'Konfirmasi kata sandi:';
$labels['newkeysize'] = 'Ukuran kunci:';
$labels['key2048'] = '2048 bit - baku';
$labels['key4096'] = '4096 bit - lebih aman';
$labels['keygenerating'] = 'Membuat kunci...';
$labels['encryptionoptions'] = 'Opsi enkripsi...';
$labels['encryptmsg'] = 'Enkripsikan pesan ini';
$labels['signmsg'] = 'Tandatantani pesan ini';
$labels['enterkeypasstitle'] = 'Masukkan frasa sandi kunci';
$labels['enterkeypass'] = 'Frasa sandi diperlukan untuk membuka kunci rahasia ($keyid) untuk pengguna: $user.';
$labels['arialabelkeyexportoptions'] = 'Opsi ekspor kunci';
$labels['attachpubkeymsg'] = 'Lampirkan kunci publik saya';
$labels['keyexportprompt'] = 'Apakah Anda ingin menyertakan kunci rahasia dalam berkas kunci OpenPGP yang disimpan?';
$labels['onlypubkeys'] = 'Ekspor Kunci Publik Saja';
$labels['withprivkeys'] = 'Ekspor Kunci Rahasia';
$labels['findkey'] = 'Cari pada server kunci';
$labels['keyimportlabel'] = 'Impor dari berkas';
$labels['keyimportsearchlabel'] = 'Cari pada server kunci';
$messages['sigvalid'] = 'Tanda tangan terverifikasi dari $sender.';
$messages['sigvalidpartial'] = 'Tanda tangan terverifikasi dari $sender, tapi sebagian pesan tidak ditandatangani.';
$messages['siginvalid'] = 'Tanda tangan tak valid dari $sender.';
$messages['sigunverified'] = 'Tanda tangan tak terverifikasi. Sertifikat tak terverifikasi. ID sertifikat: $keyid.';
$messages['signokey'] = 'Tanda tangan tak terverifikasi. Kunci publik tak ditemukan. ID kunci: $keyid.';
$messages['sigerror'] = 'Tanda tangan tak terverifikasi. Kesalahan internal.';
$messages['decryptok'] = 'Pesan didekripsi.';
$messages['decrypterror'] = 'Dekripsi gagal.';
$messages['decryptnokey'] = 'Dekripsi gagal. Kunci privat tak ditemukan. ID kunci: $keyid.';
$messages['decryptnopass'] = 'Dekripsi gagal. Kata sandi kunci diperlukan.';
$messages['decryptpartial'] = 'Pesan didekripsi, tapi sebagian isi tidak dienkripsi.';
$messages['signerror'] = 'Penandatanganan gagal.';
$messages['signnokey'] = 'Penandatanganan gagal. Kunci privat tak ditemukan.';
$messages['signnopass'] = 'Penandatanganan gagal. Kata sandi kunci diperlukan.';
$messages['encrypterror'] = 'Enkripsi gagal.';
$messages['encryptnokey'] = 'Enkripsi gagal. Kunci publik tak ditemukan untuk $email.';
$messages['nokeysfound'] = 'Tidak ada kunci yang ditemukan';
$messages['keynotfound'] = 'Kunci tidak ditemukan!';
$messages['keyopenerror'] = 'Tidak bisa memperoleh informasi kunci! Kesalahan internal.';
$messages['keylisterror'] = 'Tidak bisa membuat daftar kunci! Kesalahan internal.';
$messages['keysimportfailed'] = 'Tidak bisa mengimpor kunci! Kesalahan internal.';
$messages['keysimportsuccess'] = 'Kunci sukses diimpor. Diimpor: $new, tidak berubah: $old.';
$messages['keyremoving'] = 'Menghapus kunci...';
$messages['keyremoveconfirm'] = 'Anda yakin hendak menghapus kunci yang dipilih?';
$messages['keyremovesuccess'] = 'Kunci sukses dihapus';
$messages['keyremoveerror'] = 'Tidak bisa menghapus kunci yang dipilih.';
$messages['keyimporttext'] = 'Anda bisa mengimpor kunci privat dan publik atau tanda tangan pencabutan dalam format ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Anda dapat mencari kunci publik berdasarkan identifier kunci, nama pengguna, atau alamat surel, lalu secara langsung mengimpor mereka.';
$messages['formerror'] = 'Harap isi formulir. Semua ruas diperlukan!';
$messages['passwordsdiffer'] = 'Kata sandi tidak cocok!';
$messages['keygenerateerror'] = 'Gagal membuat suatu pasangan kunci';
$messages['keygeneratesuccess'] = 'Suatu pasangan kunci dibuat dan sukses diimpor.';
$messages['keygennosupport'] = 'Peramban web Anda tidak mendukung kriptografi. Tidak bisa membuat suatu pasangan kunci!';
$messages['nonameident'] = 'Identitas mesti mengandung nama pengguna!';
?>
