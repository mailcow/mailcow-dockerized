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
$labels['changepasswd']  = 'Alterar a senha';
$labels['curpasswd']  = 'Senha atual:';
$labels['newpasswd']  = 'Nova senha:';
$labels['confpasswd']  = 'Confirmar nova senha:';
$messages['nopassword'] = 'Por favor, informe a nova senha.';
$messages['nocurpassword'] = 'Por favor, informe a senha atual.';
$messages['passwordincorrect'] = 'Senha atual incorreta.';
$messages['passwordinconsistency'] = 'Senhas não combinam, por favor tente novamente.';
$messages['crypterror'] = 'Não foi possível gravar a nova senha. Função de criptografia ausente.';
$messages['connecterror'] = 'Não foi possível gravar a nova senha. Erro de conexão.';
$messages['internalerror'] = 'Não foi possível gravar a nova senha.';
$messages['passwordshort'] = 'A senha precisa ter ao menos $length caracteres.';
$messages['passwordweak'] = 'A senha precisa conter ao menos um número e um caractere de pontuação.';
$messages['passwordforbidden'] = 'A senha contém caracteres proibidos.';
$messages['firstloginchange'] = 'Este é o seu primeiro acesso. Por favor altere sua senha.';
$messages['disablednotice'] = 'O sistema está em manutenção e a senha não pode ser alterada no momento. Tudo voltará ao normal em breve. Pedimos desculpas pelo inconveniente.';
$messages['passwinhistory'] = 'Esta senha já foi usada antes.';
$messages['samepasswd'] = 'A nova senha deve ser diferente da antiga.';
$messages['passwdexpirewarning'] = 'Atenção! Sua senha vai expirar em breve, altere ela antes de $expirationdatetime.';
$messages['passwdexpired'] = 'Sua senha expirou, você precisa alterá-la agora!';
