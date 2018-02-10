<?php
/*
 * Polish language file
 */

$lang['footer']['loading'] = 'Proszę czekać...';
$lang['header']['restart_sogo'] = 'Uruchom ponownie SOGo';
$lang['footer']['restart_sogo'] = 'Uruchom ponownie SOGo';
$lang['footer']['restart_now'] = 'Uruchom ponownie teraz';
$lang['footer']['restart_sogo_info'] = 'Niektóre zadania, np. dodanie domeny, wymagają ponownego uruchomienia SOGo, aby zastosować zmiany wprowadzone do Mailcow UI.<br><br><b>Important:</b> Bezpieczne, ponowne uruchomienie może chwilę potrwać, proszę czekać do zakończenia procesu.';

$lang['footer']['confirm_delete'] = 'Potwierdź usunięcie';
$lang['footer']['delete_these_items'] = 'Czy jesteś pewien, że chcesz usunąć następujące elementy?';
$lang['footer']['delete_now'] = 'Usuń teraz';
$lang['footer']['cancel'] = 'Anuluj';

$lang['danger']['dkim_domain_or_sel_invalid'] = 'Nieprawidłowa domena lub selektor DKIM';
$lang['success']['dkim_removed'] = 'Klucz DKIM %s został usunięty';
$lang['success']['dkim_added'] = 'Klucz DKIM został zapisany';
$lang['danger']['access_denied'] = 'Odmowa dostępu lub nieprawidłowe dane w formularzu';
$lang['danger']['domain_invalid'] = 'Błędna nazwa domeny';
$lang['danger']['mailbox_quota_exceeds_domain_quota'] = 'Maksymalna wartość przekracza limit domeny';
$lang['danger']['object_is_not_numeric'] = 'Wartość %s nie jest liczbą';
$lang['success']['domain_added'] = 'Dodano domenę %s';
$lang['success']['items_deleted'] = "Item %s successfully deleted";
$lang['danger']['alias_empty'] = 'Alias nie może być pusty';
$lang['danger']['last_key'] = 'Nie można usunšć ostatniego klucza';
$lang['danger']['goto_empty'] = "Adres Idź do nie może być pusty";
$lang['danger']['policy_list_from_exists'] = "Rekord o danej nazwie już istnieje";
$lang['danger']['policy_list_from_invalid'] = "Rekord posiada nieprawidłowy format";
$lang['danger']['alias_invalid'] = "Alias nieprawidłowy";
$lang['danger']['goto_invalid'] = "Adres Idź do jest nieprawidłowy";
$lang['danger']['alias_domain_invalid'] = "Alias domeny nieprawidłowy";
$lang['danger']['target_domain_invalid'] = "Domena Idź do jest nieprawidłowa";
$lang['danger']['object_exists'] = "Obiekt %s już istnieje";
$lang['danger']['domain_exists'] = "Domena %s już istnieje";
$lang['danger']['alias_goto_identical'] = "Alias i Idź do nie mogą być identyczne";
$lang['danger']['aliasd_targetd_identical'] = "Alias domeny nie może być identyczny z domenš docelową";
$lang['danger']['maxquota_empty'] = 'Maks wartość dla skrzynki nie może wynosić 0.';
$lang['success']['alias_added'] = "Alias/y został/y dodany/e";
$lang['success']['alias_modified'] = "Zapisano zmiany w aliasie/ach %s";
$lang['success']['aliasd_modified'] = "Zapisano zmiany w aliasie domeny";
$lang['success']['mailbox_modified'] = "Zapisano zmiany w skrzynce %s";
$lang['success']['resource_modified'] = "Zapisano zmiany w skrzynce %s";
$lang['success']['object_modified'] = "Zapisano zmiany w obiekcie %s";
$lang['success']['f2b_modified'] = "Zmiany w Fail2ban zostały zapisane"; 
$lang['danger']['targetd_not_found'] = "Nie znaleziono domeny docelowej";
$lang['success']['aliasd_added'] = "Dodano alias domeny %s";
$lang['success']['aliasd_modified'] = "Zapisano zmiany w aliasie domeny %s";
$lang['success']['domain_modified'] = "Zapisano zmiany w domenie %s";
$lang['success']['domain_admin_modified'] = "Zapisano zmiany w administratorze domeny %s";
$lang['success']['domain_admin_added'] = "Dodano administratora domeny %s";
$lang['success']['admin_modified'] = "Zapisano zmiany w administratorze";
$lang['danger']['username_invalid'] = "Nie można użyć nazwy użytkownika";
$lang['danger']['password_mismatch'] = "Powtórzone hasło jest niezgodne";
$lang['danger']['password_complexity'] = "Hasło niezgodne z polityką";
$lang['danger']['password_empty'] = "Pole hasła nie może zostać puste";
$lang['danger']['login_failed'] = "Niepowodzenie logowania";
$lang['danger']['mailbox_invalid'] = "Nieprawidłowa nazwa skrzynki";
$lang['danger']['description_invalid'] = 'Nieprawidłowy opis źródła';
$lang['danger']['resource_invalid'] = "Nieprawidłowa nazwa zasobu";
$lang['danger']['is_alias'] = "%s został już podany jako alias";
$lang['danger']['is_alias_or_mailbox'] = "%s podano wcześniej jako alias lub skrzynkę";
$lang['danger']['is_spam_alias'] = "%s podano wcześniej jako alias dla spam";
$lang['danger']['quota_not_0_not_numeric'] = "Wartość musi być liczbš i >= 0";
$lang['danger']['domain_not_found'] = 'Nie znaleziono domeny %s';
$lang['danger']['max_mailbox_exceeded'] = "Przekroczono maksymalnš liczbę skrzynek (%d z %d)";
$lang['danger']['max_alias_exceeded'] = 'Przekroczono maksymalnš liczbę aliasów';
$lang['danger']['mailbox_quota_exceeded'] = "Wartość przekracza limit domeny (maks. %d MiB)";
$lang['danger']['mailbox_quota_left_exceeded'] = "Za mało dostępnego miejsca (zostało: %d MiB)";
$lang['success']['mailbox_added'] = "Dodano skrzynkę %s";
$lang['success']['resource_added'] = "Dodano śródło %s";
$lang['success']['domain_removed'] = "Usunięto domenę %s";
$lang['success']['alias_removed'] = "Usunięto alias %s ";
$lang['success']['alias_domain_removed'] = "Usunięto alias domeny %s";
$lang['success']['domain_admin_removed'] = "Usunięto administratora domeny %s";
$lang['success']['mailbox_removed'] = "Usunięto skrzynkę %s";
$lang['success']['eas_reset'] = "Zresetowano urzšdzenia ActiveSync dla użytkownika %s";
$lang['success']['resource_removed'] = "Usunięto zasób %s";
$lang['danger']['max_quota_in_use'] = "Limit skrzynki musi być większy od lub równy %d MiB";
$lang['danger']['domain_quota_m_in_use'] = "Limit domeny %s MiB";
$lang['danger']['mailboxes_in_use'] = "Maks. liczba skrzynek musi być większa od lub równa %d";
$lang['danger']['aliases_in_use'] = "Maks. liczba aliasów musi być większa od lub równa %d";
$lang['danger']['sender_acl_invalid'] = "ACL Nadawcy jest nieprawidłowy";
$lang['danger']['domain_not_empty'] = "Nie można usunšć niepustej domeny";
$lang['danger']['validity_missing'] = 'Proszę wyznaczyć termin ważności';
$lang['user']['messages'] = "wiadomości"; // "123 wiadomości"
$lang['user']['in_use'] = "Użyte";
$lang['user']['user_change_fn'] = "";
$lang['user']['user_settings'] = 'Ustawienia użytkownika';
$lang['user']['mailbox_details'] = ' Szczegóły skrzynki';
$lang['user']['change_password'] = 'Zmień hasło';
$lang['user']['new_password'] = 'Nowe hasło';
$lang['user']['save_changes'] = 'Zapisz zmiany';
$lang['user']['password_now'] = 'Bieżące hasło(potwierdź zmiany)';
$lang['user']['new_password_repeat'] = 'Potwierdź hasło(powtórz)';
$lang['user']['new_password_description'] = 'Wymagania: 6 znaków, litery i liczby.';
$lang['user']['spam_aliases'] = 'Tymczasowy alias email';
$lang['user']['alias'] = 'Alias';
$lang['user']['is_catch_all'] = 'Funkcja catch-all dla domen/y';
$lang['user']['aliases_also_send_as'] = 'Możliwe również wysłanie jako użytkownik';
$lang['user']['aliases_send_as_all'] = 'Nie sprawdzaj dostępu nadawcy dla następujących domen(y) i ich/jej aliasów';
$lang['user']['alias_create_random'] = 'Wygeneruj losowy alias';
$lang['user']['alias_extend_all'] = 'Przedłuż aliasy o 1 godzinę';
$lang['user']['alias_valid_until'] = 'Ważny do';
$lang['user']['alias_remove_all'] = 'Usuń wszystkie aliasy';
$lang['user']['alias_time_left'] = 'Pozostało';
$lang['user']['alias_full_date'] = 'd.m.Y, H:i:s T';
$lang['user']['alias_select_validity'] = 'Okres ważności';
$lang['user']['sync_jobs'] = 'Polecenie synchronizacji';
$lang['user']['hour'] = 'Godzina';
$lang['user']['hours'] = 'Godziny';
$lang['user']['day'] = 'Dzień';
$lang['user']['week'] = 'Tydzień';
$lang['user']['weeks'] = 'Tygodnie';
$lang['user']['spamfilter'] = 'Filtr spamu';
$lang['admin']['spamfilter'] = 'Filtr spamu';
$lang['user']['spamfilter_wl'] = 'Biała lista';
$lang['user']['spamfilter_wl_desc'] = 'Adresy email z białej listy <b>nigdy</b> nie klasyfikuj jako spam. Można użyć wildcards.';
$lang['user']['spamfilter_bl'] = 'Czarna lista';
$lang['user']['spamfilter_bl_desc'] = 'Adresy email z czarnej listy <b>zawsze</b> klasyfikuj jako spam i odrzucaj. Można użyć wildcards.';
$lang['user']['spamfilter_behavior'] = 'Rating';
$lang['user']['spamfilter_table_rule'] = 'Zasada';
$lang['user']['spamfilter_table_action'] = 'Działanie';
$lang['user']['spamfilter_table_empty'] = 'Brak danych do wyświetlenia';
$lang['user']['spamfilter_table_remove'] = 'Usuń';
$lang['user']['spamfilter_table_add'] = 'Dodaj element';
$lang['user']['spamfilter_default_score'] = 'Sprawdź punkty spam:';
$lang['user']['spamfilter_green'] = 'Zielony: ta wiadomość nie jest spamem';
$lang['user']['spamfilter_yellow'] = 'Żółty: ta wiadomość może być spamem, zostanie oznaczona jako spam i przeniesiona do folderu spam';
$lang['user']['spamfilter_red'] = 'Czerwony: ta wiadomość jest spamem i zostanie odrzucona przez serwer';
$lang['user']['spamfilter_default_score'] = 'Wartości domyślne:';
$lang['user']['spamfilter_hint'] = 'Pierwsza wartość oznacza "niską punktację spam", druga wartość oznacza "wysoką punktację spam".';
$lang['user']['spamfilter_table_domain_policy'] = "nie dotyczy (polityka domeny)";

