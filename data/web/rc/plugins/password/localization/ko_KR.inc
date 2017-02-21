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
$labels['changepasswd']  = '암호 변경';
$labels['curpasswd']  = '현재 암호:';
$labels['newpasswd']  = '새로운 암호:';
$labels['confpasswd']  = '새로운 암호 확인:';
$messages['nopassword'] = '새로운 암호를 입력하세요.';
$messages['nocurpassword'] = '현재 사용 중인 암호를 입력하세요.';
$messages['passwordincorrect'] = '현재 사용 중인 암호가 올바르지 않습니다.';
$messages['passwordinconsistency'] = '암호가 일치하지 않습니다. 다시 시도해주세요.';
$messages['crypterror'] = '새로운 암호를 저장할 수 없습니다. 암호화 기능이 누락되었습니다.';
$messages['connecterror'] = '새로운 암호를 저장할 수 없습니다. 연결 오류입니다.';
$messages['internalerror'] = '새로운 암호를 저장할 수 없습니다.';
$messages['passwordshort'] = '암호는 문자가 $length개 이상이어야 합니다.';
$messages['passwordweak'] = '암호는 숫자 및 특수문자를 각각 한 개 이상 포함해야 합니다.';
$messages['passwordforbidden'] = '암호가 금지된 문자을 포함하고 있습니다.';
$messages['firstloginchange'] = '처음 로그인하셨습니다. 암호를 변경해주세요.';
$messages['disablednotice'] = '시스템이 현재 유지보수 중이며 암호 변경이 일시적으로 불가능합니다. 모든 것이 조만간 정상화 될 예정입니다. 불편을 끼쳐드려 죄송합니다.';
$messages['passwinhistory'] = '이 암호는 이미 이전에 사용됐습니다.';
$messages['samepasswd'] = '새로운 암호는 예전 암호와 달라야 합니다.';
$messages['passwdexpirewarning'] = '경고! 암호가 곧 만료됩니다. $expirationdatetime 이전에 변경하십시오.';
$messages['passwdexpired'] = '암호가 만료됐습니다. 지금 변경하셔야 합니다!';
