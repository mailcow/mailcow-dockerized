<?php

/*
 +-----------------------------------------------------------------------+
 | plugins/password/localization/<lang>.inc                              |
 |                                                                       |
 | Localization file of the Roundcube Webmail Password plugin            |
 | Copyright (C) 2012-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/plugin-password/
*/
$labels['changepasswd']  = 'Змінити пароль';
$labels['curpasswd']  = 'Поточний пароль:';
$labels['newpasswd']  = 'Новий пароль:';
$labels['confpasswd']  = 'Підтвердіть новий пароль:';
$messages['nopassword'] = 'Будь ласка, введіть новий пароль.';
$messages['nocurpassword'] = 'Будь ласка, введіть поточний пароль.';
$messages['passwordincorrect'] = 'Поточний пароль неправильний.';
$messages['passwordinconsistency'] = 'Паролі не збігаються, спробуйте ще раз.';
$messages['crypterror'] = 'Не вдалося зберегти новий пароль. Функція шифрування відсутня.';
$messages['connecterror'] = 'Не вдалося зберегти новий пароль. Помилка з\'єднання.';
$messages['internalerror'] = 'Не вдалося зберегти новий пароль.';
$messages['passwordshort'] = 'Пароль повинен бути не менше $length символів.';
$messages['passwordweak'] = 'Пароль повинен містити як мінімум одну цифру і один розділовий знак.';
$messages['passwordforbidden'] = 'Пароль містить заборонені символи.';
$messages['firstloginchange'] = 'Це Ваше перше входження в систему. Змініть, будь ласка, свій пароль.';
$messages['disablednotice'] = 'Система зараз в режимі обслуговування і змінити пароль наразі неможливо. Невдовзі все має вернутися в норму. Просимо вибачення за будь-які незручності.';
$messages['passwinhistory'] = 'Цей пароль вже використовувався раніше.';
$messages['samepasswd'] = 'Новий пароль повинен відрізнятися від старого.';
$messages['passwdexpirewarning'] = 'УВАГА! У вашого пароля закінчується термін, змінити його як омога скоріше до $expirationdatetime.';
$messages['passwdexpired'] = 'Ваш пароль закінчився, ви повинні змінити це зараз!';