$lang['user']['tls_policy_warning'] = '<strong>Ostrzeżenie:</strong> Jeżli zdecydujesz się dokonać szyfrowanego transferu poczty, możesz utracić wiadomości.<br>Wiadomości nie stosujące się do polityki zostaną permanentnie odrzucone przez serwer pocztowy.<br>Ta opcja dotyczy Twojego podstawowego adresu email (nazwa użytkownika), wszystkich adresów pochodzących z aliasu domeny jak również aliasów <b>tylko do tej jednej skrzynki</b> jako docelowej.';
$lang['user']['tls_policy'] = 'Polityka szyfrowania';
$lang['user']['tls_enforce_in'] = 'Uruchom TLS przychodzące';
$lang['user']['tls_enforce_out'] = 'Uruchom TLS wychodzące';
$lang['user']['no_record'] = 'Brak rekordu';


$lang['user']['tag_handling'] = 'Ustaw obsługę znaczników pocztowych';
$lang['user']['tag_in_subfolder'] = 'W podfolderze';
$lang['user']['tag_in_subject'] = 'W temacie';
$lang['user']['tag_in_none'] = 'Nic nie robić';
$lang['user']['tag_help_explain'] = 'W podfolderze: tworzy nowy podfolder z nazwą taką jak etykieta, który zostanie umieszczony pod Skrzynką odbiorczą ("Skrzynka odbiorcza/Facebook").<br>
W temacie: nazwy etykiet zostaną dodane na początku tematów wiadomości, np.: "[Facebook] Moje wiadomości".';
$lang['user']['tag_help_example'] = 'Przykład adresu email z etykietą: ja<b>+Facebook</b>@example.org';
$lang['user']['eas_reset'] = 'Zresetuj pamięć podręczną urządzenia ActiveSync';
$lang['user']['eas_reset_now'] = 'Zresetuj teraz';
$lang['user']['eas_reset_help'] = 'W wielu przypadkach zresetowanie pamięci podręcznej urządzenia pomoże odzyskać uszkodzony profil ActiveSync.<br><b>Uwaga:</b> Wszystkie elementy zostaną ponownie pobrane!';

