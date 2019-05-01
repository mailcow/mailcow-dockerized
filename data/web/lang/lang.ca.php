<?php
/*
 * Catalan language file
 */

$lang['footer']['loading'] = "Si et plau espera ...";
$lang['header']['restart_sogo'] = 'Reiniciar SOGo';
$lang['footer']['restart_sogo'] = 'Reiniciar SOGo';
$lang['footer']['restart_now'] = 'Reiniciar ara';
$lang['footer']['restart_container_info'] = '<b>Important:</b> Un reinici pot trigar una estona, si et plau espera a que acabi.';

$lang['footer']['confirm_delete'] = "Confirma l'esborrat ";
$lang['footer']['delete_these_items'] = 'Si et plau confirma els canvis al objecte amb id:';
$lang['footer']['delete_now'] = 'Esborrar ara';
$lang['footer']['cancel'] = 'Cancel·lar';

$lang['danger']['dkim_domain_or_sel_invalid'] = "Domini DKIM o selector incorrecte";
$lang['success']['dkim_removed'] = "La clau DKIM %s s'ha esborrat";
$lang['success']['dkim_added'] = "La clau DKIM s'ha desat";
$lang['danger']['access_denied'] = "Accés denegat o dades incorrectes";
$lang['danger']['domain_invalid'] = "Nom de domini incorrecte";
$lang['danger']['mailbox_quota_exceeds_domain_quota'] = "La quota màxima sobrepassa el límit del domini";
$lang['danger']['object_is_not_numeric'] = "El valor %s no és numèric";
$lang['success']['domain_added'] = "S'ha afegit el domini %s";
$lang['success']['items_deleted'] = "S'ha esborrat %s";
$lang['danger']['alias_empty'] = "L'adreça de l'àlias no es pot deixar buida";
$lang['danger']['last_key'] = 'No es pot esborrar la úlitma clau';
$lang['danger']['goto_empty'] = "L'adreça \"goto\" no es pot deixar buida";
$lang['danger']['policy_list_from_exists'] = "Ja existeix un registre amb aquest nom";
$lang['danger']['policy_list_from_invalid'] = "El registre no té un format vàlid";
$lang['danger']['alias_invalid'] = "L'adreça de l'alias és incorrecta";
$lang['danger']['goto_invalid'] = "L'adreça del \"goto\" és incorrecta";
$lang['danger']['alias_domain_invalid'] = "L'àlies del domini no és vàlid";
$lang['danger']['target_domain_invalid'] = "El domini \"goto\" no és vàlid";
$lang['danger']['object_exists'] = "L' objecte %s ja existeix";
$lang['danger']['domain_exists'] = "El domini %s ja existeix";
$lang['danger']['alias_goto_identical'] = "Les adreces d'àlies i 'goto' no poden ser iguals";
$lang['danger']['aliasd_targetd_identical'] = "El domini àlies no pot ser igual al de destí";
$lang['danger']['maxquota_empty'] = 'La quota màxima no pot ser 0.';
$lang['success']['alias_added'] = "S'ha afegit el/s àlies";
$lang['success']['alias_modified'] = "S'han desat els canvis fets al àlies";
$lang['success']['mailbox_modified'] = "S'han desat els canvis fets a la bústia %s";
$lang['success']['resource_modified'] = "S'han desat els canvis fets al recurs %s";
$lang['success']['object_modified'] = "S'han desat els canvis fets a l'objecte %s";
$lang['success']['f2b_modified'] = "S'han desat els canvis fets als parametres del Fail2ban";
$lang['danger']['targetd_not_found'] = "No s'ha trobat el domini destí";
$lang['success']['aliasd_added'] = "S'ha afegit l'àlies de domini %s";
$lang['success']['aliasd_modified'] = "S'han desat els canvis fets a l'àlies de domini %s";
$lang['success']['domain_modified'] = "S'han desat els canvis fets al domini %s";
$lang['success']['domain_admin_modified'] = "S'ha modificat l'administrador de dominis %s";
$lang['success']['domain_admin_added'] = "S'ha afegit l'administrador de dominis %s";
$lang['success']['admin_modified'] = "Els canvis fets a l'administrador s'han desat";
$lang['danger']['username_invalid'] = "El nom d'usuari no es pot fer servir";
$lang['danger']['password_mismatch'] = "La confirmació de contrasenya no encaixa";
$lang['danger']['password_complexity'] = "La contrasenya no compleix els requisits";
$lang['danger']['password_empty'] = "La contrasenya no es pot deixar en blanc";
$lang['danger']['login_failed'] = "L'inici de sessió ha fallat";
$lang['danger']['mailbox_invalid'] = "El nom de la bústia no és vàlid";
$lang['danger']['description_invalid'] = "La descripció del recurs no és vàlida";
$lang['danger']['resource_invalid'] = "El nom del recurs no és vàlid";
$lang['danger']['is_alias'] = "%s ja està definida com una direcció àlies";
$lang['danger']['is_alias_or_mailbox'] = "%s ja està definit como un àlies o una bústia";
$lang['danger']['is_spam_alias'] = "%s ja està definida com una adreça àlies de spam";
$lang['danger']['quota_not_0_not_numeric'] = "La quaota ha de ser numèrica i >= 0";
$lang['danger']['domain_not_found'] = "No s'ha trobat el domini";
$lang['danger']['max_mailbox_exceeded'] = "S'ha arribat al màxim de bústies (%d de %d)";
$lang['danger']['max_alias_exceeded'] = "S'ha arribat al màxim d'àlies";
$lang['danger']['mailbox_quota_exceeded'] = "La quota exedeix el límit del domini (màx. %d MiB)";
$lang['danger']['mailbox_quota_left_exceeded'] = "No queda espai suficient (espai lliure: %d MiB)";
$lang['success']['mailbox_added'] = "S'ha afegit la bústia %s";
$lang['success']['resource_added'] = "S'ha afegit el recurs %s";
$lang['success']['domain_removed'] = "S'ha elminat el domini %s";
$lang['success']['alias_removed'] = "S'ha esborrat l'àlies %s";
$lang['success']['alias_domain_removed'] = "S'ha esborrat l'àlies de domini %s";
$lang['success']['domain_admin_removed'] = "S'ha esborrat l'administrador de dominis %s";
$lang['success']['mailbox_removed'] = "S'ha esborrat la bústia %s";
$lang['success']['eas_reset'] = "S'ha fet un reset als dispositius ActiveSync de l'usuari %s";
$lang['success']['resource_removed'] = "S'ha esborrat el recurs %s";
$lang['danger']['max_quota_in_use'] = "La quota de la bústia ha de ser meś gran o igual a %d MiB";
$lang['danger']['domain_quota_m_in_use'] = "La quota del domini ha de ser més gran o igual a  %d MiB";
$lang['danger']['mailboxes_in_use'] = "El número màxim de bústies ha de ser més gran o igual a %d";
$lang['danger']['aliases_in_use'] = "El número màxim d'àlies ha de ser més gran o igual a %d";
$lang['danger']['sender_acl_invalid'] = "L'ACL d'emissor no és vàlid";
$lang['danger']['domain_not_empty'] = "No es pot esborrar un domini que no està buit";
$lang['danger']['validity_missing'] = 'Si et plau posa un període de validesa';
$lang['user']['loading'] = "Carregant...";
$lang['user']['force_pw_update'] = "<b>Has d'</b>establir una nova contrassenya per poder accedir.";
$lang['user']['active_sieve'] = "Filtre actiu";
$lang['user']['show_sieve_filters'] = "Mostra el filtre 'sieve' de l'usuari";
$lang['user']['no_active_filter'] = "Actualment no hi ha cap filtre";
$lang['user']['messages'] = "missatges"; // "123 messages"
$lang['user']['in_use'] = "Usat";
$lang['user']['user_change_fn'] = "";
$lang['user']['user_settings'] = "Configuració de l'usuari";
$lang['user']['mailbox_details'] = 'Detalls de la bústia';
$lang['user']['change_password'] = 'Canviar la contrasenya';
$lang['user']['client_configuration'] = 'Guies de configuració per als clients de correu més habituals';
$lang['user']['new_password'] = 'Contrasenya nova:';
$lang['user']['save_changes'] = 'Desar els canvis';
$lang['user']['password_now'] = 'Contrasenya actual (per validar els canvis):';
$lang['user']['new_password_repeat'] = 'Confirmació de la contrasenya nova:';
$lang['user']['new_password_description'] = 'Requisits: 6 caracters, lletres i números.';
$lang['user']['spam_aliases'] = "Àlies d'email temporals";
$lang['user']['alias'] = 'Àlies';
$lang['user']['shared_aliases'] = 'Adreces àlies compartides';
$lang['user']['shared_aliases_desc'] = "Un àlies compartit no es veu afectat per la configuració de l'usuari. L'administrador pot configurar un filtre de spam a nivell de domini.";
$lang['user']['direct_aliases'] = 'Adreces àlies directes';
$lang['user']['direct_aliases_desc'] = "Els àlies directes sí que es veuen afectat per la configuració de l'usuari";
$lang['user']['is_catch_all'] = 'Adreça atrapa-ho-tot';
$lang['user']['aliases_also_send_as'] = 'Pot enviar com a aquests remitents';
$lang['user']['aliases_send_as_all'] = "Al enviar no es verificarà l'adreça remitent per aquests dominis:";
$lang['user']['alias_create_random'] = 'Generar àlies aleatori';
$lang['user']['alias_extend_all'] = 'Afegir 1 hora als àlies';
$lang['user']['alias_valid_until'] = 'Vàlid fins';
$lang['user']['alias_remove_all'] = 'Esborrar tots els àlies';
$lang['user']['alias_time_left'] = 'Temps restant';
$lang['user']['alias_full_date'] = 'd.m.Y, H:i:s T';
$lang['user']['alias_select_validity'] = 'Període de validesa';
$lang['user']['sync_jobs'] = 'Feines de sincronització';
$lang['user']['hour'] = 'Hora';
$lang['user']['hours'] = 'Hores';
$lang['user']['day'] = 'Dia';
$lang['user']['week'] = 'Setmana';
$lang['user']['weeks'] = 'Setmanes';
$lang['user']['spamfilter'] = 'Filtre de spam';
$lang['admin']['spamfilter'] = 'Filtre de spam';
$lang['user']['spamfilter_wl'] = 'Llista blanca';
$lang['user']['spamfilter_wl_desc'] = 'Les adreces de la lista blanca <b>mai</b> es clasificaran como a spam. Es pot fer serivir *@exemple.org.';
$lang['user']['spamfilter_bl'] = 'Llista negra';
$lang['user']['spamfilter_bl_desc'] = 'Les adreces de la lista negra <b>sempre</b> es clasificaran como a spam. Es pot fer servir *@exemple.org';
$lang['user']['spamfilter_behavior'] = 'Classificació';
$lang['user']['spamfilter_table_rule'] = 'Regla';
$lang['user']['spamfilter_table_action'] = 'Acció';
$lang['user']['spamfilter_table_empty'] = 'No hay datos para mostrar';
$lang['user']['spamfilter_table_remove'] = 'Esborrar';
$lang['user']['spamfilter_table_add'] = 'Afegir element';
$lang['user']['spamfilter_green'] = 'Verd: el missatge no és spam';
$lang['user']['spamfilter_yellow'] = "Groc: el missatge pot ser spam, s'etiquetarà com a spam i es mourà a la carpeta de correu brossa";
$lang['user']['spamfilter_red'] = 'Vermell: El missatge és spam i, per tant, el servidor el refusarà';
$lang['user']['spamfilter_default_score'] = 'Valors per defecte:';
$lang['user']['spamfilter_hint'] = 'El primer valor representa el "llindar inferior de la qualificació de spam", el segon representa el "llindar superior de la qualificació de spam".';
$lang['user']['spamfilter_table_domain_policy'] = "n/a (política del domini)";
$lang['user']['waiting'] = "Esperant";
$lang['user']['status'] = "Estat";
$lang['user']['running'] = "En marxa";

