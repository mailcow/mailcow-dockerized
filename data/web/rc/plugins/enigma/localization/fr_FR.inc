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
$labels['encryption'] = 'Chiffrement';
$labels['enigmacerts'] = 'Certificats S/MIME';
$labels['enigmakeys'] = 'Clés PGP';
$labels['keysfromto'] = 'Clés $from à $to sur $count';
$labels['keyname'] = 'Nom';
$labels['keyid'] = 'ID de clé';
$labels['keyuserid'] = 'ID utilisateur';
$labels['keytype'] = 'Type de clé';
$labels['fingerprint'] = 'Empreinte';
$labels['subkeys'] = 'Sous-clés';
$labels['basicinfo'] = 'Informations de base';
$labels['userids'] = 'Utilisateurs supplémentaires';
$labels['typepublickey'] = 'Clé publique';
$labels['typekeypair'] = 'biclé';
$labels['keyattfound'] = 'Ce courriel contient une/des clé(s) PGP jointe(s).';
$labels['keyattimport'] = 'Importer une/des clé(s)';
$labels['typesign'] = 'Signer';
$labels['typeencrypt'] = 'Chiffrer';
$labels['typecert'] = 'Certifier';
$labels['typeauth'] = 'Authentification';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algorithme ';
$labels['subkeycreated'] = 'Créée';
$labels['subkeyexpires'] = 'Expire';
$labels['subkeyusage'] = 'Utilisation';
$labels['expiresnever'] = 'jamais';
$labels['unknown'] = 'inconnu';
$labels['uservalid'] = 'Valide';
$labels['userid'] = 'ID';
$labels['valid'] = 'valide';
$labels['supportencryption'] = 'Activer la signature et le chiffrement des courriels';
$labels['supportsignatures'] = 'Activer la vérification des signatures des courriels';
$labels['supportdecryption'] = 'Activer le déchiffrement des courriels';
$labels['signdefault'] = 'Par défaut, signer tous les courriels';
$labels['encryptdefault'] = 'Par défaut, chiffrer tous les courriels';
$labels['attachpubkeydefault'] = 'Par défaut, joindre ma clé PGP publique';
$labels['passwordtime'] = 'Conserver les mots de passe des clés privées pendant';
$labels['nminutes'] = '$m minute(s)';
$labels['wholesession'] = 'la session complète';
$labels['createkeys'] = 'Créer une nouvelle biclé';
$labels['importkeys'] = 'Importer des clés';
$labels['exportkeys'] = 'Exporter des clés';
$labels['keyactions'] = 'Actions des clés...';
$labels['keyremove'] = 'Supprimer';
$labels['keydisable'] = 'Désactiver';
$labels['keyrevoke'] = 'Révoquer';
$labels['keysend'] = 'Envoyer la clé publique dans un courriel';
$labels['keychpass'] = 'Changer le mot de passe';
$labels['newkeyident'] = 'Identité :';
$labels['newkeypass'] = 'Mot de passe :';
$labels['newkeypassconfirm'] = 'Confirmer le mot de passe :';
$labels['newkeysize'] = 'Taille de la clé :';
$labels['key2048'] = '2048 bits - par défaut';
$labels['key4096'] = '4096 bits - plus sécurisé';
$labels['keygenerating'] = 'Génération des clés...';
$labels['encryptionoptions'] = 'Options de chiffrement...';
$labels['encryptmsg'] = 'Chiffrer ce courriel';
$labels['signmsg'] = 'Signer numériquement ce courriel';
$labels['enterkeypasstitle'] = 'Saisir la phrase de passe de la clé';
$labels['enterkeypass'] = 'Un phrase de passe est nécessaire pour débloquer la clé secrète ($keyid) pour l\'utilisateur : $user.';
$labels['arialabelkeyexportoptions'] = 'Options d\'exportation des clés';
$labels['attachpubkeymsg'] = 'Joindre ma clé publique';
$labels['keyexportprompt'] = 'Voulez-vous inclure les clés secrètes dans le fichier des clés OpenPGP enregistrées ?';
$labels['onlypubkeys'] = 'Exporter seulement les clés publiques';
$labels['withprivkeys'] = 'Exporter les clés secrètes';
$labels['findkey'] = 'Rechercher sur les serveurs de clés';
$labels['keyimportlabel'] = 'Importer d\'un fichier';
$labels['keyimportsearchlabel'] = 'Rechercher sur les serveurs de clés';
$messages['sigvalid'] = 'Signature vérifiée de $sender.';
$messages['sigvalidpartial'] = 'La signature de $sender a été vérifiée, mais une partie du corps n\'était pas signée.';
$messages['siginvalid'] = 'Signature invalide de $sender.';
$messages['sigunverified'] = 'Signature non vérifiée. Certificat non vérifié. ID de certificat : $keyid.';
$messages['signokey'] = 'Signature non vérifiée. Clé publique introuvable. ID de clé : $keyid.';
$messages['sigerror'] = 'Signature non vérifiée. Erreur interne.';
$messages['decryptok'] = 'Courriel déchiffré.';
$messages['decrypterror'] = 'Échec lors du déchiffrement.';
$messages['decryptnokey'] = 'Échec de déchiffrement. Clé privée introuvable. ID de clé : $keyid.';
$messages['decryptbadpass'] = 'Échec de déchiffrement. Le mot de passe est invalide.';
$messages['decryptnopass'] = 'Échec de déchiffrement. Le mot de passe de la clé est exigé.';
$messages['decryptpartial'] = 'Le courriel a été déchiffré, mais une partie du corps n\'était pas chiffré.';
$messages['signerror'] = 'Échec lors de la signature.';
$messages['signnokey'] = 'Échec lors de la signature. La clé privée est introuvable.';
$messages['signbadpass'] = 'Échec de signature. Le mot de passe est invalide.';
$messages['signnopass'] = 'Échec de signature. Le mot de passe de la clé est exigé.';
$messages['encrypterror'] = 'Échec lors du chiffrement.';
$messages['encryptnokey'] = 'Échec lors du chiffrement. La clé publique est introuvable pour $email.';
$messages['nokeysfound'] = 'Aucune clé trouvée';
$messages['keynotfound'] = 'Clé introuvable !';
$messages['keyopenerror'] = 'Impossible d\'obtenir les informations sur la clé ! Erreur interne.';
$messages['keylisterror'] = 'Impossible de lister les clés ! Erreur interne.';
$messages['keysimportfailed'] = 'Impossible d\'importer les clés ! Erreur interne.';
$messages['keysimportsuccess'] = 'Importation de clé réussie. Importées : $new, non modifiée(s) : $old.';
$messages['keyremoving'] = 'Suppression de des clés...';
$messages['keyremoveconfirm'] = 'Voulez-vous vraiment supprimer les clés sélectionnées ?';
$messages['keyremovesuccess'] = 'Clés supprimées avec succès';
$messages['keyremoveerror'] = 'Impossible de supprimer les clés sélectionnées';
$messages['keyimporttext'] = 'Vous pouvez importer des clés privées et publiques ou des signatures de révocation au format ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Vous pouvez chercher des clés publiques par identifiant de clé, nom d\'utilisateur ou adresse courriel, et ensuite les importer directement.';
$messages['formerror'] = 'Veuillez remplir le formulaire. Tous les champs sont requis !';
$messages['passwordsdiffer'] = 'Les mots de passe ne correspondent pas !';
$messages['keygenerateerror'] = 'La génération de la biclé a échoué';
$messages['keygeneratesuccess'] = 'Une biclé a été générée et importée avec succès.';
$messages['keygennosupport'] = 'Votre navigateur ne prend pas en charge le chiffrement. Activez-le pour générer une biclé !';
$messages['noidentselected'] = 'Vous devez choisir au moins une identité pour la clé !';
$messages['nonameident'] = 'Un nom d\'utilisateur doit être défini pour l\'identité !';
?>