$lang['user']['encryption'] = 'Szyfrowanie';
$lang['user']['username'] = 'Nazwa użytkownika';
$lang['user']['last_run'] = 'Ostatnie uruchomienie';
$lang['user']['excludes'] = 'Wyłączenia';
$lang['user']['interval'] = 'Zakres';
$lang['user']['active'] = 'Aktywny';
$lang['user']['action'] = 'Działanie';
$lang['user']['edit'] = 'Edytuj';
$lang['user']['remove'] = 'Usuń';
$lang['user']['create_syncjob'] = 'Utwórz nowe polecenie synchronizacji';

$lang['start']['mailcow_apps_detail'] = 'Użyj aplikacji mailcow, aby mieć dostęp do wiadomości, kalendarza, kontaktów i innych.';
$lang['start']['mailcow_panel_detail'] = '<b>Administratorzy domeny</b> tworzą, modyfikują lub usuwają skrzynki i aliasy, zmieniają domeny i czytają dalsze informacje o przypisanych im domenach.<br>
	<b>Użytkownicy skrzynek</b> mogą tworzyć ograniczone czasowo aliasy (aliasy spam), zmieniać swoje hasła i ustawienia filtru spam.';
$lang['start']['imap_smtp_server_auth_info'] = 'Proszę korzystać z pełnego adresu email i mechanizmu uwierzytelniania PLAIN.<br>
Twoje dane logowania zostaną zaszyfrowane przez obowiązkowe szyfrowanie po stronie serwera.';
$lang['start']['help'] = 'Pokaż/Ukryj panel pomocy';
$lang['header']['mailcow_settings'] = 'Konfiguracja';
$lang['header']['administration'] = 'Administrowanie';
$lang['header']['mailboxes'] = 'Skrzynki';
$lang['header']['user_settings'] = 'Ustawienia użytkownika';
$lang['header']['logged_in_as_logout_dual'] = 'Zalogowano jako <b>%s <span class="text-info">[%s]</span></b>';
$lang['mailbox']['domain'] = 'Domena';
$lang['mailbox']['spam_aliases'] = 'Alias tymczasowy';
$lang['mailbox']['multiple_bookings'] = 'Wielokrotne rejestracje';
$lang['mailbox']['kind'] = 'Rodzaj';
$lang['mailbox']['description'] = 'Opis';
$lang['mailbox']['alias'] = 'Alias';
$lang['mailbox']['aliases'] = 'Aliasy';
$lang['mailbox']['domains'] = 'Domeny';
$lang['mailbox']['mailboxes'] = 'Skrzynki';
$lang['mailbox']['resources'] = 'Zasoby';
$lang['mailbox']['mailbox_quota'] = 'Maks. wielkość skrzynki';
$lang['mailbox']['domain_quota'] = 'Wartość';
$lang['mailbox']['active'] = 'Aktywny';
$lang['mailbox']['action'] = 'Działanie';
$lang['mailbox']['backup_mx'] = 'Backup MX';
$lang['mailbox']['domain_aliases'] = 'Aliasy domeny';
$lang['mailbox']['target_domain'] = 'Domena docelowa';
$lang['mailbox']['target_address'] = 'Adres Idź do';
$lang['mailbox']['username'] = 'Nazwa użytkownika';
$lang['mailbox']['fname'] = 'Pełna nazwa';
$lang['mailbox']['filter_table'] = 'Tabela filtru';
$lang['mailbox']['yes'] = '&#10004;';
$lang['mailbox']['no'] = '&#10008;';
$lang['mailbox']['in_use'] = 'W użyciu (%)';
$lang['mailbox']['msg_num'] = 'Wiadomość #';
$lang['mailbox']['remove'] = 'Usuń';
$lang['mailbox']['edit'] = 'Edytuj';
$lang['mailbox']['add_domain'] = 'Dodaj domenę';
$lang['mailbox']['add_domain_alias'] = 'Dodaj alias domeny';
$lang['mailbox']['add_mailbox'] = 'Dodaj skrzynkę';
$lang['mailbox']['add_resource'] = 'Dodaj zasób';
$lang['mailbox']['add_alias'] = 'Dodaj alias';
$lang['mailbox']['empty'] = 'Brak wyników';
$lang['mailbox']['toggle_all'] = 'Włącz wszystkie';
$lang['mailbox']['quick_actions'] = 'Szybkie działania';
$lang['mailbox']['activate'] = 'Aktywuj';
$lang['mailbox']['deactivate'] = 'Wyłącz';

