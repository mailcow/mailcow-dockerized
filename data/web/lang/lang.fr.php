<?php
/*
 * French language file
 */

$lang['footer']['loading'] = "Veuillez patienter...";
$lang['header']['restart_sogo'] = "Redémarrer SOGo";
$lang['footer']['restart_sogo'] = "Redémarrer SOGo";
$lang['footer']['restart_now'] = "Redémarrer maintenant";
$lang['footer']['restart_sogo_info'] = "Certaines taches, par exemple l'ajout d'un domaine, nécessite le redémarrage de SOGo afin de propager les changements faits dans mailcow UI.<br><br><b>Important :</b> un redémarrage doux peut prendre du temps à s'effectuer complètement, merci d'attendre qu'il se termine.";

$lang['footer']['confirm_delete'] = "Confirmer l'effacement";
$lang['footer']['delete_these_items'] = "Merci de confirmer les changements sur l'objet id suivant :";
$lang['footer']['delete_now'] = "Effacer maintenant";
$lang['footer']['cancel'] = "Annuler";

$lang['dkim']['confirm'] = "Êtes-vous sûr ?";
$lang['danger']['dkim_not_found'] = "Clé DKIM non trouvée";
$lang['danger']['dkim_remove_failed'] = "Impossible de retirer la clé DKIM sélectionnée";
$lang['danger']['dkim_add_failed'] = "Impossible d'ajouter la clé DKIM donnée";
$lang['danger']['dkim_domain_or_sel_invalid'] = "Domaine ou sélecteur DKIM invalide";
$lang['danger']['dkim_key_length_invalid'] = "Longueur de clé DKIM invalide";
$lang['success']['dkim_removed'] = "La clé DKIM %s a été retirée";
$lang['success']['dkim_added'] = "La clé DKIM a été enregistrée";
$lang['danger']['access_denied'] = "Accès refusé ou données de formulaire invalide";
$lang['danger']['whitelist_from_invalid'] = "L'enregistrement liste blanche a un format non valide";
$lang['danger']['domain_invalid'] = "Le nom de domaine n'est pas valide";
$lang['danger']['mailbox_quota_exceeds_domain_quota'] = "Le quota max. dépasse la limite de quota du domaine";
$lang['danger']['object_is_not_numeric'] = "La valeur %s n'est pas numérique";
$lang['success']['domain_added'] = "Domaine %s ajouté";
$lang['success']['items_deleted'] = "L'élément %s a été supprimé avec succès";
$lang['danger']['alias_empty'] = "L'adresse alias ne peut pas être vide";
$lang['danger']['last_key'] = "La dernière clé ne peut pas être effacée";
$lang['danger']['goto_empty'] = "L'adresse de destination ne peut pas être vide";
$lang['danger']['policy_list_from_exists'] = "Une entrée avec ce nom existe";
$lang['danger']['policy_list_from_invalid'] = "L'entrée a un format non valide";
$lang['danger']['whitelist_exists'] = "Une entrée liste-blanche existe avec ce nom";
$lang['danger']['alias_invalid'] = "L'adresse alias est non valide";
$lang['danger']['goto_invalid'] = "L'adresse de destination est non valide";
$lang['danger']['alias_domain_invalid'] = "Le domaine alias est non valide";
$lang['danger']['target_domain_invalid'] = "Le domaine de destination est non valide";
$lang['danger']['object_exists'] = "L'objet %s existe déjà";
$lang['danger']['domain_exists'] = "Le domaine %s existe déjà";
$lang['danger']['alias_goto_identical'] = "L'alias et l'adresse de destination ne peuvent pas être identiques";
$lang['danger']['aliasd_targetd_identical'] = "Le domaine alias ne peut pas être le domaine cible";
$lang['danger']['maxquota_empty'] = "Le quota max. par boîte ne peut pas être 0.";
$lang['success']['alias_added'] = "L(es)'adresse(s) a(ont) été ajoutée(s)";
$lang['success']['alias_modified'] = "Les changements sur les alias %s ont été enregistrés";
$lang['success']['aliasd_modified'] = "Les changements du domaine alias %s ont été enregistrés";
$lang['success']['mailbox_modified'] = "Les changement sur la boîte %s ont été enregistrés";
$lang['success']['resource_modified'] = "Les changement sur la boîte %s ont été enregistrés";
$lang['success']['object_modified'] = "Les changement sur l'objet %s ont été enregistrés";
$lang['success']['f2b_modified'] = "Les changement sur les paramètres Fail2ban ont été enregistrés";
$lang['success']['msg_size_saved'] = "La limite de taille de message a été définie";
$lang['danger']['aliasd_not_found'] = "Domaine alias non trouvé";
$lang['danger']['targetd_not_found'] = "Domaine cible non trouvé";
$lang['danger']['aliasd_exists'] = "Le domaine alias existe déjà";
$lang['success']['aliasd_added'] = "Domaine alias %s ajouté";
$lang['success']['domain_modified'] = "Les changement sur le domaine %s ont été enregistrés";
$lang['success']['domain_admin_modified'] = "Les changement sur l'administrateur de domaine %s ont été enregistrés";
$lang['success']['domain_admin_added'] = "L'administrateur de domaine %s a été ajouté";
$lang['success']['changes_general'] = "Les changements ont été enregistrés";
$lang['success']['admin_modified'] = "Les changements sur l'administrateur ont été enregistrés";
$lang['danger']['exit_code_not_null'] = "Erreur : Le code de sortie était %d";
$lang['danger']['mailbox_not_available'] = "Boîte non disponible";
$lang['danger']['username_invalid'] = "L'identifiant ne peut pas être utilisé";
$lang['danger']['password_mismatch'] = "La confirmation du mot de passe n'est pas identique";
$lang['danger']['password_complexity'] = "Le mot de passe ne respecte pas les règles";
$lang['danger']['password_empty'] = "Le mot de passe ne peut pas être vide";
$lang['danger']['login_failed'] = "La connexion a échoué";
$lang['danger']['mailbox_invalid'] = "Le nom de la boîte n'est pas valide";
$lang['danger']['description_invalid'] = "La description de la ressource n'est pas valide";
$lang['danger']['resource_invalid'] = "Le nom de la ressource n'est pas valide";
$lang['danger']['mailbox_invalid_suggest'] = "Le nom de la boîte n'est pas valide. Vouliez-vous taper \"%s\" ?";
$lang['danger']['is_alias'] = "%s est déjà enregistré comme adresse alias";
$lang['danger']['is_alias_or_mailbox'] = "%s est déjà enregistré comme un alias ou une boîte";
$lang['danger']['is_spam_alias'] = "%s est déjà enregistré comme une adresse alias de pourriel";
$lang['danger']['quota_not_0_not_numeric'] = "Le quota doit être numérique et >= 0";
$lang['danger']['domain_not_found'] = "Domaine %s non trouvé";
$lang['danger']['max_mailbox_exceeded'] = "Boîtes max. dépassé (%d sur %d)";
$lang['danger']['max_alias_exceeded'] = "Alias max. dépassé";
$lang['danger']['mailbox_quota_exceeded'] = "Le quota dépasse la limite du domaine (max. %d Mo)";
$lang['danger']['mailbox_quota_left_exceeded'] = "Pas assez d'espace disponible (espace disponible : %d Mo)";
$lang['success']['mailbox_added'] = "La boîte %s a été ajoutée";
$lang['success']['resource_added'] = "La ressource %s a été ajoutée";
$lang['success']['domain_removed'] = "Le domaine %s a été retiré";
$lang['success']['alias_removed'] = "L'alias %s a été retiré";
$lang['success']['alias_domain_removed'] = "L'alias de domaine %s a été retiré";
$lang['success']['domain_admin_removed'] = "L'administrateur de domaine %s a été retiré";
$lang['success']['mailbox_removed'] = "La boîte %s a été retirée";
$lang['success']['eas_reset'] = "Les appareil ActiveSync de l'utilisateur %s ont été réinitialisés";
$lang['success']['resource_removed'] = "La ressource %s a été retirée";
$lang['danger']['max_quota_in_use'] = "Le quota de la boîte doit être supérieur ou égal à %d Mo";
$lang['danger']['domain_quota_m_in_use'] = "Le quota du domaine doit être supérieur ou égal à %d Mo";
$lang['danger']['mailboxes_in_use'] = "Boîtes max. doit être doit être supérieur ou égal à %d";
$lang['danger']['aliases_in_use'] = "Alias max. doit être supérieur ou égal à %d";
$lang['danger']['sender_acl_invalid'] = "La valeur ACL de l'expéditeur n'est pas valide";
$lang['danger']['domain_not_empty'] = "Impossible de retiré un domaine non-vide";
$lang['warning']['spam_alias_temp_error'] = "Erreur temporaire : impossible d'ajouter des alias de spam, merci d'essayer plus tard.";
$lang['danger']['spam_alias_max_exceeded'] = "Nombre max d'adresses pour les alias de spam dépassé";
$lang['danger']['validity_missing'] = "Merci d'attribuer une période de validité";
$lang['user']['on'] = "On";
$lang['user']['off'] = "Off";
$lang['user']['messages'] = "messages"; // "123 messages"
$lang['user']['in_use'] = "Utilisé";
$lang['user']['user_change_fn'] = "";
$lang['user']['user_settings'] = "Paramètres utilisateur";
$lang['user']['mailbox_settings'] = "Paramètres des boîtes";
$lang['user']['mailbox_details'] = "Détails des boîtes";
$lang['user']['change_password'] = "Changement de mot de passe";
$lang['user']['client_configuration'] = "Montrer les guides de configuration pour les programmes de courriels et les smartphones";
$lang['user']['new_password'] = "Nouveau mot de passe";
$lang['user']['save_changes'] = "Sauvegarder les changements";
$lang['user']['password_now'] = "Mot de passe actuel (confirmation des changements)";
$lang['user']['new_password_repeat'] = "Confirmation du mot de passe (répéter)";
$lang['user']['new_password_description'] = "Critère : 6 caractères de long, lettres et nombres.";
$lang['user']['did_you_know'] = "<b>Le saviez-vous ?</b> Vous pouvez utiliser des étiquettes dans vos adresses de courriel (\"moi+<b>prive</b>@exemple.com\") afin de déplacer les messages automatiquement vers un dossier (exemple: \"prive\").";
$lang['user']['spam_aliases'] = "Alias de courriel temporaires";
$lang['user']['alias'] = "Alias";
$lang['user']['aliases'] = "Alias";
$lang['user']['domain_aliases'] = "Adresses d'alias de domaine";
$lang['user']['is_catch_all'] = "Attrape-tout pour domaine(s)";
$lang['user']['aliases_also_send_as'] = "Peut également envoyer en tant qu'utilisateur";
$lang['user']['aliases_send_as_all'] = "Ne pas vérifier les accès d'expéditeur pour le(s) domaine(s) suivant(s) et leurs alias de domaine";
$lang['user']['alias_create_random'] = "Générer des alias aléatoires";
$lang['user']['alias_extend_all'] = "Prolonger l'alias pour 1 heure";
$lang['user']['alias_valid_until'] = "Valide jusqu'à";
$lang['user']['alias_remove_all'] = "Retirer tous les alias";
$lang['user']['alias_time_left'] = "Temps restant";
$lang['user']['alias_full_date'] = "d/m/Y, H:i:s T";
$lang['user']['syncjob_full_date'] = "d/m/Y, H:i:s T";
$lang['user']['alias_select_validity'] = "Période de validité";
$lang['user']['sync_jobs'] = "Travaux de synchronisation";
$lang['user']['hour'] = "Heure";
$lang['user']['hours'] = "Heures";
$lang['user']['day'] = "Jour";
$lang['user']['week'] = "Semaine";
$lang['user']['weeks'] = "Semaines";
$lang['user']['spamfilter'] = "Filtre de pourriel";
$lang['admin']['spamfilter'] = "Filtre de pourriel";
$lang['user']['spamfilter_wl'] = "Liste blanche";
$lang['user']['spamfilter_wl_desc'] = "Les adresses de courriel en liste blanche ne sont <b>jamais</b> classifiées en pourriel. Des caractères génériques peuvent être utilisés.";
$lang['user']['spamfilter_bl'] = "Liste noire";
$lang['user']['spamfilter_bl_desc'] = "Les adresses de courriel en liste noire ne sont <b>toujours</b> classifiées en pourriel. Des caractères génériques peuvent être utilisés.";
$lang['user']['spamfilter_behavior'] = "Note";
$lang['user']['spamfilter_table_rule'] = "Règle";
$lang['user']['spamfilter_table_action'] = "Action";
$lang['user']['spamfilter_table_empty'] = "Aucune donnée à afficher";
$lang['user']['spamfilter_table_remove'] = "retirer";
$lang['user']['spamfilter_table_add'] = "Ajouter un élément";
$lang['user']['spamfilter_default_score'] = "Valeurs par défaut :";
$lang['user']['spamfilter_green'] = "Vert : ce message n'est pas un pourriel";
$lang['user']['spamfilter_yellow'] = "Jaune : ce message peut être un pourriel. Il sera étiqueté en tant que pourriel et déplacé dans le dossier pourriel";
$lang['user']['spamfilter_red'] = "Rouge: Ce message est un pourriel et sera rejeté par le serveur";
$lang['user']['spamfilter_hint'] = "La première valeur décrit le \"score bas de pourriel\", la seconde représente le \"score haut de pourriel\".";
$lang['user']['spamfilter_table_domain_policy'] = "N/D (Politique du domaine)";

