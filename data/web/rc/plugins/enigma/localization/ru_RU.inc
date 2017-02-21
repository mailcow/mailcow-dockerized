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
$labels['encryption'] = 'Шифрование';
$labels['enigmacerts'] = 'Сертификаты S/MIME';
$labels['enigmakeys'] = 'Ключи PGP';
$labels['keysfromto'] = 'Ключи с $from по $to из $count';
$labels['keyname'] = 'Имя';
$labels['keyid'] = 'ID ключа';
$labels['keyuserid'] = 'ID пользователя';
$labels['keytype'] = 'Тип ключа';
$labels['fingerprint'] = 'Отпечаток';
$labels['subkeys'] = 'Подключи';
$labels['basicinfo'] = 'Базовая информация';
$labels['userids'] = 'Дополнительные ключи';
$labels['typepublickey'] = 'открытый ключ';
$labels['typekeypair'] = 'пара ключей';
$labels['keyattfound'] = 'Это сообщение содержит прикреплённый(е) PGP ключ(и).';
$labels['keyattimport'] = 'Импортировать ключ(и)';
$labels['typesign'] = 'Подписать';
$labels['typeencrypt'] = 'Зашифровать';
$labels['typecert'] = 'Удостоверить';
$labels['typeauth'] = 'Аутентификация';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Алгоритм';
$labels['subkeycreated'] = 'Создан';
$labels['subkeyexpires'] = 'Срок действия';
$labels['subkeyusage'] = 'Использование';
$labels['expiresnever'] = 'никогда';
$labels['unknown'] = 'неизвестно';
$labels['uservalid'] = 'Действительный';
$labels['userid'] = 'ID';
$labels['valid'] = 'действительный';
$labels['supportencryption'] = 'Включить шифрование и подписание сообщений';
$labels['supportsignatures'] = 'Включить проверку подписанных сообщений';
$labels['supportdecryption'] = 'Включить расшифровывание сообщений';
$labels['signdefault'] = 'Подписывать все сообщения по умолчанию';
$labels['encryptdefault'] = 'Шифровать все сообщения по умолчанию';
$labels['attachpubkeydefault'] = 'Прикрепить мой открытый ключ по умолчанию';
$labels['passwordtime'] = 'Хранить пароль закрытого ключа';
$labels['nminutes'] = '$m минут(ы)';
$labels['wholesession'] = 'весь сеанс';
$labels['createkeys'] = 'Создать новую пару ключей';
$labels['importkeys'] = 'Импортировать ключ(и)';
$labels['exportkeys'] = 'Экспортировать ключ(и)';
$labels['keyactions'] = 'Действия с ключами...';
$labels['keyremove'] = 'Удалить';
$labels['keydisable'] = 'Отключить';
$labels['keyrevoke'] = 'Отозвать';
$labels['keysend'] = 'Отправить открытый ключ в сообщении';
$labels['keychpass'] = 'Изменить пароль';
$labels['newkeyident'] = 'Профиль:';
$labels['newkeypass'] = 'Пароль:';
$labels['newkeypassconfirm'] = 'Подтвердите пароль:';
$labels['newkeysize'] = 'Размер ключа:';
$labels['key2048'] = '2048 бит - по умолчанию';
$labels['key4096'] = '4096 бит - более безопасный';
$labels['keygenerating'] = 'Создание ключей...';
$labels['encryptionoptions'] = 'Настройки шифрования...';
$labels['encryptmsg'] = 'Зашифровать это сообщение';
$labels['signmsg'] = 'Подписать это сообщение';
$labels['enterkeypasstitle'] = 'Введите фразу-пароль ключа';
$labels['enterkeypass'] = 'Фраза-пароль нужна для разблокировки секретного ключа ($keyid) пользователя: $user.';
$labels['arialabelkeyexportoptions'] = 'Параметры экспорта ключей';
$labels['attachpubkeymsg'] = 'Прикрепить мой открытый ключ';
$labels['keyexportprompt'] = 'Вы хотите включить закрытые ключи в файл сохранения ключей OpenPGP?';
$labels['onlypubkeys'] = 'Экспортировать только открытые ключи';
$labels['withprivkeys'] = 'Экспортировать закрытые ключи';
$labels['findkey'] = 'Поиск на сервере(-ах) ключей';
$labels['keyimportlabel'] = 'Импортировать из файла';
$labels['keyimportsearchlabel'] = 'Поиск на сервере(-ах) ключей';
$messages['sigvalid'] = 'Проверенная подпись $sender.';
$messages['sigvalidpartial'] = 'Проверенная подпись $sender, но часть сообщения не подписана.';
$messages['siginvalid'] = 'Недействительная подпись $sender.';
$messages['sigunverified'] = 'Непроверенная подпись. Сертификат не проверен. ID сертификата: $keyid.';
$messages['signokey'] = 'Непроверенная подпись. Открытый ключ не найден. ID ключа: $keyid.';
$messages['sigerror'] = 'Непроверенная подпись. Внутренняя ошибка.';
$messages['decryptok'] = 'Сообщение расшифровано.';
$messages['decrypterror'] = 'Сбой расшифровки.';
$messages['decryptnokey'] = 'Сбой расшифровки. Закрытый ключ не найден. ID ключа: $keyid.';
$messages['decryptnopass'] = 'Сбой расшифровки. Требуется ключ пароля.';
$messages['decryptpartial'] = 'Сообщение расшифровано, но часть сообщения не была зашифрована.';
$messages['signerror'] = 'Сбой подписания.';
$messages['signnokey'] = 'Сбой подписания. Закрытый ключ не найден.';
$messages['signnopass'] = 'Сбой подписания. Требуется ключ пароля.';
$messages['encrypterror'] = 'Сбой шифрования.';
$messages['encryptnokey'] = 'Сбой шифрования. Не найден открытый ключ для $email.';
$messages['nokeysfound'] = 'Ключи не найдены';
$messages['keynotfound'] = 'Ключ не найден!';
$messages['keyopenerror'] = 'Невозможно получить информацию о ключе! Внутренняя ошибка.';
$messages['keylisterror'] = 'Невозможно составить список ключей! Внутренняя ошибка.';
$messages['keysimportfailed'] = 'Невозможно импортировать ключ(и)! Внутренняя ошибка.';
$messages['keysimportsuccess'] = 'Ключ(и) успешно импортирован(ы) . Импортировано: $new, без изменений: $old.';
$messages['keyremoving'] = 'Удаление ключа(ей)...';
$messages['keyremoveconfirm'] = 'Вы уверенны, что хотите удалить выделенный(е) ключ(и)?';
$messages['keyremovesuccess'] = 'Ключ(и) успешно удален(ы)';
$messages['keyremoveerror'] = 'Невозможно удалить выделенный(е) ключ(и).';
$messages['keyimporttext'] = 'Вы можете импортировать закрытый и открытый ключ(и) или подписи отзыва в формате ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Вы можете искать открытые ключи по идентификатору ключа, имени пользователя или адресу электронной почты, а затем импортировать их напрямую.';
$messages['formerror'] = 'Заполните форму. Все поля обязательны!';
$messages['passwordsdiffer'] = 'Пароли не совпадают!';
$messages['keygenerateerror'] = 'Не удалось создать пару ключей';
$messages['keygeneratesuccess'] = 'Пара ключей создана и успешно импортирована.';
$messages['keygennosupport'] = 'Ваш браузер не поддерживает шифрование. Невозможно создать пару ключей!';
$messages['noidentselected'] = 'Вы должны выбрать по крайней мере один профиль для ключа!';
$messages['nonameident'] = 'У профиля должно быть определено имя пользователя!';
?>