$lang['info']['no_action'] = 'Żadne działanie nie ma zastosowania';


$lang['edit']['syncjob'] = 'Edytuj polecenie synchronizacji';
$lang['edit']['save'] = 'Zapisz zmiany';
$lang['edit']['username'] = 'Nazwa użytkownika';
$lang['edit']['hostname'] = 'Nazwa hosta';
$lang['edit']['encryption'] = 'Szyfrowanie';
$lang['edit']['maxage'] = 'Maksymalny wiek wiadomości, liczony w dniach, które będą pobierane ze zdalnego<br><small>(0 = pomiń wiek>';
$lang['edit']['subfolder2'] = 'Synchronizuj do podfolderu w skrzynce<br><small>(pusty = nie używaj subfolderu)</small>';
$lang['edit']['mins_interval'] = 'Zakres (min)';
$lang['edit']['exclude'] = 'Wyklucz obiekty (regex)';
$lang['edit']['save'] = 'Zapisz zmiany';
$lang['edit']['max_mailboxes'] = 'Maks. liczba skrzynek';
$lang['edit']['title'] = 'Edytuj obiekt';
$lang['edit']['target_address'] = 'Adres/y Idź do <small>(rozdzielone przecinkiem)</small>';
$lang['edit']['active'] = 'Aktywny';
$lang['edit']['target_domain'] = 'Domena docelowa';
$lang['edit']['password'] = 'Hasło';
$lang['edit']['password_repeat'] = 'Potwierdź hasło(powtórz)';
$lang['edit']['domain_admin'] = 'Edytuj administratora domeny';
$lang['edit']['domain'] = 'Edytuj domenę';
$lang['edit']['edit_alias_domain'] = 'Edytuj alias domeny';
$lang['edit']['domains'] = 'Domeny';
$lang['edit']['alias'] = 'Edytuj alias';
$lang['edit']['mailbox'] = 'Edytuj skrzynkę';
$lang['edit']['description'] = 'Opis';
$lang['edit']['max_aliases'] = 'Maks. liczba aliasów';
$lang['edit']['max_quota'] = 'Maks. wartość skrzynki (MiB)';
$lang['edit']['domain_quota'] = 'Wartość domeny';
$lang['edit']['backup_mx_options'] = 'Opcje backup MX';
$lang['edit']['relay_domain'] = 'Domena przekaźnikowa';
$lang['edit']['relay_all'] = 'Przekaż wszystkim odbiorcom';
$lang['edit']['relay_all_info'] = '<small>Jeśli decydujesz się <b>nie</b> przekazywać wszystkim odbiorcom, musisz dodać ("ślepą")skrzynkę dla każdego poszczególnego odbiorcy, któremu należy przekazać.</small>';
$lang['edit']['full_name'] = 'Pełna nazwa';
$lang['edit']['quota_mb'] = 'Wartość (MiB)';
$lang['edit']['sender_acl'] = 'Zezwalaj na wysłanie jako';
$lang['edit']['previous'] = 'Poprzednia strona';
$lang['edit']['unchanged_if_empty'] = 'Jeżli bez zmian, nie wypełniaj';
$lang['edit']['dont_check_sender_acl'] = "Wyłącz sprawdzanie nadawcy dla domeny %s + aliasów domeny";
$lang['edit']['multiple_bookings'] = 'Wielokrotne rejestracje';
$lang['edit']['kind'] = 'Rodzaj';
$lang['edit']['resource'] = 'Zasób';

