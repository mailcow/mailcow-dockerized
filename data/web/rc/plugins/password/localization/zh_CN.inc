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
$labels['changepasswd']  = '修改密码';
$labels['curpasswd']  = '当前密码：';
$labels['newpasswd']  = '新密码：';
$labels['confpasswd']  = '确认新密码：';
$messages['nopassword'] = '请输入新密码。';
$messages['nocurpassword'] = '请输入当前的密码。';
$messages['passwordincorrect'] = '当前密码不正确。';
$messages['passwordinconsistency'] = '两次输入的密码不一致，请重试。';
$messages['crypterror'] = '无法保存新密码，缺少加密功能。';
$messages['connecterror'] = '无法保存新密码，连接出错。';
$messages['internalerror'] = '无法保存新密码。';
$messages['passwordshort'] = '密码至少为 $length 位。';
$messages['passwordweak'] = '密码必须至少包含一个数字和一个标点符号。';
$messages['passwordforbidden'] = '密码包含禁止使用的字符。';
$messages['firstloginchange'] = '这是你第一次登录邮箱，请修改邮箱密码。';
$messages['disablednotice'] = '系统目前正在维护，密码修改功能无法使用。我们会很快回来，给您带来不便深表歉意。';
