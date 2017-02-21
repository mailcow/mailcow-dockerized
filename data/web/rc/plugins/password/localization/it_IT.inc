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
$labels['changepasswd']  = 'Modifica la Password';
$labels['curpasswd']  = 'Password corrente:';
$labels['newpasswd']  = 'Nuova password:';
$labels['confpasswd']  = 'Conferma la nuova Password:';
$messages['nopassword'] = 'Per favore inserire la nuova password.';
$messages['nocurpassword'] = 'Per favore inserire la password corrente.';
$messages['passwordincorrect'] = 'La password corrente non è corretta.';
$messages['passwordinconsistency'] = 'Le password non coincidono, per favore reinserire.';
$messages['crypterror'] = 'Impossibile salvare la nuova password. Funzione di crittografia mancante.';
$messages['connecterror'] = 'Impossibile salvare la nuova password. Errore di connessione.';
$messages['internalerror'] = 'Impossibile salvare la nuova password.';
$messages['passwordshort'] = 'La password deve essere lunga almeno $length caratteri.';
$messages['passwordweak'] = 'La password deve includere almeno una cifra decimale e un carattere di punteggiatura.';
$messages['passwordforbidden'] = 'La password contiene caratteri proibiti.';
$messages['firstloginchange'] = 'Questo è il tuo primo accesso. Si prega di cambiare la propria password.';
$messages['disablednotice'] = 'Il sistema è attualmente in manutenzione e pertanto al momento non è possibile effettuare il cambio della password. Presto tutto tornerà alla normalità. Ci scusiamo per il disagio.';
$messages['passwinhistory'] = 'Questa password è già stata usata precedentemente';
$messages['samepasswd'] = 'La nuova password deve essere differente da una usata precedentemente.';
$messages['passwdexpirewarning'] = 'Attenzione! La tua password scadrà a breve, cambiala prima del $expiretiondatetime.';
$messages['passwdexpired'] = 'La tua password è scaduta, devi cambiarla adesso.';