$lang['user']['tls_policy_warning'] = "<strong>Avís:</strong> Al forçar el xifrat en les comunicacions es poden perdre missatges.<br> Els missatges que no es puguin rebre o enviar xifrats, rebotaran. <br>Aquesta opció s'aplica a l'adreça principal (nom d'usuari) i totes les adreces derivades d'àlies <b>que només tinguin aquesta bústia</b> com a destí";
$lang['user']['tls_policy'] = "Política d'encriptació";
$lang['user']['tls_enforce_in'] = 'Forçar TLS al rebre';
$lang['user']['tls_enforce_out'] = 'Forçar TLS al enviar';
$lang['user']['no_record'] = 'Sense registre';


$lang['user']['tag_handling'] = 'Al rebre un missatge etiquetat';
$lang['user']['tag_in_subfolder'] = 'Moure a subcarpeta';
$lang['user']['tag_in_subject'] = 'Marcar al assumpte';
$lang['user']['tag_in_none'] = 'No fer res';
$lang['user']['tag_help_explain'] = 'Moure a subcarpeta: es crearà una subcarpeta anomenada com la etiqueta a INBOX ("INBOX/Facebook") i es mourà el correu allà.<br>
Marcar al assumpte: s\'afegirà el nom de la etiqueta al assumpte del missatge, per exemple: "[Facebook] Les meves notícies".';
$lang['user']['tag_help_example'] = 'Ejemplo de una dirección email etiquetada: mi<b>+Facebook</b>@ejemplo.org';
$lang['user']['eas_reset'] = "Fer un reset de la cache d'ActiveSync del dispositiu";
$lang['user']['eas_reset_now'] = "Resetejar cache d'ActiveSync";
$lang['user']['eas_reset_help'] = 'El reset serveix per recuperar perfils ActiveSync trencats.<br><b>Atenció:</b> Tots els elements es tornaran a descarregar!';

