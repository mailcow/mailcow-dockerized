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
$labels['encryption'] = 'Criptografação';
$labels['enigmacerts'] = 'Certificados S/MIME';
$labels['enigmakeys'] = 'Chaves PGP';
$labels['keysfromto'] = ' $count chaves de $from para $to';
$labels['keyname'] = 'Nome';
$labels['keyid'] = 'ID da chave';
$labels['keyuserid'] = 'ID do usuário';
$labels['keytype'] = 'Tipo de chave';
$labels['fingerprint'] = 'Autenticação digital';
$labels['subkeys'] = 'Subchaves';
$labels['basicinfo'] = 'Informação básica';
$labels['userids'] = 'Usuários adicionais';
$labels['typepublickey'] = 'chave pública';
$labels['typekeypair'] = 'par de chave';
$labels['keyattfound'] = 'Essa mensagem contém chave(s) PGP em anexo.';
$labels['keyattimport'] = 'Importa chave(s)';
$labels['typesign'] = 'Assinatura';
$labels['typeencrypt'] = 'Criptografar';
$labels['typecert'] = 'Certificado';
$labels['typeauth'] = 'Autenticação';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'Algoritimo';
$labels['subkeycreated'] = 'Criado';
$labels['subkeyexpires'] = 'Expira';
$labels['subkeyusage'] = 'Uso';
$labels['expiresnever'] = 'nunca';
$labels['unknown'] = 'desconhecido';
$labels['uservalid'] = 'Válido';
$labels['userid'] = 'ID';
$labels['valid'] = 'válido';
$labels['supportencryption'] = 'Habilitar mensagens criptografadas e assinaturas';
$labels['supportsignatures'] = 'Habilitar verificação de mensagens assinadas';
$labels['supportdecryption'] = 'habilita descriptografia de mensagens';
$labels['signdefault'] = 'Assinar todas as mensagens por padrão';
$labels['encryptdefault'] = 'Criptografar todas as mensagens por padrão';
$labels['attachpubkeydefault'] = 'Anexar minha chave PGP pública';
$labels['passwordtime'] = 'Mantenha senhas de chave privada para';
$labels['nminutes'] = '$m minuto(s)';
$labels['wholesession'] = 'toda a sessão';
$labels['createkeys'] = 'Criar novo par de chaves';
$labels['importkeys'] = 'importar chave(s)';
$labels['exportkeys'] = 'Exportar chave(s)';
$labels['keyactions'] = 'Principais ações ...';
$labels['keyremove'] = 'Remover';
$labels['keydisable'] = 'Desabilitar';
$labels['keyrevoke'] = 'Revogar';
$labels['keysend'] = 'Mandar chave pública em uma mensagem';
$labels['keychpass'] = 'Trocar senha';
$labels['newkeyident'] = 'identificação:';
$labels['newkeypass'] = 'Senha:';
$labels['newkeypassconfirm'] = 'Confirmar senha:';
$labels['newkeysize'] = 'Tamanho da chave:';
$labels['key2048'] = '2048 bits - padrão';
$labels['key4096'] = '4096 bits - maise seguro';
$labels['keygenerating'] = 'Gerando chaves...';
$labels['encryptionoptions'] = 'Opções de criptografia...';
$labels['encryptmsg'] = 'Criptografar essa mensagem';
$labels['signmsg'] = 'Assinar digitalmente essa mensagem';
$labels['enterkeypasstitle'] = 'Digite a frase secreta da chave';
$labels['enterkeypass'] = 'Uma frase secreta é necessária para desbloquear a chave ($keyid) para o usuário: $user.';
$labels['arialabelkeyexportoptions'] = 'Opções de exportação das chaves';
$labels['attachpubkeymsg'] = 'Anexar minha chave pública';
$labels['keyexportprompt'] = 'Deseja incluir chaves secretas no arquivo de chaves OpenPGP salva?';
$labels['onlypubkeys'] = 'Exportar somente Chaves Públicas';
$labels['withprivkeys'] = 'Exportar Chaves Secretas';
$labels['findkey'] = 'Procurar no(s) servidor(es) de chaves';
$labels['keyimportlabel'] = 'Importar do arquivo';
$labels['keyimportsearchlabel'] = 'Procurar no(s) servidor(es) de chaves';
$messages['sigvalid'] = 'Verificar assinatura de $sender.';
$messages['sigvalidpartial'] = 'Verificar assinatura de $sender, mas parte do corpo não foi assinada.';
$messages['siginvalid'] = 'Assinatura inválida de $sender.';
$messages['sigunverified'] = 'Assinatura não verificada. Certificado não verificado. Certificado de ID: $keyid.';
$messages['signokey'] = 'Assinatura não verificada. Chave pública não encontrada. Chave de ID: $keyid.';
$messages['sigerror'] = 'Assinatura não verificada. Erro interno.';
$messages['decryptok'] = 'Mensagem descriptografada';
$messages['decrypterror'] = 'Falha ao descriptografar.';
$messages['decryptnokey'] = 'Falha ao descriptografar. Chave privada não encontrada. Chave de ID: $keyid.';
$messages['decryptnopass'] = 'Falha ao descriptografar. Senha chave requerida.';
$messages['decryptpartial'] = 'Mensagem descriptografada, mas parte do corpo não foi encriptado.';
$messages['signerror'] = 'Assinatura falhou.';
$messages['signnokey'] = 'Assinatura falhou. Chave privada não encontrada.';
$messages['signnopass'] = 'Assinatura falhou. Senha chave requerida.';
$messages['encrypterror'] = 'Falha ao criptografar.';
$messages['encryptnokey'] = 'Falha ao criptografar. Chave pública não encontrada para $email.';
$messages['nokeysfound'] = 'Nenhuma chave encontrada';
$messages['keynotfound'] = 'Chave não encontrada!';
$messages['keyopenerror'] = 'Não é possível obter informações da chave! Erro interno.';
$messages['keylisterror'] = 'Não é possível listar as chaves! Erro interno.';
$messages['keysimportfailed'] = 'Não é possível importar chave(s)! Erro interno.';
$messages['keysimportsuccess'] = 'Chave(s) importada(s) com sucesso. Importado: $new, mantido: $old.';
$messages['keyremoving'] = 'Removendo chave(s)...';
$messages['keyremoveconfirm'] = 'Tem a certeza, de que deseja excluir a seleção de chave(s)?';
$messages['keyremovesuccess'] = 'Chave(s) deletada com sucvesso';
$messages['keyremoveerror'] = 'Não é possível excluir a seleção de chave(s).';
$messages['keyimporttext'] = 'Você pode importar chaves privadas e publicas ou assinaturas de revogação em formato ASCII-Armor.';
$messages['keyimportsearchtext'] = 'Você pode procurar por chaves públicas pelo identificador de chaves, nome do usuário ou e-mail, e então importá-las diretamente.';
$messages['formerror'] = 'Por favor, preencha o formulário. Todos os campos são necessários!';
$messages['passwordsdiffer'] = 'Senhas não conferem!';
$messages['keygenerateerror'] = 'Falha ao gerar um par de chaves';
$messages['keygeneratesuccess'] = 'Um par de chaves foi gerado e importado com sucesso.';
$messages['keygennosupport'] = 'Seu navegador não suporta criptografia. Não é possível gerar um par de chaves!';
$messages['noidentselected'] = 'Você tem que selecionar ao menos uma identidade para a chave!';
$messages['nonameident'] = 'A identificação deve ter um nome de usuário definido!';
?>
