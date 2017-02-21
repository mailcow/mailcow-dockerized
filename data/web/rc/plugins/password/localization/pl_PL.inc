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
$labels['changepasswd']  = 'Zmiana hasła';
$labels['curpasswd']  = 'Aktualne hasło:';
$labels['newpasswd']  = 'Nowe hasło:';
$labels['confpasswd']  = 'Potwierdź hasło:';
$messages['nopassword'] = 'Wprowadź nowe hasło.';
$messages['nocurpassword'] = 'Wprowadź aktualne hasło.';
$messages['passwordincorrect'] = 'Błędne aktualne hasło, spróbuj ponownie.';
$messages['passwordinconsistency'] = 'Hasła nie pasują, spróbuj ponownie.';
$messages['crypterror'] = 'Nie udało się zapisać nowego hasła. Brak funkcji kodującej.';
$messages['connecterror'] = 'Nie udało się zapisać nowego hasła. Błąd połączenia.';
$messages['internalerror'] = 'Nie udało się zapisać nowego hasła.';
$messages['passwordshort'] = 'Hasło musi posiadać co najmniej $length znaków.';
$messages['passwordweak'] = 'Hasło musi zawierać co najmniej jedną cyfrę i znak interpunkcyjny.';
$messages['passwordforbidden'] = 'Hasło zawiera niedozwolone znaki.';
$messages['firstloginchange'] = 'To jest twoje pierwsze logowanie. Proszę zmień hasło.';
$messages['disablednotice'] = 'System jest w trakcie konserwacji i zmiana hasła w tym momencie nie jest możliwa. Wszystko powinno wrócić do normy w niedługim czasie. Przepraszamy za wszelkie niedogodności';
$messages['passwinhistory'] = 'To hasło było już wcześniej użyte';
$messages['samepasswd'] = 'Nowe hasło musi być różne od poprzednich.';
$messages['passwdexpirewarning'] = 'Uwaga! Twoje hasło wkrótce wygaśnie, zmień je przed $expirationdatetime.';
$messages['passwdexpired'] = 'Twoje hasło wygasło, musisz je zmienić.';