$lang['user']['encryption'] = 'Xifrat';
$lang['user']['username'] = 'Usuari';
$lang['user']['last_run'] = 'Última execució';
$lang['user']['excludes'] = 'Exclosos';
$lang['user']['interval'] = 'Intèrval';
$lang['user']['active'] = 'Actiu';
$lang['user']['action'] = 'Acció';
$lang['user']['edit'] = 'Editar';
$lang['user']['remove'] = 'Esborrar';
$lang['user']['create_syncjob'] = 'Afegir treball de sincronitzaió';

$lang['start']['mailcow_apps_detail'] = 'Tria una aplicació (de moment només SOGo) per a accedir als teus correus, calendari, contactes i més.';
$lang['start']['mailcow_panel_detail'] = "Els <b>administradors del domini</b> poden crear, modificar o esborrar bústies i àlies, configurar i obtenir informació detallada sobre els seus dominis<br>
	Els <b>usuaris d'e-mail</b> poden crear àlies temporals (spam àlies), canviar la seva contrasenya i la configuració del seu filtre anti-spam.";


$lang['start']['help'] = "Mostrar/Ocultar panell d'ajuda";
$lang['header']['mailcow_settings'] = 'Configuració';
$lang['header']['administration'] = 'Administració';
$lang['header']['mailboxes'] = 'Bústies';
$lang['header']['user_settings'] = "Preferències d'usuari";
$lang['mailbox']['domain'] = 'Domini';
$lang['mailbox']['spam_aliases'] = 'Temp. àlies';
$lang['mailbox']['multiple_bookings'] = 'Múltiples reserves';
$lang['mailbox']['kind'] = 'Tipus';
$lang['mailbox']['description'] = 'Descripció';
$lang['mailbox']['alias'] = 'Àlies';
$lang['mailbox']['aliases'] = 'Àlies';
$lang['mailbox']['domains'] = 'Dominis';
$lang['mailbox']['mailboxes'] = 'Bústies';
$lang['mailbox']['resources'] = 'Recursos';
$lang['mailbox']['mailbox_quota'] = 'Mida màx. de quota';
$lang['mailbox']['domain_quota'] = 'Quota';
$lang['mailbox']['active'] = 'Actiu';
$lang['mailbox']['action'] = 'Acció';
$lang['mailbox']['backup_mx'] = 'Backup MX';
$lang['mailbox']['domain_aliases'] = 'Àlies de domini';
$lang['mailbox']['target_domain'] = 'Domini destí';
$lang['mailbox']['target_address'] = 'Direcció Goto';
$lang['mailbox']['username'] = "Nom d'usuari";
$lang['mailbox']['fname'] = 'Nom complert';
$lang['mailbox']['filter_table'] = 'Filtrar taula';
$lang['mailbox']['in_use'] = 'En ús (%)';
$lang['mailbox']['msg_num'] = 'Missatge #';
$lang['mailbox']['remove'] = 'Esborrar';
$lang['mailbox']['edit'] = 'Editar';
$lang['mailbox']['no_record'] = "No hi ha cap registre per l'objecte %s";
$lang['mailbox']['no_record_single'] = "No hi ha cap registre";
$lang['mailbox']['add_domain'] = 'Afegir domini';
$lang['mailbox']['add_domain_alias'] = 'Afegir àlies de domini';
$lang['mailbox']['add_mailbox'] = 'Afegir bústia';
$lang['mailbox']['add_resource'] = 'Afegir recurs';
$lang['mailbox']['add_alias'] = 'Afegir àlies';
$lang['mailbox']['add_domain_record_first'] = 'Primer afegeix un domini';
$lang['mailbox']['empty'] = 'Cap resultat';
$lang['mailbox']['toggle_all'] = "Tots";
$lang['mailbox']['quick_actions'] = 'Accions';
$lang['mailbox']['activate'] = 'Activar';
$lang['mailbox']['deactivate'] = 'Desactivar';
$lang['mailbox']['owner'] = 'Propietari';
$lang['mailbox']['mins_interval'] = 'Intèrval (min)';
$lang['mailbox']['last_run'] = 'Última execució';
$lang['mailbox']['excludes'] = 'Exclou';
$lang['mailbox']['last_run_reset'] = 'Executar a continuació';
$lang['mailbox']['sieve_info'] = 'Podeu emmagatzemar diversos filtres per usuari, però només un filtre previ i un filtre posterior poden estar actius al mateix temps.<br>
Cada filtre es processarà en l\'ordre descrit. Ni un script que falli, ni un "keep" emès farà que es deixin de processar els  altres scripts.<br>
Filtre previ → Filtre d\'usuari → Filtre posterior → <a href="https://github.com/mailcow/mailcow-dockerized/blob/master/data/conf/dovecot/sieve_after" target="_blank">Filtre global</a>';
$lang['info']['no_action'] = 'No hi ha cap acció aplicable';