$lang['add']['syncjob'] = 'Dodaj polecenie synchronizacji';
$lang['add']['syncjob_hint'] = 'Pamiętaj, że hasła należy zapisywać w zwykłym tekście!';
$lang['add']['hostname'] = 'Nazwa hosta';
$lang['add']['port'] = 'Port';
$lang['add']['username'] = 'Nazwa użytkownika';
$lang['add']['enc_method'] = 'Metoda szyfrowania';
$lang['add']['mins_interval'] = 'Zakres pobierania (minuty)';
$lang['add']['exclude'] = 'Wyklucz obiekty (regex)';
$lang['add']['delete2duplicates'] = 'Usuń duplikaty w miejscu docelowym';
$lang['add']['delete1'] = 'Usuń ze źródła po zakończeniu';
$lang['edit']['delete2duplicates'] = 'Usuń duplikaty w miejscu docelowym';
$lang['edit']['delete1'] = 'Usuń ze źródła po zakończeniu';

$lang['add']['domain'] = 'Domena';
$lang['add']['active'] = 'Aktywny';
$lang['add']['multiple_bookings'] = 'Wielokrotne rejestracje';
$lang['add']['description'] = 'Opis:';
$lang['add']['max_aliases'] = 'Maks. liczba aliasów:';
$lang['add']['max_mailboxes'] = 'Maks. liczba skrzynek:';
$lang['add']['mailbox_quota_m'] = 'Maks. wartość na skrzynkę (MiB):';
$lang['add']['domain_quota_m'] = 'Łączna wartość domeny (MiB):';
$lang['add']['backup_mx_options'] = 'Opcje Backup MX:';
$lang['add']['relay_all'] = 'Przekaż wszystkim odbiorcom';
$lang['add']['relay_domain'] = 'Domena przekaźnikowa';
$lang['add']['relay_all_info'] = '<small>Jeśli decydujesz się <b>nie</b> przekazywać wszystkim odbiorcom, musisz dodać ("ślepą")skrzynkę dla każdego poszczególnego odbiorcy, któremu należy przekazać.</small>';
$lang['add']['alias_address'] = 'Alias/y:';
$lang['add']['alias_address_info'] = '<small>Pełny/e adres/y email lub @example.com, aby przejąć wszystkie wiadomości dla domeny (oddzielone przecinkami). <b>tylko domeny mailcow</b>.</small>';
$lang['add']['alias_domain_info'] = '<small>Tylko prawidłowe nazwy domen (oddzielone przecinkami).</small>';
$lang['add']['target_address'] = 'Adresy Idź do:';
$lang['add']['target_address_info'] = '<small> Pełny/e adres/y email (oddzielone przecinkami).</small>';
$lang['add']['alias_domain'] = 'Alias domeny';
$lang['add']['select'] = 'Proszę wybrać...';
$lang['add']['target_domain'] = 'Domena docelowa:';
$lang['add']['kind'] = 'Rodzaj';
$lang['add']['mailbox_username'] = 'Nazwa użytkownika (lewa strona adresu email):';
$lang['add']['full_name'] = 'Pełna nazwa:';
$lang['add']['quota_mb'] = 'Wartość (MiB):';
$lang['add']['select_domain'] = 'Proszę najpierw wybrać domenę';
$lang['add']['password'] = 'Hasło:';
$lang['add']['password_repeat'] = 'Potwierdź hasło(powtórz):';
$lang['add']['restart_sogo_hint'] = 'Po dodaniu nowej domeny będziesz musiał ponownie uruchomić kontener serwisowy SOGo!';

