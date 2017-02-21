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
$labels['encryption'] = '加密';
$labels['enigmacerts'] = 'S/MIME 证书';
$labels['enigmakeys'] = 'PGP 密钥';
$labels['keysfromto'] = '从 $from 到 $to，共计 $count 个密钥';
$labels['keyname'] = '名称';
$labels['keyid'] = '密钥 ID';
$labels['keyuserid'] = '用户 ID';
$labels['keytype'] = '密钥类型';
$labels['fingerprint'] = '指纹';
$labels['subkeys'] = '子密钥';
$labels['basicinfo'] = '基本信息';
$labels['userids'] = '其他用户';
$labels['typepublickey'] = '公钥';
$labels['typekeypair'] = '密钥对';
$labels['keyattfound'] = '此消息包含附加的 PGP 密钥。';
$labels['keyattimport'] = '导入密钥';
$labels['typesign'] = '签名';
$labels['typeencrypt'] = '加密';
$labels['typecert'] = '证明';
$labels['typeauth'] = '身份验证';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = '算法';
$labels['subkeycreated'] = '创建于';
$labels['subkeyexpires'] = '过期时间';
$labels['subkeyusage'] = '用法';
$labels['expiresnever'] = '从不';
$labels['unknown'] = '未知';
$labels['uservalid'] = '有效';
$labels['userid'] = 'ID';
$labels['valid'] = '有效';
$labels['supportencryption'] = '启用邮件加密和签名';
$labels['supportsignatures'] = '启用邮件签名验证';
$labels['supportdecryption'] = '启用邮件解密';
$labels['signdefault'] = '默认签名所有邮件';
$labels['encryptdefault'] = '默认加密所有邮件';
$labels['attachpubkeydefault'] = '默认附加我的公共 PGP 密钥';
$labels['passwordtime'] = '保持私钥密码';
$labels['nminutes'] = '$m 分钟';
$labels['wholesession'] = '整个会话';
$labels['createkeys'] = '创建新密钥对';
$labels['importkeys'] = '导入密钥';
$labels['exportkeys'] = '加密密钥';
$labels['keyactions'] = '密钥操作...';
$labels['keyremove'] = '移除';
$labels['keydisable'] = '禁用';
$labels['keyrevoke'] = '废除';
$labels['keysend'] = '在邮件中发送公钥';
$labels['keychpass'] = '修改密码';
$labels['newkeyident'] = '身份：';
$labels['newkeypass'] = '密码：';
$labels['newkeypassconfirm'] = '确认密码：';
$labels['newkeysize'] = '密钥大小：';
$labels['key2048'] = '2048 位 - 默认';
$labels['key4096'] = '4096 位 - 更安全';
$labels['keygenerating'] = '正在生成密钥...';
$labels['encryptionoptions'] = '加密选项...';
$labels['encryptmsg'] = '加密此邮件';
$labels['signmsg'] = '数字签名此邮件';
$labels['enterkeypasstitle'] = '请输入密钥口令';
$labels['enterkeypass'] = '需要口令解锁私钥 ($keyid)，用户：$user。';
$labels['arialabelkeyexportoptions'] = '密钥导出选项';
$labels['attachpubkeymsg'] = '附加我的公钥';
$labels['keyexportprompt'] = '您要在保存的 OpenPGP 密钥文件中包含私钥吗？';
$labels['onlypubkeys'] = '仅导出公钥';
$labels['withprivkeys'] = '导出私钥';
$labels['findkey'] = '搜索密钥服务器';
$labels['keyimportlabel'] = '从文件导入';
$labels['keyimportsearchlabel'] = '在密钥服务器上搜索';
$messages['sigvalid'] = '已验证来自 $sender 的签名。';
$messages['sigvalidpartial'] = '已验证来自 $sender 的签名，但主体部分未被签名。';
$messages['siginvalid'] = '无效的来自 $sender 的签名。';
$messages['sigunverified'] = '未验证签名。证书未验证。证书 ID: $keyid。';
$messages['signokey'] = '未验证签名。公钥未找到。密钥 ID: $keyid。';
$messages['sigerror'] = '未验证签名。内部错误。';
$messages['decryptok'] = '邮件已解密';
$messages['decrypterror'] = '解密失败。';
$messages['decryptnokey'] = '解密失败。私钥未找到。密钥 ID: $keyid。';
$messages['decryptnopass'] = '解密失败。需要密钥密码。';
$messages['decryptpartial'] = '邮件已解密，但主体部分未被加密。';
$messages['signerror'] = '签名失败。';
$messages['signnokey'] = '签名失败。私钥未找到。';
$messages['signnopass'] = '签名失败。需要密钥密码。';
$messages['encrypterror'] = '加密失败。';
$messages['encryptnokey'] = '加密失败。用于 $email 的公钥未找到。';
$messages['nokeysfound'] = '没有找到密钥';
$messages['keynotfound'] = '密钥未找到！';
$messages['keyopenerror'] = '无法获取密钥信息！内部错误。';
$messages['keylisterror'] = '无法列出密钥。内部错误。';
$messages['keysimportfailed'] = '无法导入密钥！内部错误。';
$messages['keysimportsuccess'] = '密钥导入成功。已导入 $new 项，未变更 $old 项。';
$messages['keyremoving'] = '正在移除密钥...';
$messages['keyremoveconfirm'] = '您确定吗，确定删除选定密钥？';
$messages['keyremovesuccess'] = '密钥删除成功';
$messages['keyremoveerror'] = '无法删除选定密钥。';
$messages['keyimporttext'] = '您可以导入私钥和公钥或废除 ASCII-Armor 格式的签名。';
$messages['keyimportsearchtext'] = '您可以根据密钥标识符、用户名或电子邮件地址搜索公钥和直接导入它们。';
$messages['formerror'] = '请填写表单。所有字段都为必填！';
$messages['passwordsdiffer'] = '密码不匹配！';
$messages['keygenerateerror'] = '生成密钥对失败';
$messages['keygeneratesuccess'] = '密钥对生成和导入成功。';
$messages['keygennosupport'] = '您的网页浏览器不支持加密。无法生成密钥对！';
$messages['noidentselected'] = '您必须为该密钥选择至少一个身份！';
$messages['nonameident'] = '身份必须有一个用户名！';
?>