$lang['edit']['syncjob'] = 'Sync job';
$lang['edit']['username'] = 'Usuari';
$lang['edit']['hostname'] = 'Host';
$lang['edit']['encryption'] = 'Xifrat';
$lang['edit']['maxage'] = 'Salta missatges més vells del número de dies<br><small>(0 = no salta res)</small>';
$lang['edit']['maxbytespersecond'] = 'Màx. bytes per segon<br><small>(0 = il.limitat)</small>';
$lang['edit']['automap'] = 'Provar de mapejar automàticament ("Sent items", "Sent" => "Sent" etc.)';
$lang['edit']['skipcrossduplicates'] = 'Saltar missatges duplicats entre carpetes (només es desa el primer)';
$lang['add']['automap'] = $lang['edit']['automap'];
$lang['add']['skipcrossduplicates'] = $lang['edit']['skipcrossduplicates'];
$lang['edit']['subfolder2'] = 'Sincronitza a la subcarpeta destí<br><small>(buit = no usar subcarpeta)</small>';
$lang['edit']['mins_interval'] = 'Intèrval (min)';
$lang['edit']['exclude'] = 'Excloure els objectes (regex)';
$lang['edit']['save'] = 'Desar els canvis';
$lang['edit']['max_mailboxes'] = 'Màx. bústies possibles:';
$lang['edit']['title'] = "Editar l'objecte";
$lang['edit']['target_address'] = 'Direcció/ns goto <small>(separades per coma)</small>:';
$lang['edit']['active'] = 'Actiu';
$lang['edit']['force_pw_update'] = "Forçar l'actualització de la contrassenya al proper login";
$lang['edit']['force_pw_update_info'] = 'Aquest usuari només podrà accedir a la interfície de gestió.';
$lang['edit']['target_domain'] = 'Domini destí:';
$lang['edit']['password'] = 'Contrasenya:';
$lang['edit']['password_repeat'] = 'Confirmació de contrasenya (repetir):';
$lang['edit']['domain_admin'] = 'Editar administrador del domini';
$lang['edit']['domain'] = 'Editar domini';
$lang['edit']['edit_alias_domain'] = 'Editar àlies de domini';
$lang['edit']['domains'] = 'Dominis';
$lang['edit']['alias'] = 'Editar àlies';
$lang['edit']['mailbox'] = 'Editar bustia';
$lang['edit']['description'] = 'Descripció:';
$lang['edit']['max_aliases'] = 'Màx. àlies:';
$lang['edit']['max_quota'] = 'Màx. quota per bústia (MiB):';
$lang['edit']['domain_quota'] = 'Quota de domini:';
$lang['edit']['backup_mx_options'] = 'Opcions backup MX:';
$lang['edit']['relay_domain'] = 'Domini de retransmisió (relay)';
$lang['edit']['relay_all'] = 'Retransmetre tods els recipients';
$lang['edit']['relay_all_info'] = "<small>Si tries <b>no</b> retransmetre a tods els recipients, necessitàs afegir una bústia \"blind\"(\"cega\") per cada recipient que s'hagi de retransmetre.</small>";
$lang['edit']['full_name'] = 'Nom complet';
$lang['edit']['quota_mb'] = 'Quota (MiB)';
$lang['edit']['sender_acl'] = 'Permetre enviar com a';
$lang['edit']['previous'] = 'Pàgina anterior';
$lang['edit']['unchanged_if_empty'] = 'Si no hay cambios dejalo en blanco';
$lang['edit']['dont_check_sender_acl'] = 'No verifiques remitente para el dominio %s';
$lang['edit']['multiple_bookings'] = 'Reserves múltiples';
$lang['edit']['kind'] = 'Tipus';
$lang['edit']['resource'] = 'Recurs';

