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
$labels['changepasswd']  = 'Ändra lösenord';
$labels['curpasswd']  = 'Nuvarande lösenord';
$labels['newpasswd']  = 'Nytt lösenord';
$labels['confpasswd']  = 'Bekräfta nytt lösenord';
$messages['nopassword'] = 'Ange nytt lösenord.';
$messages['nocurpassword'] = 'Ange nuvarande lösenord.';
$messages['passwordincorrect'] = 'Felaktigt nuvarande lösenord.';
$messages['passwordinconsistency'] = 'Bekräftelsen av lösenordet stämmer inte, försök igen.';
$messages['crypterror'] = 'Lösenordet kunde inte ändras. Krypteringsfunktionen saknas.';
$messages['connecterror'] = 'Lösenordet kunde inte ändras. Anslutningen misslyckades.';
$messages['internalerror'] = 'Lösenordet kunde inte ändras.';
$messages['passwordshort'] = 'Lösenordet måste vara minst $length tecken långt.';
$messages['passwordweak'] = 'Lösenordet måste innehålla minst en siffra och ett specialtecken.';
$messages['passwordforbidden'] = 'Lösenordet innehåller otillåtna tecken.';
$messages['firstloginchange'] = 'Du loggar in för första gången. Vänligen ändra ditt lösenord.';
$messages['disablednotice'] = 'Underhåll av systemet pågår och därför kan lösenord inte ändras för tillfället. Allt ska fungera normalt igen så snart som möjligt. Ursäkta den eventuella olägenheten underhållet orsakar.';
$messages['passwinhistory'] = 'Lösenordet har redan använts tidigare.';
$messages['samepasswd'] = 'Lösenordet får inte vara samma som det gamla.';
$messages['passwdexpirewarning'] = 'Varning! Lösenordet blir snart ogiltigt, ändra det innan $expirationdatetime.';
$messages['passwdexpired'] = 'Lösenordet är ogiltigt, du måste ändra det nu!';
