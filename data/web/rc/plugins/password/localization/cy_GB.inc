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
$labels['changepasswd']  = 'Newid cyfrinair';
$labels['curpasswd']  = 'Cyfrinair Presennol:';
$labels['newpasswd']  = 'Cyfrinair Newydd:';
$labels['confpasswd']  = 'Cadarnhau Cyfrinair Newydd:';
$messages['nopassword'] = 'Rhowch eich cyfrinair newydd.';
$messages['nocurpassword'] = 'Rhowch eich cyfrinair presennol.';
$messages['passwordincorrect'] = 'Roedd y cyfrinair presennol yn anghywir.';
$messages['passwordinconsistency'] = 'Nid yw\'r cyfrineiriau yn cymharu, ceisiwch eto.';
$messages['crypterror'] = 'Methwyd cadw\'r cyfrinair newydd. Ffwythiant amgodi ar goll.';
$messages['connecterror'] = 'Methwyd cadw\'r cyfrinair newydd. Gwall cysylltiad.';
$messages['internalerror'] = 'Methwyd cadw\'r cyfrinair newydd.';
$messages['passwordshort'] = 'Rhaid i\'r cyfrinair fod o leia $length llythyren o hyd.';
$messages['passwordweak'] = 'Rhaid i\'r cyfrinair gynnwys o leia un rhif a un cymeriad atalnodi.';
$messages['passwordforbidden'] = 'Mae\'r cyfrinair yn cynnwys llythrennau wedi gwahardd.';
$messages['firstloginchange'] = 'Dyma eich mewngofnodiad cynta. Newidiwch eich cyfrinair.';
$messages['disablednotice'] = 'Mae gwaith cynnal a chadw ar y system ar hyn o bryd a nid yw\'n bosib newid y cyfrinair ar hyn o bryd. Fe ddylai fod popeth nol i\'r arfer yn fuan. Rydym yn ymddiheuro am yr anghyfleustra.';
$messages['passwinhistory'] = 'Defnyddiwyd y cyfrinair hwn o\'r blaen.';
$messages['samepasswd'] = 'Rhaid i\'r cyfrinair newydd fod yn wahanol i\'r hen un.';
$messages['passwdexpirewarning'] = 'Rhybudd! Fe fydd eich cyfrinair yn dod i ben cyn hir, newidiwch e cyn $expirationdatetime.';
$messages['passwdexpired'] = 'Mae\'ch cyfrinair wedi dod i ben, rhaid i chi ei newid nawr!';
