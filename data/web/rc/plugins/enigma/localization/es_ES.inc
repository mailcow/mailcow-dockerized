<?php

/**
 +-----------------------------------------------------------------------+
 | plugins/enigma/localization/<lang>.inc                                |
 |                                                                       |
 | Localization file of the Roundcube Webmail ACL plugin                 |
 | Copyright (C) 2012-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/roundcube/roundcube-webmail/plugin-enigma/
*/
$labels['encryption'] = 'Encriptación';
$labels['enigmacerts'] = 'Certificados S/MIME';
$labels['enigmakeys'] = 'Claves PGP';
$labels['keysfromto'] = 'Llaves $from hasta $to de $count';
$labels['keyname'] = 'Nombre';
$labels['keyid'] = 'ID de Clave';
$labels['keyuserid'] = 'ID de Usuario';
$labels['keytype'] = 'Tipo de Clave';
$labels['fingerprint'] = 'Huella dactilar';
$labels['subkeys'] = 'Subclaves';
$labels['basicinfo'] = 'Información Básica';
$labels['userids'] = 'Usuarios Adicionales';
$labels['typepublickey'] = 'llave pública';
$labels['typekeypair'] = 'clave par';
$labels['keyattfound'] = 'Este mensaje contiene adjunto una(s) clave(s) PGP.';
$labels['keyattimport'] = 'Importar clave(s)';
$labels['typesign'] = 'Firmar';
$labels['typeencrypt'] = 'Encriptar';
$labels['typecert'] = 'Certificar';
$labels['typeauth'] = 'Autentificación';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritmo';
$labels['subkeycreated'] = 'Creado';
$labels['subkeyexpires'] = 'Expirado';
$labels['subkeyusage'] = 'Uso';
$labels['expiresnever'] = 'nunca';
$labels['unknown'] = 'desconocido';
$labels['uservalid'] = 'Válido';
$labels['userid'] = 'ID';
$labels['valid'] = 'válido';
$labels['supportencryption'] = 'Habilitar el cifrado de mensajes y firma';
$labels['supportsignatures'] = 'Activar la verificación de firmas de mensajes';
$labels['supportdecryption'] = 'Habilitar descifrado de mensaje';
$labels['signdefault'] = 'Firmar todos los mensajes por defecto';
$labels['encryptdefault'] = 'Encriptar todos los mensajes por defecto';
$labels['attachpubkeydefault'] = 'Adjuntar mi clave PGP pública por defecto';
$labels['passwordtime'] = 'Mantenga las contraseñas de clave privada para';
$labels['nminutes'] = '$m minuto(s)';
$labels['wholesession'] = 'toda la sesión';
$labels['createkeys'] = 'Crear una nueva clave par';
$labels['importkeys'] = 'Importar clave(s)';
$labels['exportkeys'] = 'Exportar clave(s)';
$labels['keyactions'] = 'Acción de llave...';
$labels['keyremove'] = 'Eliminar';
$labels['keydisable'] = 'Deshabilitado';
$labels['keyrevoke'] = 'Revocar';
$labels['keysend'] = 'Enviar clave pública en el mensaje';
$labels['keychpass'] = 'Cambiar contraseña';
$labels['newkeyident'] = 'Identidad:';
$labels['newkeypass'] = 'Contraseña:';
$labels['newkeypassconfirm'] = 'Confirmar contraseña:';
$labels['newkeysize'] = 'Tamaño de clave:';
$labels['key2048'] = '2048 bits - por defecto';
$labels['key4096'] = '4096 bits - más seguro';
$labels['keygenerating'] = 'Generando claves...';
$labels['encryptionoptions'] = 'Opciones de encriptado...';
$labels['encryptmsg'] = 'Encriptar este mensaje.';
$labels['signmsg'] = 'Firmar digitalmente este mensaje';
$labels['enterkeypasstitle'] = 'Escriba la frase de contraseña';
$labels['enterkeypass'] = 'Se necesita una contraseña para desbloquear la clave secreta ($keyid) para el usuario: $user.';
$labels['arialabelkeyexportoptions'] = 'Opciones de claves de exportación';
$labels['attachpubkeymsg'] = 'Adjuntar mi clave pública';
$labels['keyexportprompt'] = '¿Quiere incluir claves secretas en el archivo guardado de claves OpenPGP?';
$labels['onlypubkeys'] = 'Exportar Sólo las Claves Públicas';
$labels['withprivkeys'] = 'Exportar las Claves Secretas';
$labels['findkey'] = 'Buscar en servidor(es) de claves';
$labels['keyimportlabel'] = 'Importar desde archivo';
$labels['keyimportsearchlabel'] = 'Buscar en servidor(es) de claves';
$messages['sigvalid'] = 'Firma verificada por $sender.';
$messages['sigvalidpartial'] = 'Firma verificada de $sender, pero parte parte del cuerpo no estaba firmado.';
$messages['siginvalid'] = 'Firma inválida por $sender.';
$messages['sigunverified'] = 'Firma no verificada. Error interno. Clave pública no encontrada. ID del Certificado: $keyid.';
$messages['signokey'] = 'Firma no verificada. Error interno. Clave pública no encontrada. ID de Clave: $keyid.';
$messages['sigerror'] = 'Firma no verificada. Error interno.';
$messages['decryptok'] = 'Mensaje desenciptado.';
$messages['decrypterror'] = 'Desenciptación fallida.';
$messages['decryptnokey'] = 'Desenciptación fallida. Clave privada no encontrada. ID de Clave: $keyid.';
$messages['decryptnopass'] = 'Desencriptado fallido. Contraseña requerida.';
$messages['decryptpartial'] = 'Mensaje descifrado, pero parte del cuerpo no estaba cifrado.';
$messages['signerror'] = 'Firmado fallido.';
$messages['signnokey'] = 'Firmado fallido. Clave privada no encontrada.';
$messages['signnopass'] = 'Acceso fallido. Contraseña requerida.';
$messages['encrypterror'] = 'Encriptación fallida.';
$messages['encryptnokey'] = 'Encriptación fallida. Clave pública no encontrada para $email.';
$messages['nokeysfound'] = 'No se encontraron claves';
$messages['keynotfound'] = '¡Clave no encontrada!';
$messages['keyopenerror'] = '¡No se puede obtener la información de clave! Error interno.';
$messages['keylisterror'] = '¡No es posible enumerar las claves! Error interno.';
$messages['keysimportfailed'] = '¡No se puede(n) importar la(s) clave(s)! Error interno.';
$messages['keysimportsuccess'] = 'Clave(s) importada(s) correctamente. Importado: $new, sin cambios: $old.';
$messages['keyremoving'] = 'Eliminando clave(s)...';
$messages['keyremoveconfirm'] = '¿Seguro que quiere eliminar la(s) clave(s) seleccionada(s)?';
$messages['keyremovesuccess'] = 'Clave(s) eliminada(s) correctamente.';
$messages['keyremoveerror'] = 'No se han podido borrar las clave(s) seleccionadas.';
$messages['keyimporttext'] = 'Puede importar la(s) clave(s) privada(s) y pública(s) o firmas de revocación en formato ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Puedes buscar claves por identificador de clave, nombre de usuario o dirección de email y después importarlas directamente.';
$messages['formerror'] = 'Por favor llene el formulario. ¡Todos los campos son obligatorios!';
$messages['passwordsdiffer'] = '¡Las contraseñas no coinciden!';
$messages['keygenerateerror'] = 'No se ha podido generar una clave par';
$messages['keygeneratesuccess'] = 'Un par de claves generadas e importadas correctamente.';
$messages['keygennosupport'] = 'Su navegador web no soporta criptografía. ¡No se puede generar un par de claves!';
$messages['noidentselected'] = '¡Tiene que seleccionar al menos una identidad para la llave!';
$messages['nonameident'] = '¡La identidad debe de tener un nombre de usuario definido!';
?>