$lang['add']['syncjob'] = 'Afegir sync job';
$lang['add']['syncjob_hint'] = 'Tingues en compte que les contrasenyes es desen sense xifrar!';
$lang['add']['hostname'] = 'Hostname';
$lang['add']['port'] = 'Port';
$lang['add']['username'] = 'Username';
$lang['add']['enc_method'] = 'Mètode de xifrat';
$lang['add']['mins_interval'] = 'Intèrval (minuts)';
$lang['add']['exclude'] = $lang['edit']['exclude'];
$lang['add']['delete2duplicates'] = 'Eliminar els duplicats al destí';
$lang['add']['delete1'] = "Esborrar de l'origen un cop s'han copiat";
$lang['add']['delete2'] = "Esborrar els missatges a destí que no son al origen";
$lang['edit']['delete2duplicates'] = $lang['add']['delete2duplicates'];
$lang['edit']['delete1'] = $lang['add']['delete1'];
$lang['edit']['delete2'] = $lang['add']['delete2'];

$lang['add']['domain'] = 'Domini';
$lang['add']['active'] = 'Actiu';
$lang['add']['multiple_bookings'] = 'Reserves múltiples';
$lang['add']['description'] = 'Descripció:';
$lang['add']['max_aliases'] = 'Màx. àlies possibles:';
$lang['add']['max_mailboxes'] = 'Màx. bústies possibles:';
$lang['add']['mailbox_quota_m'] = 'Màx. quota per bústia (MiB):';
$lang['add']['domain_quota_m'] = 'Quota total del domini (MiB):';
$lang['add']['backup_mx_options'] = $lang['edit']['backup_mx_options'];
$lang['add']['relay_all'] =  $lang['edit']['relay_all'];
$lang['add']['relay_domain'] = $lang['edit']['relay_domain'];
$lang['add']['relay_all_info'] = $lang['edit']['relay_all_info'];
$lang['add']['alias_address'] = 'Dirección/es àlies:';
$lang['add']['alias_address_info'] = '<small>Adreces de correu completes, o @exemple.com per a atrapar tots els missatges per a un domini (separats per coma). <b>Només dominis interns</b>.</small>';
$lang['add']['alias_domain_info'] = '<small>Només noms de domini vàlids (separats per coma).</small>';
$lang['add']['target_address'] = 'Adreces goto:';
$lang['add']['target_address_info'] = '<small>Adreces de corru completes (separades per coma).</small>';
$lang['add']['alias_domain'] = 'Domini àlies';
$lang['add']['select'] = 'Si et plau selecciona...';
$lang['add']['target_domain'] = 'Domini destí:';
$lang['add']['kind'] = 'Kind';
$lang['add']['mailbox_username'] = "Nom d'usuari (part de l'esquerra de l'adreça @):";
$lang['add']['full_name'] = 'Nom complet:';
$lang['add']['quota_mb'] = 'Quota (MiB):';
$lang['add']['select_domain'] = 'Si et plau, primer selecciona un domini';
$lang['add']['password'] = 'Constrasenya:';
$lang['add']['password_repeat'] = 'Confirmació de contrasenya (repetir):';
$lang['add']['restart_sogo_hint'] = "Necessites reiniciar el contenidor del servei SOGo després d'afegir un nou domini";
$lang['add']['goto_null'] = 'Descartar mail silenciosament';
$lang['add']['validation_success'] = 'Validated successfully';
$lang['add']['activate_filter_warn'] = 'All other filters will be deactivated, when active is checked.';
$lang['add']['validate'] = 'Validar';
$lang['mailbox']['add_filter'] = 'Afegir filter';
$lang['add']['sieve_desc'] = 'Short description';
$lang['edit']['sieve_desc'] = 'Short description';
$lang['add']['sieve_type'] = 'Filter type';
$lang['edit']['sieve_type'] = 'Filter type';
$lang['mailbox']['set_prefilter'] = 'Mark as prefilter';
$lang['mailbox']['set_postfilter'] = 'Mark as postfilter';
$lang['mailbox']['filters'] = 'Filtres';
$lang['mailbox']['sync_jobs'] = 'Sync jobs';
$lang['mailbox']['inactive'] = 'Inactiu';
$lang['edit']['validate_save'] = 'Validar i desar';


