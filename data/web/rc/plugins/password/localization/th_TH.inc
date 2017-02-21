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
$labels['changepasswd']  = 'เปลี่ยนรหัสผ่าน';
$labels['curpasswd']  = 'รหัสผ่านปัจจุบัน';
$labels['newpasswd']  = 'รหัสผ่านใหม่';
$labels['confpasswd']  = 'ยืนยันรหัสผ่านใหม่';
$messages['nopassword'] = 'กรุณาใส่รหัสผ่านใหม่';
$messages['nocurpassword'] = 'กรุณาใส่รหัสผ่านปัจจุบัน';
$messages['passwordincorrect'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
$messages['passwordinconsistency'] = 'รหัสผ่านไม่ตรงกัน กรุณากรอกใหม่อีกครััง';
$messages['crypterror'] = 'ไม่สามารถบันทึกรหัสผ่านใหม่. ฟังก์ชั่นการเข้ารหัสขาดหายไป.';
$messages['connecterror'] = 'ไม่สามารถบันทึกรหัสผ่านใหม่. เชื่อมต่อผิดพลาด.';
$messages['internalerror'] = 'ไม่สามารถบันทึกรหัสผ่านใหม่.';
$messages['passwordshort'] = 'รหัสผ่านต้องมีอย่างน้อย $length ตัวอักษร.';
$messages['passwordweak'] = 'รหัสผ่านต้องมีอย่างน้อยหนึ่งตัวเลขและตัวอักษรวรรคตอนหนึ่ง';
$messages['passwordforbidden'] = 'รหัสผ่านมีอักขระต้องห้าม';
$messages['firstloginchange'] = 'นี่คือครั้งแรกในการเข้าระบบ กรุณาเปลี่ยนรหัสผ่านของคุณ';
$messages['disablednotice'] = 'ระบบขณะนี้อยู่ระหว่างการบำรุงรักษาและการเปลี่ยนรหัสผ่านเป็นไปไม่ได้ในขณะนี้ ทุกอย่างควรจะกลับมาเป็นปกติเร็ว ๆ นี้ เราต้องขออภัยในความไม่สะดวก.';
$messages['passwinhistory'] = 'รหัสผ่านนี้ได้ถูกนำมาใช้ก่อนหน้านี้';
$messages['samepasswd'] = 'รหัสผ่านใหม่จะต้องมีความแตกต่างจากเดิม';
$messages['passwdexpirewarning'] = 'คำเตือน! รหัสผ่านของคุณจะหมดอายุในเร็ว ๆ นี้ เปลี่ยนก่อน $expirationdatetime.';
$messages['passwdexpired'] = 'รหัสผ่านของคุณหมดอายุแล้วคุณมีการเปลี่ยนแปลงในขณะนี้!';
