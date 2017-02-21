ALTER TABLE `users`
  ADD `failed_login` datetime DEFAULT NULL,
  ADD `failed_login_counter` int(10) UNSIGNED DEFAULT NULL;