$lang['login']['username'] = "Nom d'usuari";
$lang['login']['password'] = 'Contrasenya';
$lang['login']['login'] = 'Inici de sessió';
$lang['login']['delayed'] = "Pots iniciar de sessió passats %s segons.";

$lang['tfa']['tfa'] = "Autenticació de dos factors";
$lang['tfa']['set_tfa'] = "Definir el mètode d'autenticació de dos factors";
$lang['tfa']['yubi_otp'] = "Autenticació OTP de Yubico";
$lang['tfa']['key_id'] = "Un identificador per la teva YubiKey";
$lang['tfa']['key_id_totp'] = "Un identificador per la teva clau";
$lang['tfa']['api_register'] = 'mailcow fa servir la Yubico Cloud API. Obté una API key per la teva clau <a href="https://upgrade.yubico.com/getapikey/" target="_blank">aquí</a>';
$lang['tfa']['u2f'] = "Autenticació U2F";
$lang['tfa']['none'] = "Desactivar";
$lang['tfa']['delete_tfa'] = "Desactivar TFA";
$lang['tfa']['disable_tfa'] = "Desactivar TFA fins al següent login";
$lang['tfa']['confirm'] = "Confirma";
$lang['tfa']['totp'] = "OTP basat en temps (Google Authenticator etc.)";
$lang['tfa']['select'] = "Si et plau, selecciona";
$lang['tfa']['waiting_usb_auth'] = "<i>Esperant el dispositiu USB...</i><br><br>Apreta el botó del teu dispositiu USB U2F ara.";
$lang['tfa']['waiting_usb_register'] = "<i>Esperant el dispositiu USB...</i><br><br>Posa el teu password i confirma el registre del teu U2F apretant el botó del teu dispositiiu USB U2F.";
$lang['tfa']['scan_qr_code'] = "Escaneja el codi següent amb la teva app d'autenticació o entra'l manualment.";
$lang['tfa']['enter_qr_code'] = "El teu codi TOTP, si el teu dispositiu no pot escanejar codis QR";
$lang['tfa']['confirm_totp_token'] = "Confirma els canvis introduint el codi generat";

$lang['admin']['no_new_rows'] = 'No hi ha més files';
$lang['admin']['additional_rows'] = ' files addicionals afegides'; // parses to 'n additional rows were added'
$lang['admin']['private_key'] = 'Clau privada';
$lang['admin']['import'] = 'Importar';
$lang['admin']['import_private_key'] = 'Importar clau privada';
$lang['admin']['f2b_parameters'] = 'Fail2ban';
$lang['admin']['f2b_ban_time'] = 'Temsp de bloqueig (s)';
$lang['admin']['f2b_max_attempts'] = 'Intents màx.';
$lang['admin']['f2b_retry_window'] = 'Finestra de reintent (s) per intents màx.';
$lang['admin']['f2b_netban_ipv4'] = 'Suxarxa IPv4 on aplicar el bloqueig (8-32)';
$lang['admin']['f2b_netban_ipv6'] = 'Suxarxa IPv6 on aplicar el bloqueig (8-128)';
$lang['admin']['f2b_whitelist'] = 'Llista blanca de xarxes/hosts';
$lang['admin']['search_domain_da'] = 'Buscar dominis';



