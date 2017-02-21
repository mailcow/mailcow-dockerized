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
$labels['encryption'] = 'הצפנה';
$labels['enigmacerts'] = 'תעודות מאושרות S/MIME';
$labels['enigmakeys'] = 'מפתחות PGP';
$labels['keysfromto'] = 'מפתחות from$ עד to$ מתוך count$';
$labels['keyname'] = 'שם';
$labels['keyid'] = 'זיהוי מפתח';
$labels['keyuserid'] = 'זיהוי משתמש';
$labels['keytype'] = 'סוג מפתח';
$labels['fingerprint'] = 'טביעת אצבע';
$labels['subkeys'] = 'תת מפתח';
$labels['basicinfo'] = 'מידע בסיסי';
$labels['userids'] = 'משתמשים נוספים';
$labels['typepublickey'] = 'מפתח ציבורי';
$labels['typekeypair'] = 'צמד מפתחות';
$labels['keyattfound'] = 'הצרופה מכילה מפתח PGP אחד או יותר';
$labels['keyattimport'] = 'ייבוא מפתח אחד או יותר';
$labels['typesign'] = 'לחתום';
$labels['typeencrypt'] = 'להצפין';
$labels['typecert'] = 'לאשר';
$labels['typeauth'] = 'וידוא מקוריות';
$labels['subkeyid'] = 'קוד זיהוי';
$labels['subkeyalgo'] = 'אלגוריתם';
$labels['subkeycreated'] = 'נוצר';
$labels['subkeyexpires'] = 'פג תוקף';
$labels['subkeyusage'] = 'שימוש';
$labels['expiresnever'] = 'אף פעם';
$labels['unknown'] = 'לא ידוע';
$labels['uservalid'] = 'בתוקף';
$labels['userid'] = 'קוד זיהוי';
$labels['valid'] = 'בתוקף';
$labels['supportencryption'] = 'הפעלה של הצפנת הודעה וחתימתה';
$labels['supportsignatures'] = 'הפעלה של וידוא חתימות הודעה';
$labels['supportdecryption'] = 'הפעלה של פיענוח הודעה';
$labels['signdefault'] = 'חתימה של כל הודעה כברירת מחדל';
$labels['encryptdefault'] = 'הצפנה של כל הודעה כברירת מחדל';
$labels['attachpubkeydefault'] = 'צירוף המפתח הציבורי שלי כברירת מחדל';
$labels['passwordtime'] = 'אחסון סיסמאות של מפתח פרטי עבור';
$labels['nminutes'] = 'm$ דקות';
$labels['wholesession'] = 'כל משך ההתקשרות';
$labels['createkeys'] = 'יצירה של צמד מפתחות';
$labels['importkeys'] = 'ייבוא מפתח אחד או יותר';
$labels['exportkeys'] = 'ייצוא מפתח אחד או יותר';
$labels['keyactions'] = 'פעולות אפשריות למפתח...';
$labels['keyremove'] = 'הסר';
$labels['keydisable'] = 'חסום';
$labels['keyrevoke'] = 'הפקעה';
$labels['keysend'] = 'משלוח מפתח ציבורי בהודעה';
$labels['keychpass'] = 'שינוי סיסמה';
$labels['newkeyident'] = 'זהות:';
$labels['newkeypass'] = 'סיסמה:';
$labels['newkeypassconfirm'] = 'אישור סיסמה:';
$labels['newkeysize'] = 'גודל מפתח:';
$labels['key2048'] = 'ברירת מחדל - 2048 סיביות';
$labels['key4096'] = '4096 סיביות - יותר בטוח';
$labels['keygenerating'] = 'מפתחות נוצרים...';
$labels['encryptionoptions'] = 'אפשרויות הצפנה...';
$labels['encryptmsg'] = 'הצפנה של הודעה זו';
$labels['signmsg'] = 'חתימה דיגיטלית של הודעה זו';
$labels['enterkeypasstitle'] = 'נא להקיש צרור אותיות וסימנים ליצירת מפתח';
$labels['enterkeypass'] = 'יש להקיש צרור אותיות וסימנים שנבחרו קודם כדי לשחרר נעילה של המפתח הסודי  (keyid$)  של משתמש  user$';
$labels['arialabelkeyexportoptions'] = 'אפשרויות ייצוא של מפתחות';
$labels['attachpubkeymsg'] = 'צירוף המפתח הציבורי שלי';
$labels['keyexportprompt'] = 'האם ברצונך לכלול מפתחות סודיים בקובץ השמור של המפתחות ?';
$labels['onlypubkeys'] = 'ייצוא מפתחות ציבוריים בלבד';
$labels['withprivkeys'] = 'ייצוא מפתחות סודיים';
$labels['findkey'] = 'חיפוש בשרת(י) מפתח';
$labels['keyimportlabel'] = 'ייבוא מקובץ';
$labels['keyimportsearchlabel'] = 'חיפוש בשרת(י) מפתח';
$messages['sigvalid'] = 'אימות חתימה מ sender$';
$messages['sigvalidpartial'] = 'אומתה חתימה של sender$ אבל חלק מגוף ההודעה לא נחתם';
$messages['siginvalid'] = 'חתימה לא תקינה מ sender$';
$messages['sigunverified'] = 'חתימה לא מאומתת. התעודה לא אומתה. קוד תעודה: keyid$.';
$messages['signokey'] = 'חתימה לא מאומתת. לא נמצא מפתח ציבורי. קוד מפתח: keyid$.';
$messages['sigerror'] = 'חתימה לא מאומתת. שגיאה פנימית.';
$messages['decryptok'] = 'הודעה פוענחה.';
$messages['decrypterror'] = 'הפיענוח נכשל.';
$messages['decryptnokey'] = 'הפיענוח נכשל. לא נמצא מפתח פרטי. קוד מפתח: keyid$.';
$messages['decryptnopass'] = 'פענוח נכשל. נדרשת סיסמת מפתח.';
$messages['decryptpartial'] = 'ההודעה פוענחה אבל חלק מגוף ההודעה כלל לא הוצפן';
$messages['signerror'] = 'חתימה נכשלה.';
$messages['signnokey'] = 'חתימה נכשלה. לא נמצא מפתח פרטי.';
$messages['signnopass'] = 'חיתום נכשל. נדרשת סיסמת מפתח.';
$messages['encrypterror'] = 'הצפנה נכשלה.';
$messages['encryptnokey'] = 'הצפנה נכשלה. לא נמצא מפתח ציבורי עבור mail$.';
$messages['nokeysfound'] = 'לא נמצאו מפתחות.';
$messages['keynotfound'] = 'לא נמצא מפתח !';
$messages['keyopenerror'] = 'לא ניתן להשיג מידע על מפתח! שגיאה פנימית.';
$messages['keylisterror'] = 'לא ניתן להציג רשימת מפתחות! שגיאה פנימית.';
$messages['keysimportfailed'] = 'לא ניתן לייבא מפתח אחד או יותר !   שגיאה פנימית.';
$messages['keysimportsuccess'] = 'מפתחות יובאו בהצלחה. יובא: new$, לא שינוי: old$.';
$messages['keyremoving'] = 'הסרת מפתחות...';
$messages['keyremoveconfirm'] = 'האם למחוק את המפתחות שנבחרו?';
$messages['keyremovesuccess'] = 'מפתח(ות) נמחק(ו) בהצלחה';
$messages['keyremoveerror'] = 'לא ניתן למחוק את המפתחות שנבחרו.';
$messages['keyimporttext'] = 'ניתן לייבא מפתחות פרטיים וציבוריים או חתימות ביטול בםפורמט ASCII-Armor';
$messages['keyimportsearchtext'] = 'ניתן לחפש מפתחות ציבוריים לפי מזהה מפתח, שם משתמש או כתובת דוא"ל ואח"כ לייבא אותם ישירות.';
$messages['formerror'] = 'נא למלא את כל השדות בטופס !';
$messages['passwordsdiffer'] = 'הסיסמאות לא תואמות!';
$messages['keygenerateerror'] = 'נכשלה יצירה של צמד המפתחות';
$messages['keygeneratesuccess'] = 'נוצר צמד מפתחות ויובא בהצלחה.';
$messages['keygennosupport'] = 'הדפדפן הזה אינו תומך בהצפנה. לא ניתן לייצר צמד מפתחות !';
$messages['noidentselected'] = 'יש לבחור לפחות זהות אחת עבור המפתח';
$messages['nonameident'] = 'יש להגדיר שם משתמש בהגדרת הזהות !';
?>
