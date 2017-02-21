<?php

/*
 +-----------------------------------------------------------------------+
 | localization/<lang>/messages.inc                                      |
 |                                                                       |
 | Localization file of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/messages/
*/
$messages['errortitle']  = 'Ha ocurrido un error!';
$messages['loginfailed']  = 'Contraseña o nombre de usuario incorrecto';
$messages['cookiesdisabled'] = 'Su navegador no acepta cookies';
$messages['sessionerror'] = 'Su sesión no existe o ha expirado';
$messages['storageerror'] = 'Error de conexión con el servidor IMAP';
$messages['servererror'] = '¡Error en el servidor!';
$messages['servererrormsg'] = 'Error en servidor: $msg';
$messages['connerror'] = 'Error de conexión (No se pudo conectar con el servidor)!';
$messages['dberror'] = 'Error en base de datos!';
$messages['windowopenerror'] = 'La ventana emergente ha sido bloqueada!';
$messages['requesttimedout'] = 'Tiempo de espera agotado';
$messages['errorreadonly'] = 'No se pudo realizar la operación. La carpeta es de sólo lectura.';
$messages['errornoperm'] = 'No se pudo realizar la operación. Permiso denegado.';
$messages['erroroverquota'] = 'No se pudo realizar la operación. Espacio en disco insuficiente.';
$messages['erroroverquotadelete'] = 'Espacio en disco insuficiente. Utilice SHIFT+DEL para eliminar un mensaje.';
$messages['invalidrequest'] = 'Peteción invalida! No se guardó nada.';
$messages['invalidhost'] = 'Nombre de servidor inválido.';
$messages['nomessagesfound'] = 'No se han encontrado mensajes en esta casilla';
$messages['loggedout'] = 'Ha cerrado la sesión. ¡Hasta pronto!';
$messages['mailboxempty'] = 'La casilla está vacía';
$messages['nomessages'] = '0 mensajes';
$messages['refreshing'] = 'Actualizando...';
$messages['loading'] = 'Cargando...';
$messages['uploading'] = 'Subiendo archivo...';
$messages['uploadingmany'] = 'Subiendo archivos...';
$messages['loadingdata'] = 'Cargando datos...';
$messages['checkingmail'] = 'Verificando si hay nuevos mensajes...';
$messages['sendingmessage'] = 'Enviando mensaje...';
$messages['messagesent'] = 'Mensaje enviado correctamente';
$messages['savingmessage'] = 'Guardar mensaje...';
$messages['messagesaved'] = 'Mensaje guardado en borradores';
$messages['successfullysaved'] = 'Guardado correctamente';
$messages['savingresponse'] = 'Guardando texto de respuesta...';
$messages['deleteresponseconfirm'] = 'Realmente deseas eliminar este texto de respuesta?';
$messages['addedsuccessfully'] = 'Contacto añadido correctamente a la libreta de direcciones';
$messages['contactnameexists'] = 'Ya existe un contacto con el mismo nombre.';
$messages['blockedimages'] = 'Para proteger su privacidad, las imágenes externas han sido bloqueadas en este mensaje';
$messages['encryptedmessage'] = 'Este es un mensaje cifrado y no puede ser mostrado. ¡Lo siento!';
$messages['nocontactsfound'] = 'No hay contactos';
$messages['contactnotfound'] = 'El contacto solicitado no existe';
$messages['contactsearchonly'] = 'Ingresá algún término de búsqueda para encontrar contactos';
$messages['sendingfailed'] = 'Error al enviar mensaje';
$messages['senttooquickly'] = 'Por favor, espere $sec segundo(s) antes de enviar este mensaje';
$messages['errorsavingsent'] = 'Ocurrió un error al guardar el mensaje enviado';
$messages['errorsaving'] = 'Ocurrió un error al guardar';
$messages['errormoving'] = 'No se pudo mover el mensaje';
$messages['errorcopying'] = 'No se pudo copiar el mensaje';
$messages['errordeleting'] = 'No se ha podido eliminar el mensaje';
$messages['errormarking'] = 'No se puede marcar el mensaje.';
$messages['deletecontactconfirm']  = '¿Realmente quiere eliminar los contactos seleccionados?';
$messages['deletegroupconfirm']  = 'Realmente deseas eliminar el grupo seleccionado?';
$messages['deletemessagesconfirm'] = '¿Realmente quiere eliminar los mensajes seleccionados?';
$messages['deletefolderconfirm']  = '¿Realmente quiere eliminar esta carpeta?';
$messages['purgefolderconfirm']  = '¿Realmente quiere eliminar todos los mensajes de esta carpeta?';
$messages['contactdeleting'] = 'Eliminando contacto(s)...';
$messages['groupdeleting'] = 'Eliminando grupo...';
$messages['folderdeleting'] = 'Eliminando carpeta...';
$messages['foldermoving'] = 'Moviendo carpeta...';
$messages['foldersubscribing'] = 'Suscribiendo a carpeta...';
$messages['folderunsubscribing'] = 'Quitando suscripción a carpeta...';
$messages['formincomplete'] = 'No se han llenado todos los campos del formulario';
$messages['noemailwarning'] = 'Por favor, introduzca un e-mail válido';
$messages['nonamewarning']  = 'Por favor, introduzca su nombre';
$messages['nopagesizewarning'] = 'Por favor, introduzca un tamaño de página';
$messages['norecipientwarning'] = 'Por favor, introduzca al menos un destinatario';
$messages['nosubjectwarning']  = 'El campo "Asunto" esta vacío. ¿Desea completarlo en este momento?';
$messages['nobodywarning'] = '¿Quiere enviar este mensaje sin texto?';
$messages['notsentwarning'] = 'El mensaje no ha sido enviado. ¿Desea descartar su mensaje?';
$messages['restoresavedcomposedata'] = 'Se ha encontrado un mensaje redactado anteriormente que no ha sido enviado.\n\nAsunto: $subject\nGuardado: $date\n\nDeseas recuperar este mensaje?';
$messages['noldapserver'] = 'Por favor, seleccione un servidor LDAP para buscar';
$messages['nosearchname'] = 'Por favor, introduzca un nombre o la dirección de e-mail';
$messages['notuploadedwarning'] = 'Todavia no se subieron todos los archivos adjuntos. Por favor aguarde o cancele la subida.';
$messages['searchsuccessful'] = 'Se encontraron $nr mensajes';
$messages['contactsearchsuccessful'] = '$nr contactos encontrados.';
$messages['searchnomatch'] = 'La búsqueda no obtuvo resultados';
$messages['searching'] = 'Buscando...';
$messages['checking'] = 'Revisando...';
$messages['stillsearching'] = 'Búsqueda en progreso...';
$messages['nospellerrors'] = 'No se encontraron errores ortográficos';
$messages['folderdeleted'] = 'Carpeta eliminada exitosamente';
$messages['foldersubscribed'] = 'Suscripción a carpeta exitosa.';
$messages['folderunsubscribed'] = 'Suscripción a carpeta exitosamente eliminada.';
$messages['folderpurged'] = 'La carpeta se vació correctamente.';
$messages['folderexpunged'] = 'La carpeta se compactó correctamente.';
$messages['deletedsuccessfully'] = 'Eliminado exitosamente';
$messages['converting'] = 'Removiendo el formato del mensaje...';
$messages['messageopenerror'] = 'No puedo descargar el mensaje del servidor';
$messages['fileuploaderror'] = 'Error al subir archivos';
$messages['filesizeerror'] = 'El archivo excede el tamaño maximo ($size)';
$messages['copysuccess'] = '$nr contactos copiados exitosamente.';
$messages['movesuccess'] = '$nr contactos movidos exitosamente.';
$messages['copyerror'] = 'No se pudo copiar ningún contacto.';
$messages['moveerror'] = 'No se pudo mover ningún contacto.';
$messages['sourceisreadonly'] = 'Esta dirección es de sólo-lectura';
$messages['errorsavingcontact'] = 'No se pudo guardar la dirección de contacto';
$messages['movingmessage'] = 'Moviendo mensaje...';
$messages['copyingmessage'] = 'Copiando mensaje...';
$messages['copyingcontact'] = 'Copiando contacto(s)...';
$messages['movingcontact'] = 'Moviendo contacto(s)...';
$messages['deletingmessage'] = 'Eliminando contacto(s)...';
$messages['markingmessage'] = 'Marcando mensaje(s)...';
$messages['addingmember'] = 'Agregando contacto(s) al grupo...';
$messages['removingmember'] = 'Quitando contacto(s) del grupo...';
$messages['receiptsent'] = 'La notificación de lectura se ha enviado con éxito.';
$messages['errorsendingreceipt'] = 'No se ha podido enviar la notificación de lectura.';
$messages['deleteidentityconfirm'] = 'Realmente querés eliminar esta identidad?';
$messages['nodeletelastidentity'] = 'No se puede borrar esta identidad puesto que es la última.';
$messages['forbiddencharacter'] = 'El nombre de la carpeta contiene un carácter prohibido';
$messages['selectimportfile'] = 'Por favor, seleccione el archivo a subir';
$messages['addresswriterror'] = 'La libreta de direcciones seleccionada es de solo-lectura';
$messages['contactaddedtogroup'] = 'Contactos agregados al grupo correctamente';
$messages['contactremovedfromgroup'] = 'Contactos eliminados del grupo correctamente';
$messages['nogroupassignmentschanged'] = 'Ninguna asiganción de grupo fue modificada.';
$messages['importwait'] = 'Importando, aguarde por favor...';
$messages['importformaterror'] = 'Importación fallida! El archivo subido no es un archivo de importación de datos válido.';
$messages['importconfirm'] = '<b>Se importaron $inserted contactos correctamente. $skipped ya existentes fueron ignorados</b>:<p><em>$names</em></p>';
$messages['importconfirmskipped'] = '<b>Se ignoraron $skipped entradas ya existentes</b>';
$messages['importmessagesuccess'] = '$nr mensajes importados exitosamente';
$messages['importmessageerror'] = 'Importación fallida! El archivo subido no es un mensaje o archivo mailbox válido';
$messages['opnotpermitted'] = 'Operación no permitida!';
$messages['editorwarning'] = 'Cambiar el tipo de editor puede causar la pérdida del formato. Deseas continuar?';
$messages['httpreceivedencrypterror'] = 'Ha ocurrido un error fatal de configuración. Contacte inmediatamente a su administrador. <b>Su mensaje no ha sido enviado.</b>';
$messages['smtpconnerror'] = 'Error SMTP ($code): Conexión al servidor falló';
$messages['smtpautherror'] = 'Error SMTP ($code): Falló la autentificación';
$messages['smtpfromerror'] = 'Error SMTP ($code): No se pudo establecer el remitente "$from" ($msg)';
$messages['smtptoerror'] = 'Error SMTP ($code): No se puedo agregar el destinatario "$to" ($msg)';
$messages['smtprecipientserror'] = 'Error SMTP: No se pudo leer la lista de destinatarios';
$messages['smtperror'] = 'Error SMTP: $msg';
$messages['toomanyrecipients'] = 'Demasiados destinatarios. Reduzca la cantidad a un máximo de $max.';
$messages['maxgroupmembersreached'] = 'El número de miembros del grupo excede el máximo de $max';
$messages['internalerror'] = 'Ocurrió un error interno. Por favor, intenta nuevamente.';
$messages['contactdelerror'] = 'No se pudo eliminar el/los contactos(s).';
$messages['contactdeleted'] = 'Contacto(s) eliminado/s exitosamente.';
$messages['contactrestoreerror'] = 'No se pudo recuperar el/los contacto/s eliminado(s).';
$messages['contactrestored'] = 'Contacto(s) recuperado/s satisfactoriamente.';
$messages['groupdeleted'] = 'Grupo eliminado correctamente.';
$messages['grouprenamed'] = 'Grupo renombrado satisfactoriamente.';
$messages['groupcreated'] = 'Grupo creado satisfactoriamente.';
$messages['savedsearchdeleted'] = 'Búsqueda guardada se eliminó satisfactoriamente.';
$messages['savedsearchdeleteerror'] = 'No se pudo eliminar la búsqueda guardada.';
$messages['savedsearchcreated'] = 'Búsqueda guardada exitosamente.';
$messages['savedsearchcreateerror'] = 'No se pudo guardar la búsqueda.';
$messages['messagedeleted'] = 'Mensaje(s) eliminado/s satisfactoriamente.';
$messages['messagemoved'] = 'Mensaje(s) movidos correctamente.';
$messages['messagecopied'] = 'Mensaje(s) copiados correctamente.';
$messages['messagemarked'] = 'Mensaje(s) marcados satisfactoriamente.';
$messages['autocompletechars'] = 'Ingresá por lo menos $min caracteres para autocompletar.';
$messages['autocompletemore'] = 'Se encontraron mas coincidencias. Por favor, escribí mas caracteres.';
$messages['namecannotbeempty'] = 'El nombre no puede estar vacio.';
$messages['nametoolong'] = 'El nombre es demasiado largo.';
$messages['folderupdated'] = 'Carpeta actualizada correctamente.';
$messages['foldercreated'] = 'Carpeta creada satisfactoriamente.';
$messages['invalidimageformat'] = 'No es un formato de imágen válido.';
$messages['mispellingsfound'] = 'Errores de ortografía encontrados en el mensaje.';
$messages['parentnotwritable'] = 'No se pudo crear/mover la carpeta dentro de la carpeta superior seleccionada. No tiene permiso para acceder a ella.';
$messages['messagetoobig'] = 'El mensaje es demasiado grande para ser procesado.';
$messages['attachmentvalidationerror'] = 'Advertencia! Este archivo adjunto es sospechoso porque su tipo no concuerda con el tipo declarado en el mensaje. Si no confias en el remitente, no deberías abrirlo en el navegador porque puede contener código malicioso.<br/><br/><em>Se esperaba: $expected; Se encontró: $detected</em>';
$messages['noscriptwarning'] = 'Advertencia: Este servicio de webmail requiere Javascript! Para usarlo, por favor, habilite Javascript en las preferencia de tu navegador.';
