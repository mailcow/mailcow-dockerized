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
$labels['encryption'] = 'Шифрування';
$labels['enigmacerts'] = 'Сертифікати S/MIME';
$labels['enigmakeys'] = 'PGP-ключі';
$labels['keysfromto'] = 'Ключі з $from до $to з $count';
$labels['keyname'] = 'Ім\'я';
$labels['keyid'] = 'ID ключа';
$labels['keyuserid'] = 'ID користувача';
$labels['keytype'] = 'Тип ключа';
$labels['fingerprint'] = 'Відбиток';
$labels['subkeys'] = 'Субключі';
$labels['basicinfo'] = 'Базова інформація';
$labels['userids'] = 'Додаткові користувачі';
$labels['typepublickey'] = 'публічний ключ';
$labels['typekeypair'] = 'пара ключів';
$labels['keyattfound'] = 'Це повідомлення містить прикріплений PGP-ключ(і).';
$labels['keyattimport'] = 'Імпортувати ключ(і)';
$labels['typesign'] = 'Підписати';
$labels['typeencrypt'] = 'Шифрувати';
$labels['typecert'] = 'Сертифікувати';
$labels['typeauth'] = 'Автентифікація';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Алгоритм';
$labels['subkeycreated'] = 'Створено';
$labels['subkeyexpires'] = 'Закінчується';
$labels['subkeyusage'] = 'Використання';
$labels['expiresnever'] = 'ніколи';
$labels['unknown'] = 'невідомо';
$labels['uservalid'] = 'Дійсний';
$labels['userid'] = 'ID';
$labels['valid'] = 'дійсний';
$labels['supportencryption'] = 'Увімкнути шифрування і підписання повідомлень';
$labels['supportsignatures'] = 'Увімкнути верифікацію підписів повідомленьй';
$labels['supportdecryption'] = 'Увімкнути розшифрування повідомлень';
$labels['signdefault'] = 'Підписувати усі листи за замовчуванням';
$labels['encryptdefault'] = 'Шифрувати усі повідомлення за замовчуванням';
$labels['attachpubkeydefault'] = 'Приєднати мій публічний PGP ключ по замовчуванню';
$labels['passwordtime'] = 'Зберігати паролі приватних ключів';
$labels['nminutes'] = '$n хвилин(и)';
$labels['wholesession'] = 'усю сесію';
$labels['createkeys'] = 'Створити нову пару ключів';
$labels['importkeys'] = 'Імпортувати ключ(і)';
$labels['exportkeys'] = 'Експортувати ключ(і)';
$labels['keyactions'] = 'Ключові дії...';
$labels['keyremove'] = 'Вилучити';
$labels['keydisable'] = 'Вимкнути';
$labels['keyrevoke'] = 'Відкликати';
$labels['keysend'] = 'Надіслати публічний ключ в повідомленні';
$labels['keychpass'] = 'Змінити пароль';
$labels['newkeyident'] = 'Профіль:';
$labels['newkeypass'] = 'Пароль:';
$labels['newkeypassconfirm'] = 'Підтвердіть пароль:';
$labels['newkeysize'] = 'Розмір ключа:';
$labels['key2048'] = '2048 бітів — дефолтно';
$labels['key4096'] = '4096 бітів — безпечніше';
$labels['keygenerating'] = 'Генерування ключів…';
$labels['encryptionoptions'] = 'Опції шифрування…';
$labels['encryptmsg'] = 'Шифрувати це повідомлення';
$labels['signmsg'] = 'Поставити цифровий підпис під повідомленням';
$labels['enterkeypasstitle'] = 'Введіть фразу-пароль ключа';
$labels['enterkeypass'] = 'Необхідна фраза-пароль для відімкнення секретного ключа ($keyid) для користувача: $user.';
$labels['arialabelkeyexportoptions'] = 'Опції експорту ключів';
$labels['attachpubkeymsg'] = 'Приєднати мій публічний ключ';
$labels['keyexportprompt'] = 'Бажаєте включити приватні ключі у файл-ключів OpenPGP?';
$labels['onlypubkeys'] = 'Експортувати публічні ключі';
$labels['withprivkeys'] = 'Експортувати приватні ключі';
$labels['findkey'] = 'Пошук по ключовому(вих) серверах';
$labels['keyimportlabel'] = 'Імпортувати з файлу';
$labels['keyimportsearchlabel'] = 'Пошук по ключовому(вих) серверах';
$messages['sigvalid'] = 'Перевірений підпис від $sender.';
$messages['sigvalidpartial'] = 'Перевірений підпис від $sender, але частина тіла повідомлення не було підписано.';
$messages['siginvalid'] = 'Недійсний підпис від $sender.';
$messages['sigunverified'] = 'Неперевірений підпис. Сертифікат не перевірено. ID сертифіката: $keyid.';
$messages['signokey'] = 'Неперевірений підпис. Публічний ключ не знайдено. ID ключа: $keyid.';
$messages['sigerror'] = 'Неперевірений підпис. Внутрішня помилка.';
$messages['decryptok'] = 'Повідомлення розшифровано.';
$messages['decrypterror'] = 'Розшифрування не вдалося.';
$messages['decryptnokey'] = 'Розшифрування не вдалося. Приватний ключ не знайдено. ID ключа: $keyid.';
$messages['decryptnopass'] = 'Розшифрування не вдалося. Потрібен пароль ключа.';
$messages['decryptpartial'] = 'Повідомлення розшифроване, але частина тіла не було зашифроване.';
$messages['signerror'] = 'Підписання не вдалося.';
$messages['signnokey'] = 'Підписання не вдалося. Приватний ключ не знайдено.';
$messages['signnopass'] = 'Підписання не вдалося. Потрібен пароль ключа.';
$messages['encrypterror'] = 'Шифрування не вдалося. ';
$messages['encryptnokey'] = 'Шифрування не вдалося. Публічний ключ не знайдено для $email.';
$messages['nokeysfound'] = 'Не знайдено ключів';
$messages['keynotfound'] = 'Ключ не знайдено!';
$messages['keyopenerror'] = 'Не вдалося отримати інформацію про ключ! Внутрішня помилка.';
$messages['keylisterror'] = 'Не вдалося вивести перелік ключів! Внутрішня помилка.';
$messages['keysimportfailed'] = 'Не вдалося імпортувати ключ(і)! Внутрішня помилка.';
$messages['keysimportsuccess'] = 'Ключ(і) імпотровано успішно. Імпортовано: $new, без змін: $old.';
$messages['keyremoving'] = 'Вилучення ключа(-ів)…';
$messages['keyremoveconfirm'] = 'Ви дійсно хочете видалити обраний(-і) ключ(і)?';
$messages['keyremovesuccess'] = 'Ключ(і) вилучено успішно';
$messages['keyremoveerror'] = 'Не вдалося вилучити обраний(-і) ключ(і).';
$messages['keyimporttext'] = 'Ви можете імпортувати приватні та публічні ключі чи підписи скасування у форматі ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Ви можете здійснювати пошук відкритих ключів за допомогою ідентифікатора ключа, ім\'я користувача або адреси електронної пошти, а потім імпортувати їх.';
$messages['formerror'] = 'Будь ласка, заповніть форму. Усі поля обов\'язкові!';
$messages['passwordsdiffer'] = 'Паролі не співпадають!';
$messages['keygenerateerror'] = 'Не вдалося згенерувати пару ключів';
$messages['keygeneratesuccess'] = 'Пару ключів успішно згенеровано й імпортовано.';
$messages['keygennosupport'] = 'Ваш веб-браузер не підтримує криптографію. Не вдалося згенерувати пару ключів!';
$messages['noidentselected'] = 'Ви повинні вибрати принаймні, один ідентифікатор для ключа!';
$messages['nonameident'] = 'Профіль повинен мати вказане ім\'я користувача!';
?>
