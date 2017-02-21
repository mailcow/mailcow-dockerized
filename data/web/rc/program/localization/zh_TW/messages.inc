<?php

/*
 +-----------------------------------------------------------------------+
 | localization/<lang>/messages.inc                                      |
 |                                                                       |
 | Localization file of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/messages/
*/
$messages['errortitle']  = '發生錯誤！';
$messages['loginfailed']  = '登入失敗';
$messages['cookiesdisabled'] = '你的瀏覽器不接受 cookies';
$messages['sessionerror'] = '你的工作階段已失效或過期';
$messages['storageerror'] = '連線到 IMAP 伺服器失敗';
$messages['servererror'] = '伺服器錯誤！';
$messages['servererrormsg'] = '伺服器錯誤：$msg';
$messages['connerror'] = '連線錯誤 (無法連線到伺服器)！';
$messages['dberror'] = '資料庫錯誤!';
$messages['requesttimedout'] = '要求逾時';
$messages['errorreadonly'] = '動作無法完成。資料夾唯讀';
$messages['errornoperm'] = '動作無法完成。權限不符';
$messages['erroroverquota'] = '動作無法完成。硬碟空間不夠';
$messages['erroroverquotadelete'] = '硬碟空間不夠,請使用SHIFT+DEL來刪除訊息';
$messages['invalidrequest'] = '要求無效！未儲存任何資料。';
$messages['invalidhost'] = '無效的伺服器名稱';
$messages['nomessagesfound'] = '此郵件匣沒有任何郵件';
$messages['loggedout'] = '你已成功地登出並結束工作階段了。再見！';
$messages['mailboxempty'] = '郵件匣是空的';
$messages['nomessages'] = '沒有郵件';
$messages['refreshing'] = '重新整理中...';
$messages['loading'] = '載入中...';
$messages['uploading'] = '上傳檔案中...';
$messages['uploadingmany'] = '上傳檔案中...';
$messages['loadingdata'] = '載入資料中...';
$messages['checkingmail'] = '檢查新郵件...';
$messages['sendingmessage'] = '寄出郵件中...';
$messages['messagesent'] = '郵件寄出成功';
$messages['savingmessage'] = '儲存郵件中...';
$messages['messagesaved'] = '郵件已經儲存至草稿匣';
$messages['successfullysaved'] = '儲存成功';
$messages['savingresponse'] = '儲存預設回應中...';
$messages['deleteresponseconfirm'] = '你確定要刪除這個預設回應嗎？';
$messages['addedsuccessfully'] = '聯絡人已經成功地新增至通訊錄';
$messages['contactnameexists'] = '已存在相同名稱的連絡人';
$messages['blockedimages'] = '為了保護你的隱私，此郵件中的遠端圖片已經被阻擋。';
$messages['encryptedmessage'] = '此郵件經過加密，因此無法顯示。抱歉！';
$messages['nocontactsfound'] = '沒有找到任何聯絡人';
$messages['contactnotfound'] = '找不到要求的聯絡人';
$messages['contactsearchonly'] = '輸入關鍵字找尋連絡人';
$messages['sendingfailed'] = '郵件寄出失敗';
$messages['senttooquickly'] = '你寄出的郵件太過於頻繁，請稍候 $sec 秒後再試一次。';
$messages['errorsavingsent'] = '儲存郵件到寄件備份時發生錯誤。';
$messages['errorsaving'] = '儲存時發生錯誤。';
$messages['errormoving'] = '無法移動此郵件';
$messages['errorcopying'] = '無法訊息';
$messages['errordeleting'] = '無法刪除此郵件';
$messages['errormarking'] = '無法標上旗標';
$messages['deletecontactconfirm']  = '你確定要刪除已選擇的聯絡人？';
$messages['deletegroupconfirm']  = '您確定要刪除選擇的群組嗎?';
$messages['deletemessagesconfirm'] = '你確定要刪除已選擇的郵件？';
$messages['deletefolderconfirm']  = '你確定要刪除這個資料夾嗎？';
$messages['purgefolderconfirm']  = '你確定要刪除這個資料夾裡面所有的郵件嗎？';
$messages['contactdeleting'] = '刪除連絡人...';
$messages['groupdeleting'] = '刪除存組...';
$messages['folderdeleting'] = '刪除資料夾中...';
$messages['foldermoving'] = '移動資料夾中...';
$messages['foldersubscribing'] = '訂閱資料夾...';
$messages['folderunsubscribing'] = '取消訂閱資料夾...';
$messages['formincomplete'] = '這個表單有部分未填，請完整填寫';
$messages['noemailwarning'] = '請輸入一個有效的電子郵件位址';
$messages['nonamewarning']  = '請輸入名字';
$messages['nopagesizewarning'] = '請輸入一頁顯示多少郵件';
$messages['norecipientwarning'] = '請輸入至少一位收信者';
$messages['nosubjectwarning']  = '「主旨」欄是空的。你要輸入一個主旨嗎？';
$messages['nobodywarning'] = '要傳送沒有內文的郵件嗎？';
$messages['notsentwarning'] = '郵件尚未寄出。你確定要離開並且捨棄此郵件？';
$messages['restoresavedcomposedata'] = '發現一封上次編寫但尚未寄出的郵件。\n\n主旨: $subject\n日期: $date\n\n你要回復這封郵件嗎？';
$messages['noldapserver'] = '請選擇一個 LDAP 伺服器進行搜尋';
$messages['nosearchname'] = '請輸入一個聯絡人姓名或電子郵件位址';
$messages['notuploadedwarning'] = '尚有附加檔案未上傳完畢，請等待或取消上傳';
$messages['searchsuccessful'] = '共找到 $nr 封郵件';
$messages['contactsearchsuccessful'] = '找到連絡人 $nr';
$messages['searchnomatch'] = '找不到任何符合的郵件';
$messages['searching'] = '搜尋中...';
$messages['checking'] = '檢查中...';
$messages['stillsearching'] = '仍在搜尋中...';
$messages['nospellerrors'] = '沒有找到任何拼字錯誤';
$messages['folderdeleted'] = '資料夾已經成功地被刪除';
$messages['foldersubscribed'] = '資料夾成功訂閱';
$messages['folderunsubscribed'] = '資料夾成功取消訂閱';
$messages['folderpurged'] = '資料夾成功清空';
$messages['folderexpunged'] = '資料夾成功壓縮';
$messages['deletedsuccessfully'] = '刪除成功';
$messages['converting'] = '移除郵件格式中...';
$messages['messageopenerror'] = '無法從伺服器載入郵件';
$messages['fileuploaderror'] = '檔案上傳失敗。';
$messages['filesizeerror'] = '上傳的檔案超過了 $size 的大小限制。';
$messages['copysuccess'] = '成功複製 $nr 個聯絡人。';
$messages['movesuccess'] = '成功移動 $nr 個聯絡人。';
$messages['copyerror'] = '無法複製任何聯絡人。';
$messages['moveerror'] = '無法移動任何聯絡人。';
$messages['sourceisreadonly'] = '此來源位址是唯讀的';
$messages['errorsavingcontact'] = '無法儲存連絡人的位址';
$messages['movingmessage'] = '移動郵件中...';
$messages['copyingmessage'] = '複製訊息...';
$messages['copyingcontact'] = '複製群組...';
$messages['movingcontact'] = '正在移動連絡人...';
$messages['deletingmessage'] = '刪除訊息...';
$messages['markingmessage'] = '標示訊息...';
$messages['addingmember'] = '新增連絡人至群組';
$messages['removingmember'] = '從群組刪除連絡人';
$messages['receiptsent'] = '閱讀回條傳送成功';
$messages['errorsendingreceipt'] = '無法傳送回條';
$messages['deleteidentityconfirm'] = '您確定要刪除這位連絡人嗎?';
$messages['nodeletelastidentity'] = '你不能刪除此身份，因為只剩一個身份。';
$messages['forbiddencharacter'] = '資料夾名稱中包含非法的字元';
$messages['selectimportfile'] = '請選擇一個要上傳的檔案。';
$messages['addresswriterror'] = '無法寫入選擇的通訊錄';
$messages['contactaddedtogroup'] = '成功把聯絡人加入此群組';
$messages['contactremovedfromgroup'] = '成功把移除此群組中的聯絡人';
$messages['nogroupassignmentschanged'] = '群組資料沒有異動';
$messages['importwait'] = '匯入中，請稍候...';
$messages['importformaterror'] = '匯入失敗！不支援上載的檔案格式。';
$messages['importconfirm'] = '<b>成功匯入 $inserted 筆資料，略過 $skipped 筆已存在的資料</b>：<p><em>$names</em></p>';
$messages['importconfirmskipped'] = '<b>略過 $skipped 個已存在的項目</b>';
$messages['importmessagesuccess'] = '成功匯入 $nr 封郵件';
$messages['importmessageerror'] = '匯入失敗！上載的檔案不是有效的郵件或資料夾檔案。';
$messages['opnotpermitted'] = '不允許的操作';
$messages['editorwarning'] = '切換編輯器模式將會遺失所有設定的樣式。您確定要繼續嗎？';
$messages['httpreceivedencrypterror'] = '發生嚴重錯誤。請立即聯絡管理者。<b>您的郵件無法送出。</b>';
$messages['smtpconnerror'] = 'SMTP 錯誤（$code）：與伺服器連線失敗';
$messages['smtpautherror'] = 'SMTP 錯誤（$code）：認證失敗';
$messages['smtpfromerror'] = 'SMTP 錯誤（$code）：無法設寄建者「$from」（$msg）';
$messages['smtptoerror'] = 'SMTP 錯誤（$code）：無法加收件者「$to」（$msg）';
$messages['smtprecipientserror'] = 'SMTP 錯誤：無法分析收件者';
$messages['smtperror'] = 'SMTP 錯誤：$msg';
$messages['toomanyrecipients'] = '太多收件人。請減少至 $max 人';
$messages['maxgroupmembersreached'] = '太多群組成員，超過最大人數 $max 人';
$messages['internalerror'] = '發生內部錯誤。請再次嘗試。';
$messages['contactdelerror'] = '無法刪除聯絡人';
$messages['contactdeleted'] = '聯絡人成功刪除';
$messages['contactrestoreerror'] = '無法復原刪除的連絡人';
$messages['contactrestored'] = '連絡人復原完成';
$messages['groupdeleted'] = '群組成功刪除';
$messages['grouprenamed'] = '群組重新命名成功';
$messages['groupcreated'] = '群組建立成功';
$messages['savedsearchdeleted'] = '搜尋結果刪除完成';
$messages['savedsearchdeleteerror'] = '無法刪除搜尋結果';
$messages['savedsearchcreated'] = '搜尋結果建立完成';
$messages['savedsearchcreateerror'] = '無法建立搜尋結果';
$messages['messagedeleted'] = '訊息成功刪除';
$messages['messagemoved'] = '訊息成功移動';
$messages['messagecopied'] = '訊息成功複製';
$messages['messagemarked'] = '訊息成功標示';
$messages['autocompletechars'] = '輸入至少 $min 字元才能自動完成自動完成';
$messages['autocompletemore'] = '過多相符資訊，請輸入更多文字';
$messages['namecannotbeempty'] = '名稱不可空白';
$messages['nametoolong'] = '名稱太長';
$messages['folderupdated'] = '資料夾成功更新';
$messages['foldercreated'] = '資料夾成功建立';
$messages['invalidimageformat'] = '圖片格式錯誤';
$messages['mispellingsfound'] = '郵件中偵測到拼字錯誤';
$messages['parentnotwritable'] = '無法建立/移動資料夾至指定的上層目錄 (No access rights)';
$messages['messagetoobig'] = '郵件太大無法處理';
$messages['attachmentvalidationerror'] = '警告！這個附件是可疑的，因為它的檔案類型與其宣稱的不同。如果您不能相信寄件者請不要開啟附件，它可能含有惡意的內容。<br /><br />
<em>Expected: $expected; found: $detected</em>';
$messages['noscriptwarning'] = '警告：使用Webmail需要Javascript支援，請檢查您瀏覽器的Javascript設定';
