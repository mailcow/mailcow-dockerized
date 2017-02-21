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
$labels['changepasswd']  = 'Changer le mot de passe';
$labels['curpasswd']  = 'Mot de passe actuel :';
$labels['newpasswd']  = 'Nouveau mot de passe :';
$labels['confpasswd']  = 'Confirmer le nouveau mot de passe :';
$messages['nopassword'] = 'Veuillez saisir le nouveau mot de passe.';
$messages['nocurpassword'] = 'Veuillez saisir le mot de passe actuel.';
$messages['passwordincorrect'] = 'Le mot de passe actuel est erroné.';
$messages['passwordinconsistency'] = 'Les mots de passe ne correspondent pas, veuillez ressayer.';
$messages['crypterror'] = 'Impossible d\'enregistrer le nouveau mot de passe. La fonction de chiffrement est manquante.';
$messages['connecterror'] = 'Impossible d\'enregistrer le nouveau mot de passe. Erreur de connexion.';
$messages['internalerror'] = 'Impossible d\'enregistrer le nouveau mot de passe.';
$messages['passwordshort'] = 'Le mot de passe doit comporter au moins $length caractères.';
$messages['passwordweak'] = 'Le mot de passe doit comporter au moins un chiffre et un signe de ponctuation.';
$messages['passwordforbidden'] = 'Le mot de passe contient des caractères interdits.';
$messages['firstloginchange'] = 'Ceci est votre première connexion. Veuillez changer votre mot de passe.';
$messages['disablednotice'] = 'Le système est en cours de maintenance et les changements de mot de passe sont impossibles pour l\'instant. Tout devrait redevenir normal sous peu. Nous regrettons tout inconvénient que cette situation pourrait occasionner.';
$messages['passwinhistory'] = 'Le mot de passe a déjà été utilisé précédemment.';
$messages['samepasswd'] = 'Le nouveau mot de passe doit être différent du précédent.';
$messages['passwdexpirewarning'] = 'Attention ! Votre mot de passe expirera prochainnement. Changez-le avant le $expirationdatetime.';
$messages['passwdexpired'] = 'Votre mot de passe a expiré. Vous devez le changer maintenant !';
