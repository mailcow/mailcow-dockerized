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
$labels['changepasswd']  = 'Ændre adgangskode ';
$labels['curpasswd']  = 'Nuværende adgangskode:';
$labels['newpasswd']  = 'Ny adgangskode:';
$labels['confpasswd']  = 'Bekræft ny adgangskode:';
$messages['nopassword'] = 'Indtast venligst en ny adgangskode.';
$messages['nocurpassword'] = 'Indtast venligst nuværende adgangskode.';
$messages['passwordincorrect'] = 'Nuværende adgangskode er forkert.';
$messages['passwordinconsistency'] = 'Adgangskoderne er ikke ens, prøv igen.';
$messages['crypterror'] = 'Kunne ikke gemme den nye adgangskode. Krypteringsfunktion mangler.';
$messages['connecterror'] = 'Kunne ikke gemme den nye adgangskode. Fejl ved forbindelsen.';
$messages['internalerror'] = 'Kunne ikke gemme den nye adgangskode.';
$messages['passwordshort'] = 'Adgangskoden skal være mindst $length tegn lang.';
$messages['passwordweak'] = 'Adgangskoden skal indeholde mindst et tal og et tegnsætningstegn (-.,)';
$messages['passwordforbidden'] = 'Adgangskoden indeholder forbudte tegn.';
$messages['firstloginchange'] = 'Dette er første gang du logger ind, ændre venligst din adgangskode';
$messages['disablednotice'] = 'Systemet vedligeholdes i øjeblikket og det er pt. ikke muligt at skifte kodeord. Alt burde være oppe igen om lidt. Vi beklager ulejligheden. ';
$messages['passwinhistory'] = 'Kodeordet har tidligere været anvendt. ';
$messages['samepasswd'] = 'Det nye kodeord skal være forskelligt fra det gamle. ';
$messages['passwdexpirewarning'] = 'Advarsel, Dit kodeord udløber snart. Du skal ændre det før $expirationdatetime.';
$messages['passwdexpired'] = 'Dit kodeord er udløbet, du skal ændre det nu!';
