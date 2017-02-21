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
$labels['changepasswd']  = 'Cambiar contraseña';
$labels['curpasswd']  = 'Contraseña actual:';
$labels['newpasswd']  = 'Contraseña nueva:';
$labels['confpasswd']  = 'Confirmar contraseña:';
$messages['nopassword'] = 'Por favor, introduzca una contraseña nueva.';
$messages['nocurpassword'] = 'Por favor, introduzca la contraseña actual.';
$messages['passwordincorrect'] = 'La contraseña actual es incorrecta.';
$messages['passwordinconsistency'] = 'Las contraseñas no coinciden. Por favor, inténtelo de nuevo.';
$messages['crypterror'] = 'No se pudo guardar la contraseña nueva. Falta la función de cifrado.';
$messages['connecterror'] = 'No se pudo guardar la contraseña nueva. Error de conexión.';
$messages['internalerror'] = 'No se pudo guardar la contraseña nueva.';
$messages['passwordshort'] = 'La contraseña debe tener al menos $length caracteres.';
$messages['passwordweak'] = 'La contraseña debe incluir al menos un número y un signo de puntuación.';
$messages['passwordforbidden'] = 'La contraseña introducida contiene caracteres no permitidos.';
$messages['firstloginchange'] = 'Este es su primer inicio de sesión. Por favor, cambie su contraseña.';
$messages['disablednotice'] = 'El sistema actualmente está en mantenimiento y ahora no se puede cambiar la contraseña. Todo debería volver a la normalidad pronto. Disculpa las molestias.';
$messages['passwinhistory'] = 'Esta contraseña ya se ha usado previamente.';
$messages['samepasswd'] = 'La nueva contraseña tiene que ser diferente a la antigua.';
$messages['passwdexpirewarning'] = '¡ Atención! Su nueva contraseña expirará pronto, cámbiela antes de $expirationdatetime.';
$messages['passwdexpired'] = 'Su contraseña ha expirado, tiene que cambiarla ahora.';
