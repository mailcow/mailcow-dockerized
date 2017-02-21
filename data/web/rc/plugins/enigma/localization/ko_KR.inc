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
$labels['encryption'] = '암호화';
$labels['enigmacerts'] = 'S/MIME 인증서';
$labels['enigmakeys'] = 'PGP 키';
$labels['keysfromto'] = '키 총 $count개 중 $from 번째 부터 $to 번째까지';
$labels['keyname'] = '이름';
$labels['keyid'] = '키 ID';
$labels['keyuserid'] = '사용자 ID';
$labels['keytype'] = '키 유형';
$labels['fingerprint'] = '지문';
$labels['subkeys'] = '하위 키';
$labels['basicinfo'] = '기본 정보';
$labels['userids'] = '추가 사용자';
$labels['typepublickey'] = '공개 키';
$labels['typekeypair'] = '키 페어';
$labels['keyattfound'] = '이 메시지는 첨부된 PGP 키를 포함합니다.';
$labels['keyattimport'] = '키 가져오기';
$labels['typesign'] = '서명';
$labels['typeencrypt'] = '암호화';
$labels['typecert'] = '인증';
$labels['typeauth'] = '인증';
$labels['subkeyid'] = 'ID';
$labels['subkeyalgo'] = '알고리즘';
$labels['subkeycreated'] = '생성일';
$labels['subkeyexpires'] = '만료일';
$labels['subkeyusage'] = '사용도';
$labels['expiresnever'] = '하지 않음';
$labels['unknown'] = '알 수 없음';
$labels['uservalid'] = '유효';
$labels['userid'] = 'ID';
$labels['valid'] = '유효';
$labels['supportencryption'] = '메시지 암호화 및 서명 추가 활성화';
$labels['supportsignatures'] = '메시지 서명 검증 활성화';
$labels['supportdecryption'] = '메시지 복호화 활성화';
$labels['signdefault'] = '기본적으로 모든 메시지에 서명 추가';
$labels['encryptdefault'] = '기본적으로 모든 메시지 암호화';
$labels['attachpubkeydefault'] = '내 공개 PGP 키를 기본으로 첨부';
$labels['passwordtime'] = '비공개 키 암호를 다음 기간동안 유지';
$labels['nminutes'] = '$m분';
$labels['wholesession'] = '전체 세션';
$labels['createkeys'] = '새로운 키 페어 생성';
$labels['importkeys'] = '키 가져오기';
$labels['exportkeys'] = '키 내보내기';
$labels['keyactions'] = '키 동작...';
$labels['keyremove'] = '제거';
$labels['keydisable'] = '비활성화';
$labels['keyrevoke'] = '폐기';
$labels['keysend'] = '메시지에 공개 키 전송';
$labels['keychpass'] = '암호 변경';
$labels['newkeyident'] = '신원:';
$labels['newkeypass'] = '암호:';
$labels['newkeypassconfirm'] = '암호 확인:';
$labels['newkeysize'] = '키 크기:';
$labels['key2048'] = '2048 비트 - 기본';
$labels['key4096'] = '4096 비트 - 보안 강화';
$labels['keygenerating'] = '키를 생성하는 중...';
$labels['encryptionoptions'] = '암호화 옵션...';
$labels['encryptmsg'] = '이 메시지를 암호화';
$labels['signmsg'] = '이 메시지에 디지털 서명 추가';
$labels['enterkeypasstitle'] = '키 암호문 입력';
$labels['enterkeypass'] = '다음 사용자에 대한 비밀 키($keyid)의 잠금을 해제하려면 암호문이 필요합니다: $user.';
$labels['arialabelkeyexportoptions'] = '키 내보내기 옵션';
$labels['attachpubkeymsg'] = '내 공개 키 첨부';
$labels['keyexportprompt'] = '암호 키를 저장된 OpenPGP 키 파일에 포함하시겠습니까?';
$labels['onlypubkeys'] = '공개 키만 내보내기';
$labels['withprivkeys'] = '비밀 키 내보내기';
$labels['findkey'] = '키 서버에서 검색';
$labels['keyimportlabel'] = '파일에서 가져오기';
$labels['keyimportsearchlabel'] = '키 서버에서 검색';
$messages['sigvalid'] = '$sender이(가) 보낸 검증된 서명.';
$messages['sigvalidpartial'] = '$sender의 검증된 서명이긴 하나, 본문 일부가 서명되지 않았습니다.';
$messages['siginvalid'] = '$sender이(가) 보낸 검증되지 않은 서명.';
$messages['sigunverified'] = '검증되지 않은 서명. 인증서가 검증되지 않음. 인증서 ID: $keyid.';
$messages['signokey'] = '검증되지 않은 서명. 공개 키를 찾을 수 없습니다. 키 ID: $keyid.';
$messages['sigerror'] = '검증되지 않은 서명. 내부 오류.';
$messages['decryptok'] = '메시지가 복호화됨.';
$messages['decrypterror'] = '복호화에 실패함.';
$messages['decryptnokey'] = '복호화에 실패함. 비공개 키를 찾을 수 없음. 키 ID: $keyid.';
$messages['decryptnopass'] = '복호화에 실패함. 키 암호가 필요합니다.';
$messages['decryptpartial'] = '메시지가 복호화됐지만 본문 일부가 암호화 되지 않았습니다.';
$messages['signerror'] = '서명 추가에 실패함.';
$messages['signnokey'] = '서명 추가에 실패함. 비공개 키를 찾을 수 없음.';
$messages['signnopass'] = '서명 추가에 실패함. 키 암호가 필요합니다.';
$messages['encrypterror'] = '암호화에 실패함.';
$messages['encryptnokey'] = '암호화에 실패함. $email에 대한 공개 키를 찾을 수 없음.';
$messages['nokeysfound'] = '키를 찾을 수 없음';
$messages['keynotfound'] = '키를 발견하지 못함!';
$messages['keyopenerror'] = '키 정보를 얻을 수 없음! 내부 오류.';
$messages['keylisterror'] = '키 목록을 표시할 수 없음! 내부 오류.';
$messages['keysimportfailed'] = '키를 가져올 수 없음! 내부 오류.';
$messages['keysimportsuccess'] = '키를 성공적으로 가져옴. 가져옴: $new개, 변경되지 않음: $old개.';
$messages['keyremoving'] = '키를 제거하는 중...';
$messages['keyremoveconfirm'] = '정말로 선택한 키를 삭제하시겠습니까?';
$messages['keyremovesuccess'] = '키가 성공적으로 삭제되었습니다';
$messages['keyremoveerror'] = '선택한 키를 삭제하지 못함.';
$messages['keyimporttext'] = '비공개 및 공개 키 또는 폐기 서명을 ASCII-Armor 형식으로 가져올 수 있습니다.';
$messages['keyimportsearchtext'] = '키 식별자, 사용자명 또는 이메일 주소로 공개 키를 검색하신 후에, 직접 가져오실 수 있습니다.';
$messages['formerror'] = '양식을 작성해주세요. 모든 필드가 채워져야 합니다!';
$messages['passwordsdiffer'] = '암호가 일치하지 않음!';
$messages['keygenerateerror'] = '키 페어 생성을 실패함';
$messages['keygeneratesuccess'] = '키 페어가 성공적으로 생성되었고 가져왔습니다.';
$messages['keygennosupport'] = '웹 브라우저가 암호화를 지원하지 않습니다. 키 페어를 생성하지 못함!';
$messages['noidentselected'] = '적어도 키에 대한 하나 이상의 신원을 선택하셔야 합니다!';
$messages['nonameident'] = '신원에는 사용자명이 정의되어야 합니다!';
?>
