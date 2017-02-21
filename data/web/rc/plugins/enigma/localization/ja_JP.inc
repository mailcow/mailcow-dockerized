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
$labels['encryption'] = '暗号化';
$labels['enigmacerts'] = 'S/MIME証明書';
$labels['enigmakeys'] = 'PGPのキー';
$labels['keysfromto'] = '$count の $from から $to へのキー';
$labels['keyname'] = '名前';
$labels['keyid'] = 'キーID';
$labels['keyuserid'] = 'ユーザーID';
$labels['keytype'] = 'キーの種類';
$labels['fingerprint'] = 'フィンガープリント';
$labels['subkeys'] = '副キー';
$labels['basicinfo'] = '基本情報';
$labels['userids'] = '追加のユーザー';
$labels['typepublickey'] = '公開鍵';
$labels['typekeypair'] = '鍵ペア';
$labels['keyattfound'] = 'このメッセージは同封したPGPのキーを含んでいます。';
$labels['keyattimport'] = 'キーをインポート';
$labels['typesign'] = '署名';
$labels['typeencrypt'] = '暗号化';
$labels['typecert'] = '証明書';
$labels['typeauth'] = '認証';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = 'アルゴリズム';
$labels['subkeycreated'] = '作成日時';
$labels['subkeyexpires'] = '期限切れ';
$labels['subkeyusage'] = '使用法';
$labels['expiresnever'] = 'しない';
$labels['unknown'] = '不明';
$labels['uservalid'] = '有効';
$labels['userid'] = 'ID';
$labels['valid'] = '有効';
$labels['supportencryption'] = 'メッセージの暗号化と署名';
$labels['supportsignatures'] = 'メッセージの署名を検証';
$labels['supportdecryption'] = 'メッセージの復号';
$labels['signdefault'] = 'すべてのメッセージを初期設定で署名';
$labels['encryptdefault'] = 'すべてのメッセージを初期設定で暗号化';
$labels['attachpubkeydefault'] = '初期設定で自分の公開鍵を添付';
$labels['passwordtime'] = 'プライベートキーのパスワードを以下の間保持: ';
$labels['nminutes'] = '$m 分';
$labels['wholesession'] = 'セッションの間中';
$labels['createkeys'] = '新しい鍵ペアを作成';
$labels['importkeys'] = 'キーをインポート';
$labels['exportkeys'] = 'キーをエクスポート';
$labels['keyactions'] = 'キーの操作...';
$labels['keyremove'] = '削除';
$labels['keydisable'] = '無効化';
$labels['keyrevoke'] = '廃止';
$labels['keysend'] = 'メッセージ中に公開鍵を送信';
$labels['keychpass'] = 'パスワードを変更';
$labels['newkeyident'] = '識別情報:';
$labels['newkeypass'] = 'パスワード:';
$labels['newkeypassconfirm'] = 'パスワードの確認:';
$labels['newkeysize'] = '鍵の大きさ:';
$labels['key2048'] = '2048ビット(初期設定)';
$labels['key4096'] = '4096ビット(より安全)';
$labels['keygenerating'] = 'キーを精製中...';
$labels['encryptionoptions'] = '暗号のオプション...';
$labels['encryptmsg'] = 'このメッセージを暗号化';
$labels['signmsg'] = 'このメッセージに電子署名';
$labels['enterkeypasstitle'] = 'キーのパスフレーズを入力';
$labels['enterkeypass'] = 'ユーザー: $userの秘密鍵($keyid)の解除にパスフレーズが必要です。';
$labels['arialabelkeyexportoptions'] = 'キーのエクスポートのオプション';
$labels['attachpubkeymsg'] = '公開鍵を添付';
$labels['keyexportprompt'] = '保存したOpenPGPのキーファイルに秘密鍵も含めますか?';
$labels['onlypubkeys'] = '公開鍵だけエクスポート';
$labels['withprivkeys'] = '秘密鍵をエクスポート';
$labels['findkey'] = 'キーサーバーで検索';
$labels['keyimportlabel'] = 'ファイルからインポート';
$labels['keyimportsearchlabel'] = 'キーサーバーで検索';
$messages['sigvalid'] = '$senderからの署名を検証しました。';
$messages['sigvalidpartial'] = '$senderからの署名を検証しましたが、本文の一部は署名されていません。';
$messages['siginvalid'] = '$senderからの署名は正しくありません。';
$messages['sigunverified'] = '署名を検証できません。証明書を検証できません。証明書ID: $keyid';
$messages['signokey'] = '署名を検証できません。次の公開鍵がありません: $keyid';
$messages['sigerror'] = '署名を検証できません。内部エラーです。';
$messages['decryptok'] = 'メッセージを復号しました。';
$messages['decrypterror'] = '復号できませんでした。';
$messages['decryptnokey'] = '復号できませんでした。次のキーIDの秘密鍵がありません: $keyid';
$messages['decryptnopass'] = '復号できませんでした。キーのパスワードが必要です。';
$messages['decryptpartial'] = 'メッセージを復号しました。しかし、本文の一部は暗号化していませんでした。';
$messages['signerror'] = '署名できませんでした。';
$messages['signnokey'] = '署名できませんでした。秘密鍵がありません。';
$messages['signnopass'] = '署名できませんでした。キーのパスワードが必要です。';
$messages['encrypterror'] = '暗号化できませんでした。';
$messages['encryptnokey'] = '暗号化できませんでした。$emailへの公開鍵がありません。';
$messages['nokeysfound'] = 'キーがありません。';
$messages['keynotfound'] = 'キーがありません!';
$messages['keyopenerror'] = 'キーの情報を取得できません!  内部エラーです。';
$messages['keylisterror'] = 'キーの一覧をできません!  内部エラーです。';
$messages['keysimportfailed'] = 'キーをインポートできません!  内部エラーです。';
$messages['keysimportsuccess'] = 'キーをインポートしました。$new個を新規に、$old個は変更していません。';
$messages['keyremoving'] = 'キーを削除中...';
$messages['keyremoveconfirm'] = '本当に!  秘密鍵を削除しますか?';
$messages['keyremovesuccess'] = '鍵を削除しました。';
$messages['keyremoveerror'] = '秘密鍵を削除できません。';
$messages['keyimporttext'] = '秘密鍵と公開鍵、または失効署名はASCII-Armor形式でインポートできます。';
$messages['keyimportsearchtext'] = 'キーの識別子、ユーザー名、電子メールアドレスのいずれかで公開鍵を検索して、直接インポートできます。';
$messages['formerror'] = '項目を入力してください。すべての項目は必須です!';
$messages['passwordsdiffer'] = 'パスワードが一致しません!';
$messages['keygenerateerror'] = '鍵ペアを生成できませんでした。';
$messages['keygeneratesuccess'] = '鍵ペアを生成してインポートしました。';
$messages['keygennosupport'] = 'Webブラウザーが暗号機能をサポートしていません。鍵ペアを生成できません!';
$messages['noidentselected'] = 'キーのための識別情報を少なくとも1つ選択しなければなりません!';
$messages['nonameident'] = '識別情報にはユーザー名が必須です!';
?>