$lang['login']['username'] = 'Nazwa użytkownika';
$lang['login']['password'] = 'Hasło';
$lang['login']['login'] = 'Zaloguj się';
$lang['login']['delayed'] = 'Logowanie zostało opóźnione o %s sekund.';

$lang['tfa']['tfa'] = "Uwierzytelnianie dwuetapowe";
$lang['tfa']['set_tfa'] = "Ustaw metodę uwierzytelniania dwuetapowego";
$lang['tfa']['yubi_otp'] = "Uwierzytelnianie Yubico OTP";
$lang['tfa']['key_id'] = "Identyfikator dla Twojego YubiKey";
$lang['tfa']['key_id_totp'] = "Identyfikator dla Twojego klucza";
$lang['tfa']['api_register'] = 'mailcow używa Yubico Cloud API. Proszę pobrać klucz API dla Twojego klucza <a href="https://upgrade.yubico.com/getapikey/" target="_blank">here</a>';
$lang['tfa']['u2f'] = "Uwierzytelnianie U2F";
$lang['tfa']['totp'] = "Uwierzytelnianie TOTP";
$lang['tfa']['none'] = "Deaktywuj";
$lang['tfa']['delete_tfa'] = "Wyłącz TFA";
$lang['tfa']['disable_tfa'] = "Wyłącz TFA do kolejnego udanego logowania";
$lang['tfa']['confirm'] = "Potwierdź";
$lang['tfa']['totp'] = "Time-based OTP (Google Authenticator itd.)";
$lang['tfa']['select'] = "Proszę wybrać";
$lang['tfa']['waiting_usb_auth'] = "<i>Czekam na urządzenie USB...</i><br><br>Wciśnij teraz przycisk na urządzeniu U2F USB.";
$lang['tfa']['waiting_usb_register'] = "<i> Czekam na urządzenie USB...</i><br><br>Wprowadź swoje  hasło powyżej i potwierdź rejestrację U2F przez naciśnięcie przycisku na urządzeniu U2F USB.";
$lang['tfa']['scan_qr_code'] = "Zeskanuj następujący kod aplikacją uwierzytelniającą lub wprowadź kod ręcznie.";
$lang['tfa']['enter_qr_code'] = "Twój kod TOTP, jeśli Twoje urządzenie nie skanuje kodów QR.";
$lang['tfa']['confirm_totp_token'] = "Potwierdź zmiany przez wprowadzenie wygenerowanego tokenu";

