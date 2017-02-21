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
$labels['changepasswd']  = 'Изменить пароль';
$labels['curpasswd']  = 'Текущий пароль:';
$labels['newpasswd']  = 'Новый пароль:';
$labels['confpasswd']  = 'Подтвердите новый пароль:';
$messages['nopassword'] = 'Пожалуйста, введите новый пароль.';
$messages['nocurpassword'] = 'Пожалуйста, введите текущий пароль.';
$messages['passwordincorrect'] = 'Текущий пароль неверен.';
$messages['passwordinconsistency'] = 'Пароли не совпадают, попробуйте ещё раз, пожалуйста.';
$messages['crypterror'] = 'Не могу сохранить новый пароль. Отсутствует криптографическая функция.';
$messages['connecterror'] = 'Не могу сохранить новый пароль. Ошибка соединения.';
$messages['internalerror'] = 'Не могу сохранить новый пароль.';
$messages['passwordshort'] = 'Длина пароля должна быть как минимум $length символов.';
$messages['passwordweak'] = 'Пароль должен включать в себя как минимум одну цифру и один знак пунктуации.';
$messages['passwordforbidden'] = 'Пароль содержит недопустимые символы.';
$messages['firstloginchange'] = 'Вы выполнили вход впервые. Измените ваш пароль.';
$messages['disablednotice'] = 'В настоящее время проводится обслуживание системы и изменение пароля не возможно в данный момент. Все должно вернуться в нормальное состояние в ближайшее время. Приносим свои извинения за причиненные неудобства.';
$messages['passwinhistory'] = 'Этот пароль уже использовался ранее.';
$messages['samepasswd'] = 'Новый пароль должен отличаться от старого.';
$messages['passwdexpirewarning'] = 'Внимание! Ваш пароль истекает в ближайшее время, измените его до $expirationdatetime.';
$messages['passwdexpired'] = 'Срок действия вашего пароля истек, вы должны изменить его сейчас!';