$lang['admin']['dkim_key_length'] = 'Mida de la clau DKIM (bits)';
$lang['admin']['dkim_key_valid'] = 'Vàlida';
$lang['admin']['dkim_key_unused'] = 'No es fa servir';
$lang['admin']['dkim_key_missing'] = 'No té clau';
$lang['admin']['dkim_add_key'] = 'Afegir registre ARC/DKIM';
$lang['admin']['dkim_keys'] = 'Registres ARC/DKIM';
$lang['admin']['add'] = 'Afegir';
$lang['add']['add_domain_restart'] = 'Afegir el domini i reiniciar SOGo';
$lang['add']['add_domain_only'] = 'Afegir el domini';
$lang['admin']['configuration'] = 'Configuració';
$lang['admin']['password'] = 'Contrasenya';
$lang['admin']['password_repeat'] = 'Confirmació de la contrasenya (repetir)';
$lang['admin']['active'] = 'Actiu';
$lang['admin']['inactive'] = 'Inactiu';
$lang['admin']['action'] = 'Acció';
$lang['admin']['add_domain_admin'] = 'Afegir Administrador del dominio';
$lang['admin']['admin_domains'] = 'Asignaciones de dominio';
$lang['admin']['domain_admins'] = 'Administradores de dominio';
$lang['admin']['username'] = "Nom d'usuari";
$lang['admin']['edit'] = 'Editar';
$lang['admin']['remove'] = 'Esborrar';
$lang['admin']['save'] = 'Desar els canvis';
$lang['admin']['admin'] = 'Administrador';
$lang['admin']['admin_details'] = "Editar detalls de l'administrador";
$lang['admin']['unchanged_if_empty'] = "Si no hi ha canvis, deixa'l en blanc";
$lang['admin']['access'] = 'Accés';
$lang['admin']['no_record'] = 'Cap registre';
$lang['admin']['filter_table'] = 'Filtrar taula';
$lang['admin']['empty'] = 'Sense resultats';
$lang['admin']['refresh'] = 'Refrescar';
$lang['admin']['to_top'] = 'Tornar a dalt';
$lang['admin']['in_use_by'] = 'En ús per';
$lang['admin']['refresh'] = 'Refrescar';
$lang['admin']['to_top'] = 'Tornar a dalt';
$lang['admin']['in_use_by'] = 'En ús per';
$lang['admin']['forwarding_hosts'] = 'Forwarding Hosts';
$lang['admin']['forwarding_hosts_hint'] = "Els missatges entrants s'accepten de forma incondicional a qualsevol host que apareix aquí. Aquests hosts no es comproven a cap DNSBL ni estan sotmesos a greylisting. El spam rebut mai no es rebutja, però opcionalment es pot arxivar a la carpeta Junk. L'ús més comú d'això és especificar altres servidors de correu en els quals s'ha configurat una regla que reenvia correus electrònics entrants a aquest servidor";
$lang['admin']['forwarding_hosts_add_hint'] = "Podeu especificar adreces IPv4/IPv6, xarxes en notació CIDR, noms de host (que es resoldran a adreces IP) o noms de domini (que es resoldran a les adreces IP consultant els registres SPF o, si no n'hi ha, registres MX ).";
$lang['admin']['relayhosts_hint'] = 'Defineix aquí els relayhosts per després poder-los seleccionar als dominis.';
$lang['admin']['add_relayhost_add_hint'] = "Tingues en compte que les dades d'autenticació al relayhost es desaran sense xifrar.";
$lang['admin']['host'] = 'Host';
$lang['admin']['source'] = 'Origen';
$lang['admin']['add_forwarding_host'] = 'Afegir Forwarding Host';
$lang['admin']['add_relayhost'] = 'Afegir Relayhost';
$lang['success']['forwarding_host_removed'] = "Forwarding host %s esborrat";
$lang['success']['forwarding_host_added'] = "Forwarding host %s afegit";
$lang['success']['relayhost_removed'] = "Relayhost %s esborrat";
$lang['success']['relayhost_added'] = "Relayhost %s afegit";
$lang['diagnostics']['dns_records'] = 'Registres DNS';
$lang['diagnostics']['dns_records_24hours'] = "Tingues en compte que els canvis realitzats als DNS poden trigar fins a 24 hores a reflectir correctament el seu estat actual en aquesta pàgina. Es tracta d'una manera de veure fàcilment com configurar els registres DNS i comprovar si tots els registres DNS son correctes.";
$lang['diagnostics']['dns_records_name'] = 'Nom';
$lang['diagnostics']['dns_records_type'] = 'Tipus';
$lang['diagnostics']['dns_records_data'] = 'Valor correcte';
$lang['diagnostics']['dns_records_status'] = 'Valor actual';
$lang['diagnostics']['optional'] = 'Aquest registre és opcional.';
$lang['diagnostics']['cname_from_a'] = 'Valor derivat de registre A/AAAA. Això és compatible sempre que el registre assenyali el recurs correcte.';

$lang['admin']['relay_from'] = '"From:" adreça';
$lang['admin']['api_allow_from'] = "Permetre l'accés a la API des d'aquestes IPs";
$lang['admin']['api_key'] = "API key";
$lang['admin']['activate_api'] = "Activar API";
$lang['admin']['regen_api_key'] = "Regenerar API key";

$lang['admin']['quarantaine'] = "Quarantena";
$lang['admin']['quarantaine_retention_size'] = "Retentions per mailbox:";
$lang['admin']['quarantaine_max_size'] = "Mida màxima en MiB (més grans es descarten):";
$lang['admin']['quarantaine_exclude_domains'] = "Excloure els dominis i àlies de domini:";

$lang['admin']['ui_texts'] = "Etiquetes i textos de la UI";
$lang['admin']['help_text'] = "Text alternatiu per a l'ajuda de sota la casella de login (es permet HTML)";
$lang['admin']['title_name'] = 'Nom del lloc "mailcow UI"';
$lang['admin']['main_name'] = 'Nom de "mailcow UI"';
$lang['admin']['apps_name'] = 'Nom de "mailcow Apps"';

$lang['admin']['customize'] = "Personalitzar";
$lang['admin']['change_logo'] = "Canviar el logo";
$lang['admin']['logo_info'] = "La vostra imatge es reduirà a una alçada de 40 píxels per a la barra de navegació superior i un màx. ample de 250 px per a la pàgina d'inici. És molt recomanable un gràfic escalable";
$lang['admin']['upload'] = "Pujar";
$lang['admin']['app_links'] = "Enllaços a App";
$lang['admin']['app_name'] = "Nom de la App";
$lang['admin']['link'] = "Enllaç";
$lang['admin']['remove_row'] = "Eliminar fila";
$lang['admin']['add_row'] = "Afegir fila";
$lang['admin']['reset_default'] = "Restablir";
$lang['admin']['merged_vars_hint'] = 'Les files en gris venen de <code>vars.(local.)inc.php</code> i no es poden modificar.';
$lang['mailbox']['waiting'] = "Esperant";
$lang['mailbox']['status'] = "Estat";
$lang['mailbox']['running'] = "Executant-se";