$lang['admin']['private_key'] = 'Klucz prywatny';
$lang['admin']['import'] = 'Importuj';
$lang['admin']['import_private_key'] = 'Importuj klucz prywatny';
$lang['admin']['f2b_parameters'] = 'Parametry Fail2ban';
$lang['admin']['f2b_ban_time'] = 'Czas bana (s)';
$lang['admin']['f2b_max_attempts'] = 'Max. ilość prób';
$lang['admin']['f2b_retry_window'] = 'Spróbuj ponownie (s) dla max. ilości prób'; // do spr
$lang['admin']['f2b_whitelist'] = 'Biała lista sieci/hosty';
$lang['admin']['search_domain_da'] = 'Szukaj domen';
$lang['admin']['r_inactive'] = 'Nieaktywne ograniczenia';
$lang['admin']['r_active'] = 'Aktywne ograniczenia';
$lang['admin']['r_info'] = 'Zablokowane/Wyłączone elementy na liście aktywnych ograniczeń nie są rozpoznawane przez mailcow jako prawidłowe ograniczenia i nie można ich przesunąć. Niemniej jednak nierozpoznane ograniczenia zostaną wyświetlone w kolejności pojawiania się. <br>Nowe elementy można dodać w <code>inc/vars.local.inc.php</code>, aby móc je przełączać.';
$lang['admin']['dkim_key_length'] = 'Długość klucza DKIM (bity)';
$lang['admin']['dkim_key_valid'] = 'Prawidłowy klucz';
$lang['admin']['dkim_key_unused'] = 'Nieużywany klucz';
$lang['admin']['dkim_key_missing'] = 'Brak klucza';
$lang['admin']['save'] = 'Zapisz zmiany';
$lang['admin']['dkim_add_key'] = 'Dodaj klucz ARC/DKIM';
$lang['admin']['dkim_keys'] = 'Klucze ARC/DKIM';
$lang['admin']['add'] = 'Dodaj';
$lang['admin']['configuration'] = 'Konfiguracja';
$lang['admin']['password'] = 'Hasło';
$lang['admin']['password_repeat'] = 'Potwierdź hasło(powtórz)';
$lang['admin']['active'] = 'Aktwyny';
$lang['admin']['inactive'] = 'Nieaktywny';
$lang['admin']['action'] = 'Działanie';
$lang['admin']['add_domain_admin'] = 'Dodaj administratora domeny';
$lang['admin']['admin_domains'] = 'Zadania domeny';
$lang['admin']['domain_admins'] = 'Administratorzy domeny';
$lang['admin']['username'] = 'Nazwa użytkownika';
$lang['admin']['edit'] = 'Edytuj';
$lang['admin']['remove'] = 'Usuń';
$lang['admin']['save'] = 'Zapisz zmiany';
$lang['admin']['admin'] = 'Administrator';
$lang['admin']['admin_details'] = 'Edytuj szczegóły administratora';
$lang['admin']['unchanged_if_empty'] = 'W przypadku braku zmian, nie wypełniaj';
$lang['admin']['yes'] = '&#10004;';
$lang['admin']['no'] = '&#10008;';
$lang['admin']['access'] = 'Dostęp';
$lang['admin']['no_record'] = 'Brak rekordu';
$lang['admin']['filter_table'] = 'Tabela filtru';
$lang['admin']['empty'] = 'Brak wyników';
$lang['admin']['refresh'] = 'Odśwież';
$lang['admin']['forwarding_hosts'] = 'Hosty przekazujące';
$lang['admin']['forwarding_hosts_hint'] = 'Wiadomości przychodzące od hostów wyszczególnionych tutaj są akceptowane bezwarunkowo. Dane hosty nie są następnie sprawdzane względem list DNSBL lub poddawane metodzie szarych list. Otrzymany od nich spam nie jest nigdy odrzucany, ale opcjonalnie może zostać umieszczony w folderze spam. Najpopularniejsze zastosowanie dla takiego rozwiązania to wyszczególnienie serwerów poczty, na których ustawiono przekierowanie poczty przychodzącej na Twój serwer Mailcow.';
$lang['admin']['forwarding_hosts_add_hint'] = 'Możesz albo wyszczególnić adresy IPv4/IPv6, sieci w notacji CIDR, nazwy hostów (które zostaną rozłożone na adresy IP), albo nazwy domen (które zostaną rozłożone na adresy IP poprzez sprawdzanie rekordów SPF lub, w razie ich braku, rekordów MX).';
$lang['admin']['host'] = 'Host';
$lang['admin']['source'] = 'źródło';
$lang['admin']['add_forwarding_host'] = 'Dodaj hosta przekazującego';
$lang['success']['forwarding_host_removed'] = "Usunięto hosta przekazującego %s";
$lang['success']['forwarding_host_added'] = "Dodano hosta przekazującego %s";