$lang['user']['tls_policy_warning'] = "<strong>Attention :<strong> Si vous décidez d'imposer le chiffrement des échanges de courriel, vous pouvez perdre des messages.<br> Les messages qui ne respecte pas la politique seront rejetés avec un message d'erreur définitif par le système de courriel.<br> Cette option s'applique à votre adresse de courriel principale (identifiant de connexion), tous les alias de domaine ainsi que les alias d'adresse <b> qui n'ont que cette unique boîte</b> comme destinataire.";
$lang['user']['tls_policy'] = "Politique de chiffrement";
$lang['user']['tls_enforce_in'] = "Imposer le TLS entrant";
$lang['user']['tls_enforce_out'] = "Imposer le TLS sortant";
$lang['user']['no_record'] = "Aucun enregistrement";

$lang['user']['misc_settings'] = "Autre paramètres de profil";
$lang['user']['misc_delete_profile'] = "Autre paramètres de profil";

$lang['user']['tag_handling'] = "Définir la gestion des courriel étiquetés";
$lang['user']['tag_in_subfolder'] = "Dans un sous-dossier";
$lang['user']['tag_in_subject'] = "Dans l'objet";
$lang['user']['tag_in_none'] = "Ne fais rien";
$lang['user']['tag_help_explain'] = "Dans un sous-dossier : a nouveau sous-dossier portant le nom de l'étiquette sera crée sous INBOX (\"INBOX/Facebook\").<br>Dans l'objet : le nom de l'étiquette sera accolé à l'objet du courriel. Par exemple : \"[Facebook] Mes Nouvelles\".";
$lang['user']['tag_help_example'] = "Exemple pour une adresse de courriel étiquetée : moi<b>+Facebook</b>@exemple.org";
$lang['user']['eas_reset'] = "Réinitialiser le cache de l'appareil ActiveSync";
$lang['user']['eas_reset_now'] = "Réinitialiser maintenant";
$lang['user']['eas_reset_help'] = "Dans beaucoup de cas, une réinitialisation du cache de l'appareil aidera à solutioner un profile ActiveSync cassé.<br><b>Attention :</b> Tous les éléments seront téléchargés à nouveau !";