$lang['edit']['spam_score'] = "Especifica una puntuació de spam";
$lang['edit']['spam_policy'] = "Afegeix o treu elementes a la white-/blacklist";
$lang['edit']['spam_alias'] = "Crear o canviar alies temporals limitats per temps";
$lang['danger']['img_tmp_missing'] = "No s'ha validat el fitxer de la imatge: el fitxer temporal no s'ha trobat";
$lang['danger']['img_invalid'] = "No s'ha validat el fitxer de la imatge";
$lang['danger']['invalid_mime_type'] = "Mime type invàlid";
$lang['success']['upload_success'] = "El fitxer s'ha pujat";
$lang['success']['app_links'] = "S'han desat els canvis als enallaços a App";
$lang['success']['ui_texts'] = "S'han desat els canvis als noms de App";
$lang['success']['reset_main_logo'] = "Restablir el logo per defecte";
$lang['success']['items_released'] = "S'han alliberat els elements seleccionats";
$lang['danger']['imagick_exception'] = "Error: exepció de Imagick mentre es llegia la imatge";

$lang['quarantine']['quarantine'] = "Quarantena";
$lang['quarantine']['qinfo'] = "El sistema de quarantena desa a la base de dades els missatges que han estat refusats. El missatge, al emissor, li consta com a <em>no</em> lliurat.";
$lang['quarantine']['release'] = "Alliberar";
$lang['quarantine']['empty'] = 'No hi ha elements';
$lang['quarantine']['toggle_all'] = 'Marcar tots';
$lang['quarantine']['quick_actions'] = 'Accions';
$lang['quarantine']['remove'] = 'Esborrar';
$lang['quarantine']['received'] = "Rebut";
$lang['quarantine']['action'] = "Acció";
$lang['quarantine']['rcpt'] = "Receptor";
$lang['quarantine']['rcpt'] = "Recipient";
$lang['quarantine']['qid'] = "Rspamd QID";
$lang['quarantine']['sender'] = "Emissor";
$lang['quarantine']['show_item'] = "Mostrar";
$lang['quarantine']['check_hash'] = "Comprovar el hash del fitxer a VT";
$lang['quarantine']['qitem'] = "Element en quarantena";
$lang['quarantine']['subj'] = "Assumpte";
$lang['quarantine']['recipients'] = "Recipients";
$lang['quarantine']['text_plain_content'] = "Contingut (text/plain)";
$lang['quarantine']['text_from_html_content'] = "Contingut (a partir del HTML)";
$lang['quarantine']['atts'] = "Adjunts";

$lang['header']['quarantine'] = "Quarantena";
$lang['header']['debug'] = "Debug";

$lang['quarantine']['release_body'] = "Hem adjuntat el teu missatge com a eml en aquest missatge.";
$lang['danger']['release_send_failed'] = "No s'ha pogut alliberar el missatge: %s";
$lang['quarantine']['release_subject'] = "Element potencialment perillós %s en quarantena";

$lang['mailbox']['bcc_map_type'] = "BCC type";
$lang['mailbox']['bcc_type'] = "BCC type";
$lang['mailbox']['bcc_sender_map'] = "Sender map";
$lang['mailbox']['bcc_rcpt_map'] = "Recipient map";
$lang['mailbox']['bcc_local_dest'] = "Destinació local";
$lang['mailbox']['bcc_destinations'] = "Destí/ns BCC";

$lang['mailbox']['bcc'] = "BCC";
$lang['mailbox']['bcc_maps'] = "BCC maps";
$lang['mailbox']['bcc_to_sender'] = "Canviar a 'Sender map'";
$lang['mailbox']['bcc_to_rcpt'] = "Canviar a 'Recipient map'";
$lang['mailbox']['add_bcc_entry'] = "Afegir BCC map";
$lang['mailbox']['bcc_info'] = "Els 'BCC maps' s'utilitzen per enviar còpies silencioses de tots els missatges a una altra adreça. S'utilitza una entrada del tipus 'Recipient map' quan la destinació local actua com a destinatari d'un correu. Els 'Sender map' segueixen el mateix principi.<br/>
     La destinació local no serà informada sobre un lliurament fallit.";
$lang['mailbox']['address_rewriting'] = "Re-escriptura d'adreces";
$lang['mailbox']['recipient_maps'] = 'Recipient maps';
$lang['mailbox']['recipient_map_info'] = "Els 'Recipient maps' es fan servir per canviar l'adreça del destinatari abans de lliurar el missatge";
$lang['mailbox']['recipient_map_old'] = 'Destinatari original';
$lang['mailbox']['recipient_map_new'] = 'Nou destinatari';
$lang['mailbox']['add_recipient_map_entry'] = "Afegir 'Recipient map'";
$lang['mailbox']['add_sender_map_entry'] = "Afegir 'Sender map'";