$lang['user']['encryption'] = "Chiffrement";
$lang['user']['username'] = "Nom d'utilisateur";
$lang['user']['password'] = "Mot de passe";
$lang['user']['last_run'] = "Dernière exécution";
$lang['user']['excludes'] = "Exclu";
$lang['user']['interval'] = "Intervalle";
$lang['user']['active'] = "Actif";
$lang['user']['action'] = "Action";
$lang['user']['edit'] = "Éditer";
$lang['user']['remove'] = "Retirer";
$lang['user']['delete_now'] = "Retirer maintenant";
$lang['user']['create_syncjob'] = "Création d'une nouvelle tache de synchronisation";

$lang['start']['dashboard'] = "%s - tableau de bord";
$lang['start']['start_rc'] = "Ouvrir Roundcube";
$lang['start']['start_sogo'] = "Ouvrir SOGo";
$lang['start']['mailcow_apps_detail'] = "Utiliser l'application mailcow pour accéder à vos courriels, calendriers, contacts et bien plus.";
$lang['start']['mailcow_panel'] = "Démarrer l'interface mailcow";
$lang['start']['mailcow_panel_description'] = "L'interface mailcow est disponible pour les administrateurs et les utilisateurs de boîtes de courriels.";
$lang['start']['mailcow_panel_detail'] = "Les <b>administrateurs de domaine</b> créent, modifient ou effacent les boîtes de courriel et les alias. Ils modifient les domaines et ont accès aux informations à propos des domaines qui leur sont assignés.<br>Les <b>utilisateurs de boîtes</b> peuvent créer des alias limités dans le temps (alias de pourriel), changer leur mot de passe et leurs préférences de filtre à pourriel.";
$lang['start']['recommended_config'] = "Configuration recommandée (sans ActiveSync)";
$lang['start']['imap_smtp_server'] = "Donnée des serveurs IMAP et SMTP";
$lang['start']['imap_smtp_server_description'] = "Pour une expérience optimale, nous vous recommandons d'utiliser <a href=\"%s\" target=\"_blank\"><b>Mozilla Thunderbird</b></a>.";
$lang['start']['imap_smtp_server_badge'] = "Lecture/Écriture des courriels";
$lang['start']['imap_smtp_server_auth_info'] = "Merci d'utiliser votre adresse de courriel complète couplée au mécanisme d’authentification \"PLAIN\".<br>Vos données de connexion seront chiffrées par le chiffrement imposé coté serveur.";
$lang['start']['managesieve'] = "ManageSieve";
$lang['start']['managesieve_badge'] = "Filtre de courriel";
$lang['start']['managesieve_description'] = "Merci d'utiliser <b>Mozilla Thunderbird</b> avec l'extension <a style=\"text-decoration:none\" target=\"_blank\" href=\"%s\"><b>nightly sieve</b></a>.<br>Démarrez Thunderbird, ouvrez les paramètres des extensions et déposez le fichier xpi fraîchement téléchargé dans la fenêtre ouverte.<br>Le nom du serveur est <b>%s</b>, utilisez le port <b>4190</b> si on vous le demande. Les données de connexion correspondent à celles de votre courriel.";
$lang['start']['service'] = "Service";
$lang['start']['encryption'] = "Méthode de chiffrement";
$lang['start']['help'] = "Montrer/Cacher le panneau d'aide";
$lang['start']['hostname'] = "Nom d'hôte";
$lang['start']['port'] = "Port";
$lang['start']['footer'] = "";
$lang['header']['mailcow_settings'] = "Configuration";
$lang['header']['administration'] = "Administration";
$lang['header']['mailboxes'] = "Boîtes de courriel";
$lang['header']['user_settings'] = "Paramètres utilisateur";
$lang['header']['login'] = "Identifiant";
$lang['header']['logged_in_as_logout'] = "Connecté en tant que <b>%s</b> (déconnexion)";
$lang['header']['logged_in_as_logout_dual'] = "Connecté en tant que <b>%s <span class=\"text-info\">[%s]</span></b>";
$lang['header']['locale'] = "Langue";
$lang['mailbox']['domain'] = "Domaine";
$lang['mailbox']['spam_aliases'] = "Alias temp.";
$lang['mailbox']['multiple_bookings'] = "Réservations multiples";
$lang['mailbox']['kind'] = "Type";
$lang['mailbox']['description'] = "Description";
$lang['mailbox']['alias'] = "Alias";
$lang['mailbox']['resource_name'] = "Nom des ressources";
$lang['mailbox']['aliases'] = "Alias";
$lang['mailbox']['domains'] = "Domaines";
$lang['mailbox']['mailboxes'] = "Boîtes";
$lang['mailbox']['resources'] = "Ressources";
$lang['mailbox']['mailbox_quota'] = "Taille max. d'une boîte";
$lang['mailbox']['domain_quota'] = "Quota";
$lang['mailbox']['active'] = "Actif";
$lang['mailbox']['action'] = "Action";
$lang['mailbox']['ratelimit'] = "Limite de vitesse en sortie/h";
$lang['mailbox']['backup_mx'] = "MX de secours";
$lang['mailbox']['domain_aliases'] = "Alias de domaine";
$lang['mailbox']['target_domain'] = "Domaine cible";
$lang['mailbox']['target_address'] = "Adresse cible";
$lang['mailbox']['username'] = "Identifiant";
$lang['mailbox']['fname'] = "Nom complet";
$lang['mailbox']['filter_table'] = "Table de filtrage";
$lang['mailbox']['yes'] = "&#10004;";
$lang['mailbox']['no'] = "&#10008;";
$lang['mailbox']['quota'] = "Quota";
$lang['mailbox']['in_use'] = "Utilisation (%)";
$lang['mailbox']['msg_num'] = "Message";
$lang['mailbox']['remove'] = "Retirer";
$lang['mailbox']['edit'] = "Éditer";
$lang['mailbox']['archive'] = "Archiver";
$lang['mailbox']['no_record'] = "Aucun enregistrement pour l'objet %s";
$lang['mailbox']['no_record_single'] = "Aucun enregistrement";
$lang['mailbox']['add_domain'] = "Ajouter un domaine";
$lang['mailbox']['add_domain_alias'] = "Ajouter un alias de domaine";
$lang['mailbox']['add_mailbox'] = "Ajouter une boîte de courriel";
$lang['mailbox']['add_resource'] = "Ajouter une ressource";
$lang['mailbox']['add_alias'] = "Ajouter un alias";
$lang['mailbox']['add_domain_record_first'] = "Merci de d'abord ajouter un domaine";
$lang['mailbox']['empty'] = "Aucun résultat";
$lang['mailbox']['toggle_all'] = "Basculer tout";
$lang['mailbox']['quick_actions'] = "Actions";
$lang['mailbox']['activate'] = "Activer";
$lang['mailbox']['deactivate'] = "Désactiver";
$lang['mailbox']['owner'] = "Propriétaire";
$lang['mailbox']['mins_interval'] = "Intervalle (min)";
$lang['mailbox']['last_run'] = "Dernière exécution";

$lang['info']['no_action'] = "Aucune action applicable";

$lang['delete']['title'] = "Retirer un objet";
$lang['delete']['remove_domain_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer le domaine <b>%s</b> !";
$lang['delete']['remove_syncjob_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer une tâche de synchronisation pour l'utilisateur <b>%s</b> !";
$lang['delete']['remove_domainalias_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer l'alias de domaine <b>%s</b> !";
$lang['delete']['remove_domainadmin_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer l'administrateur de domaine <b>%s</b> !";
$lang['delete']['remove_alias_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer l'alias d'adresse <b>%s</b> !";
$lang['delete']['remove_mailbox_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer la boîte de courriel <b>%s</b> !";
$lang['delete']['remove_mailbox_details'] = "La boîte de courriel sera <b>supprimée définitivement</b> !";
$lang['delete']['remove_resource_warning'] = "<b>Attention :</b> vous êtes sur le point de retirer la ressource <b>%s</b> !";
$lang['delete']['remove_resource_details'] = "La ressource sera <b>supprimée définitivement</b> !";
$lang['delete']['remove_domain_details'] = "Cela retire également les alias de domaine.<br><br><b>Un domaine doit être vide pour être retiré.</b>";
$lang['delete']['remove_syncjob_details'] = "Les objets de cette tâche de synchronisation ne seront plus récupérés depuis le serveur distant.";
$lang['delete']['remove_alias_details'] = "Les utilisateurs ne pourront plus recevoir ni envoyer de courriels depuis cette adresse.</b>";
$lang['delete']['remove_button'] = "Retirer";
$lang['delete']['previous'] = "Page précédente";

$lang['edit']['syncjob'] = "Éditer la tâche de synchronisation";
$lang['edit']['save'] = "Enregistrer les changements";
$lang['edit']['username'] = "Identifiant";
$lang['edit']['hostname'] = "Nom d'hôte";
$lang['edit']['encryption'] = "Chiffrement";
$lang['edit']['maxage'] = "Age maximum en jours des messages qui seront récupérés depuis le serveur distant<br><small>(0 = ignorer l'age)</small>";
$lang['edit']['subfolder2'] = "Sous-dossier où synchroniser à destination<br><small>(vide = ne pas utiliser de sous-dossier)</small>";
$lang['edit']['mins_interval'] = "Intervalle (min)";
$lang['edit']['exclude'] = "Objets à exclure (expression régulière)";
$lang['edit']['archive'] = "Accès aux archives";
$lang['edit']['max_mailboxes'] = "Nombre max. de boîtes";
$lang['edit']['title'] = "Éditer l'objet";
$lang['edit']['target_address'] = "Adresse(s) de destination <small>(séparés par des virgules)</small>";
$lang['edit']['active'] = "Activer";
$lang['edit']['target_domain'] = "Domaine cible";
$lang['edit']['password'] = "Mot de passe";
$lang['edit']['ratelimit'] = "Limite de vitesse sortante/h";
$lang['danger']['ratelimt_less_one'] = "La limite de vitesse sortante/h ne peut pas être inférieure à 1";
$lang['edit']['password_repeat'] = "Confirmation du mot de passe (répéter)";
$lang['edit']['domain_admin'] = "Éditer l'administrateur de domaine";
$lang['edit']['domain'] = "Éditer le domaine";
$lang['edit']['alias_domain'] = "Alias de domaine";
$lang['edit']['edit_alias_domain'] = "Éditer l'alias de domaine";
$lang['edit']['domains'] = "Domaines";
$lang['edit']['destroy'] = "Entrée des donnés manuelle";
$lang['edit']['alias'] = "Éditer l'alias";
$lang['edit']['mailbox'] = "Éditer la boîte";
$lang['edit']['description'] = "Description";
$lang['edit']['max_aliases'] = "Alias max.";
$lang['edit']['max_quota'] = "Quota max. par boîte (Mo)";
$lang['edit']['domain_quota'] = "Quota de domaine";
$lang['edit']['backup_mx_options'] = "Option de MX secondaire";
$lang['edit']['relay_domain'] = "Domaine de relais";
$lang['edit']['relay_all'] = "Relayer tous les destinataires";
$lang['edit']['dkim_signature'] = "Signature ARC + DKIM";
$lang['edit']['dkim_record_info'] = "<small>Merci d'ajouter une entrée TXT avec la valeur donnée à votre configuration DNS.</small>";
$lang['edit']['relay_all_info'] = "<small>Si vous choisissez de ne <b>pas</b> relayer tous les destinataires, vous devrez ajouter une boîte \"aveugle\" pour chaque destinataire qui doit être relayé.</small>";
$lang['edit']['full_name'] = "Nom complet";
$lang['edit']['quota_mb'] = "Quota (Mo)";
$lang['edit']['sender_acl'] = "Autorisé à envoyé en tant que";
$lang['edit']['sender_acl_info'] = "Les alias ne peuvent pas être désélectionnés.";
$lang['edit']['dkim_txt_name'] = "Nom de l'enregistrement TXT :";
$lang['edit']['dkim_txt_value'] = "Valeur de l'enregistrement TXT :";
$lang['edit']['previous'] = "Page précédente";
$lang['edit']['unchanged_if_empty'] = "Si aucun changement, laisser vide";
$lang['edit']['dont_check_sender_acl'] = "Désactiver la vérification de l'émetteur pour le domaine %s + les domaines alias";
$lang['edit']['multiple_bookings'] = "Inscriptions multiples";
$lang['edit']['kind'] = "Type";
$lang['edit']['resource'] = "Resource";
$lang['edit']['goto_null'] = "Ignorer silencieusement le courriel";

$lang['add']['syncjob'] = "Ajouter une tâche de synchronisation";
$lang['add']['syncjob_hint'] = "Soyez prévenus que les mots de passe doivent être sauvés en clair !";
$lang['add']['hostname'] = "Nom d'hôte";
$lang['add']['port'] = "Port";
$lang['add']['username'] = "Identifiant";
$lang['add']['enc_method'] = "Méthode de chiffrement";
$lang['add']['mins_interval'] = "Période de relève (minutes)";
$lang['add']['maxage'] = "Age maximum des messages qui seront relevés depuis le distant (0 = ignorer l'age)";
$lang['add']['subfolder2'] = "Synchronisation dans un sous-dossier à destination";
$lang['add']['exclude'] = "Exclure des objets (expression régulière)";
$lang['add']['delete2duplicates'] = "Supprimer les doubles à destination";
$lang['add']['delete1'] = "Supprimer à la source une fois terminé";
$lang['add']['delete2'] = "Supprimer les message à destination qui ne sont pas présent à la source";
$lang['edit']['delete2duplicates'] = "Supprimer les doubles à destination";
$lang['edit']['delete1'] = "Supprimer à la source une fois terminé";
$lang['edit']['delete2'] = "Supprimer les messages à destination qui ne sont pas présent à la source";

$lang['add']['title'] = "Ajouter un objet";
$lang['add']['domain'] = "Domaine";
$lang['add']['active'] = "Actif";
$lang['add']['multiple_bookings'] = "Inscriptions multiples";
$lang['add']['save'] = "Sauvegarder les changements";
$lang['add']['description'] = "Description";
$lang['add']['max_aliases'] = "Nombre max. des alias";
$lang['add']['resource_name'] = "Nom de la ressource";
$lang['add']['max_mailboxes'] = "Nombre max. de boîtes";
$lang['add']['mailbox_quota_m'] = "Quota max par boîte (Mo)";
$lang['add']['domain_quota_m'] = "Quota total du domaine (Mo)";
$lang['add']['backup_mx_options'] = "Options de MX secondaire";
$lang['add']['relay_all'] = "Relayer tous les destinataires";
$lang['add']['relay_domain'] = "Relayer ce domaine";
$lang['add']['relay_all_info'] = "<small>Si vous choisissez de ne <b>pas</b> relayer tous les destinataires, vous devrez ajouter une boîte \"aveugle\" pour chaque destinataire qui doit être relayé.</small>";
$lang['add']['alias'] = "Alias";
$lang['add']['alias_spf_fail'] = "<b>Note :</b> Si l'adresse de destination choisie est une boîte externe, la <b>serveur de réception</b> peut rejeter votre message pour cause d'échec SPF.</a>";
$lang['add']['alias_address'] = "Adresse(s) alias";
$lang['add']['alias_address_info'] = "<small>Adresse de courriel complète ou @exemple.com pour recevoir tous les messages d'un domaine (séparés par de virgules). <b>Domaines mailcow seulement</b></small>";
$lang['add']['alias_domain_info'] = "<small>Nom de domaine valide seulement (séparés par des virgules).</small>";
$lang['add']['target_address'] = "Adresse de destination";
$lang['add']['target_address_info'] = "<small>Adresse(s) de courriel complète(s) (séparées par des virgules).</small>";
$lang['add']['alias_domain'] = "Alias de domaine";
$lang['add']['select'] = "Merci de sélectionner...";
$lang['add']['target_domain'] = "Domaine cible";
$lang['add']['mailbox'] = "Boîte de courriel";
$lang['add']['resource'] = "Ressource";
$lang['add']['kind'] = "Type";
$lang['add']['mailbox_username'] = "Identifiant (côté gauche d'une adresse de courriel)";
$lang['add']['full_name'] = "Nom complet";
$lang['add']['quota_mb'] = "Quota (Mo)";
$lang['add']['select_domain'] = "Merci de d'abord sélectionner un domaine";
$lang['add']['password'] = "Mot de passe";
$lang['add']['password_repeat'] = "Confirmation du mot de passe (répéter)";
$lang['add']['previous'] = "Page précédente";
$lang['add']['restart_sogo_hint'] = "Vous devrez redémarrer le container du service SOGo après l'ajout d'un nouveau domaine !";
$lang['add']['goto_null'] = "Ignorer silencieusement le courriel";

$lang['login']['title'] = "Connexion";
$lang['login']['administration'] = "Administration";
$lang['login']['administration_details'] = "Merci d'utiliser votre identifiant d'administrateur pour effectuer les taches administratives.";
$lang['login']['user_settings'] = "Paramètres utilisateur";
$lang['login']['user_settings_details'] = "Les propriétaires de boîte de courriel peuvent utiliser l'interface mailcow pour changer leur mot de passe, créer des alias temporaires (alias de pourriel), ajuster le comportement du filtre à pourriel ou importer des messages depuis un serveur IMAP distant.";
$lang['login']['username'] = "Identifiant";
$lang['login']['password'] = "Mot de passe";
$lang['login']['reset_password'] = "Réinitialiser mon mot de passe";
$lang['login']['login'] = "Connexion";
$lang['login']['previous'] = "Page précédente";
$lang['login']['delayed'] = "La connexion a été différée de %s seconds.";

$lang['tfa']['tfa'] = "Authentification à double facteur";
$lang['tfa']['set_tfa'] = "Définir la méthode d'authentification à double facteur";
$lang['tfa']['yubi_otp'] = "Authentification Yubico OTP";
$lang['tfa']['key_id'] = "Un identifiant pour votre YubiKey";
$lang['tfa']['key_id_totp'] = "Un identifiant pour votre clé";
$lang['tfa']['api_register'] = "mailcow utilise Yubico Cloud API. Merci d'obtenir un clé API pour votre clé <a href=\"https://upgrade.yubico.com/getapikey/\" target=\"_blank\">ici</a>";
$lang['tfa']['u2f'] = "Authentification U2F";
$lang['tfa']['hotp'] = "Authentification HOTP";
$lang['tfa']['totp'] = "Mot de passe à usage unique basé sur le temps (Google Authenticator etc.)";
$lang['tfa']['none'] = "Désactivé";
$lang['tfa']['delete_tfa'] = "Invalider TFA";
$lang['tfa']['disable_tfa'] = "Désactiver TFA jusqu'à la prochaine connexion réussie";
$lang['tfa']['confirm_tfa'] = "Merci de confirmer votre mot de passe à usage unique dans le champ ci-dessous";
$lang['tfa']['confirm'] = "Confirmer";
$lang['tfa']['otp'] = "Mot de passe à usage unique";
$lang['tfa']['trash_login'] = "Trash login";
$lang['tfa']['select'] = "Merci de sélectionner";
$lang['tfa']['waiting_usb_auth'] = "<i>En attente du périphérique USB...</i><br><br>Merci de presser le bouton de votre périphérique U2F USB maintenant.";
$lang['tfa']['waiting_usb_register'] = "<i>En attente du périphérique USB...</i><br><br>Merci de confirmer votre mot de passe au dessus et de valider votre enregistrement U2F en appuyant sur le bouton de votre périphérique U2F USB.";
$lang['tfa']['scan_qr_code'] = "Merci de scanner le code suivant avec votre application d'authentification ou de saisir le code manuellement.";
$lang['tfa']['enter_qr_code'] = "Votre code TOTP si votre appareil ne peut pas scanner les codes QR";
$lang['tfa']['confirm_totp_token'] = "Merci de confirmer les changements en saisissant le jeton généré";

$lang['admin']['private_key'] = "Clé privée";
$lang['admin']['import'] = "Importer";
$lang['admin']['import_private_key'] = "Importer la clé privée";
$lang['admin']['f2b_parameters'] = "Paramètres Fail2ban";
$lang['admin']['f2b_ban_time'] = "Temps de bannissement (s)";
$lang['admin']['f2b_max_attempts'] = "Nb max de tentatives";
$lang['admin']['f2b_retry_window'] = "Fenêtre de nouvel essai (s) pour le nb max de tentatives";
$lang['admin']['f2b_whitelist'] = "Réseau/hôtes en liste blanche";
$lang['admin']['search_domain_da'] = "Chercher des domaines";
$lang['admin']['restrictions'] = "Restrictions Postfix";
$lang['admin']['rr'] = "Restrictions Postfix sur les destinataires";
$lang['admin']['sr'] = "Restriction Postfix sur les expéditeurs";
$lang['admin']['reset_defaults'] = "Réinitialiser aux valeurs par défaut";
$lang['admin']['r_inactive'] = "Restrictions inactives";
$lang['admin']['r_active'] = "Restrictions actives";
$lang['admin']['r_info'] = "Les éléments grisé/invalidés dans la liste des restrictions active ne sont pas reconnus comme des restrictions valide par mailcow et ne peuvent pas être déplacés. Les restriction inconnues seront enregistrées dans leur ordre d'apparence.<br>Vous pouvez ajouter de nouveaux élément dans <code>inc/vars.local.inc.php</code> pour pouvoir les sélectionner.";
$lang['admin']['public_folders'] = "Dossiers publiques";
$lang['admin']['public_folders_text'] = "Un espace de nommage \"Public\" a été crée. Le nom du dossier publique ci-dessous indique le nom de la première boîte crée automatiquement à l'intérieur de cet espace de nommage.";
$lang['admin']['public_folder_name'] = "Nom de dossier <small>(alphanumérique)</small>";
$lang['admin']['public_folder_enable'] = "Activer le dossier publique";
$lang['admin']['public_folder_enable_text'] = "Changer cette option ne supprime aucun courriel dans les dossiers publiques.";
$lang['admin']['public_folder_pusf'] = "Activer le statut vu par utilisateur";
$lang['admin']['public_folder_pusf_text'] = "Lorsque le \"statut vu par utilisateur\" est activé, le système ne marquera pas un courriel comme lu pour l'utilisateur B si l'utilisateur l'a lu mais pas B.";
$lang['admin']['privacy'] = "Vie privée";
$lang['admin']['privacy_text'] = "Cette option active une table PCRE pour retirer \"User-Agent\", \"X-Enigmail\", \"X-Mailer\", \"X-Originating-IP\" et remplace les en-têtes \"Received: from\" avec localhost/127.0.0.1.";
$lang['admin']['privacy_anon_mail'] = "Anonymiser les courriels sortants";
$lang['admin']['dkim_txt_name'] = "Nom de l'enregistrement TXT :";
$lang['admin']['dkim_txt_value'] = "Valeur de l'enregistrement TXT :";
$lang['admin']['dkim_key_length'] = "Longueur de la clé DKIM (bits)";
$lang['admin']['dkim_key_valid'] = "Clé valide";
$lang['admin']['dkim_key_unused'] = "Clé non utilisée";
$lang['admin']['dkim_key_missing'] = "Clé manquante";
$lang['admin']['dkim_key_hint'] = "Le sélecteur des clés DKIM est toujours <code>dkim</code>.";
$lang['admin']['previous'] = "Page précédente";
$lang['admin']['quota_mb'] = "Quota (Mo) :";
$lang['admin']['sender_acl'] = "Autoriser à envoyer en tant que :";
$lang['admin']['msg_size'] = "Taille du message";
$lang['admin']['msg_size_limit'] = "Taille de message limite actuelle";
$lang['admin']['msg_size_limit_details'] = "Appliquer une nouvelle limite rechargera Postfix et le serveur web.";
$lang['admin']['save'] = "Enregistrer les changements";
$lang['admin']['maintenance'] = "Maintenance et Information";
$lang['admin']['sys_info'] = "Information système";
$lang['admin']['dkim_add_key'] = "Ajouter un clé ARC/DKIM";
$lang['admin']['dkim_keys'] = "Clés ARC/DKIM";
$lang['admin']['add'] = "Ajouter";
$lang['admin']['configuration'] = "Configuration";
$lang['admin']['password'] = "Mot de passe";
$lang['admin']['password_repeat'] = "Confirmation du mot de passe (répéter)";
$lang['admin']['active'] = "Actif";
$lang['admin']['inactive'] = "Inactif";
$lang['admin']['action'] = "Action";
$lang['admin']['add_domain_admin'] = "Ajouter un administrateur de domaine";
$lang['admin']['admin_domains'] = "Affectation des domaines";
$lang['admin']['domain_admins'] = "Administrateurs de domaines";
$lang['admin']['username'] = "Identifiant";
$lang['admin']['edit'] = "Editer";
$lang['admin']['remove'] = "Retirer";
$lang['admin']['admin'] = "Administrateur";
$lang['admin']['admin_details'] = "Éditer les informations de l'administrateur";
$lang['admin']['unchanged_if_empty'] = "Si aucun changement, laisser vide";
$lang['admin']['yes'] = "&#10004;";
$lang['admin']['no'] = "&#10008;";
$lang['admin']['access'] = "Accès";
$lang['admin']['invalid_max_msg_size'] = "Mauvaise taille de message max.";
$lang['admin']['site_not_found'] = "Impossible de trouver la configuration du site mailcow";
$lang['admin']['public_folder_empty'] = "Le nom du dossier publique ne peut pas être vide";
$lang['admin']['set_rr_failed'] = "Impossible de définir les restrictions Postfix";
$lang['admin']['no_record'] = "Aucun enregistrement";
$lang['admin']['filter_table'] = "Table de filtrage";
$lang['admin']['empty'] = "Aucun résultat";
$lang['admin']['time'] = "Temps";
$lang['admin']['priority'] = "Priorité";
$lang['admin']['message'] = "Message";
$lang['admin']['refresh'] = "Rafraîchir";
$lang['admin']['to_top'] = "Retour en haut";
$lang['admin']['in_use_by'] = "Utilisé par";
$lang['admin']['logs'] = "Journaux";
$lang['admin']['forwarding_hosts'] = "Hôtes de réexpédition";
$lang['admin']['forwarding_hosts_hint'] = "Tous les messages entrant sont acceptés sans condition depuis les hôtes listés ici. Ces hôtes ne sont pas validés par DNSBLs ou sujets à un greylisting. Les pourriels reçus de ces hôtes ne sont jamais rejetés, mais occasionnellement, ils peuvent se retrouver dans le dossier Junk. L'usage le plus courant est pour les serveurs de courriels qui ont été configurés pour réexpédier leurs courriels entrant vers votre serveur mailcow.";
$lang['admin']['forwarding_hosts_add_hint'] = "Vous pouvez aussi bien indiquer des adresses IPv4/IPv6, des réseaux en notation CIDR, des noms d'hôtes (qui se seront convertit en adresses IP), ou des noms de domaine (qui seront convertit en adresses IP par une requête SPF ou, en son absence, l'enregistrement MX).";
$lang['admin']['relayhosts_hint'] = "Définissez les hôtes de relai pour pouvoir les sélectionner dans la fenêtre de configuration des domaines.";
$lang['admin']['add_relayhost_add_hint'] = "Sachez que les données d'authentification des hôtes de relai seront stockées en clair.";
$lang['admin']['host'] = "Hôte";
$lang['admin']['source'] = "Source";
$lang['admin']['add_forwarding_host'] = "Ajouter un hôte de réexpédition";
$lang['admin']['add_relayhost'] = "Ajouter un hôte de relai";
$lang['delete']['remove_forwardinghost_warning'] = "<b>Attention :</b>vous êtes sur le point de retirer l'hôte de réexpédition <b>%s</b> !";
$lang['success']['forwarding_host_removed'] = "L'hôte de réexpédition %s a été retiré";
$lang['success']['forwarding_host_added'] = "L'hôte de réexpédition %s a été ajouté";
$lang['success']['relayhost_removed'] = "L'hôte de relai %s a été retiré";
$lang['success']['relayhost_added'] = "L'hôte de relai %s a été ajouté";
$lang['admin']['relay_from'] = "\"From:\" adresse";
$lang['admin']['relay_run'] = "Test de fonctionnement";

$lang['admin']['customize'] = "Personnaliser";
$lang['admin']['change_logo'] = "Changer de logo";
$lang['admin']['logo_info'] = "Votre image sera redimensionnée à une hauteur de 40 pixels pour la barre de navigation du haut et à un maximum de 250 pixels en largeur pour la page d'accueil. Un graphique extensible est fortement recommandé.";
$lang['admin']['upload'] = "Télécharger";
$lang['admin']['app_links'] = "Liens vers les applications";
$lang['admin']['app_name'] = "Nom de l'application";
$lang['admin']['link'] = "Lien";
$lang['admin']['remove_row'] = "Retirer la ligne";
$lang['admin']['add_row'] = "Ajouter une ligne";
$lang['admin']['reset_default'] = "Remise à zéro par défaut";
$lang['admin']['merged_vars_hint'] = "Les lignes grisées ont été importées depuis <code>vars.inc.(local.)php</code> et ne peuvent pas être modifiées.";

$lang['edit']['tls_policy'] = "Changer la politique TLS";
$lang['edit']['spam_score'] = "Définir un score personnalisé de pourriel";
$lang['edit']['spam_policy'] = "Ajouter ou retirer des éléments des listes blanches/noires";
$lang['edit']['delimiter_action'] = "Modifier l'action des séparateurs";
$lang['edit']['syncjobs'] = "Ajouter ou modifier des tâches de synchronisation";
$lang['edit']['eas_reset'] = "Ré-initialisation des appareils EAS";
$lang['edit']['spam_alias'] = "Créer ou changer les adresses alias à temps limité";
