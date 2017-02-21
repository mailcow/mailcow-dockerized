<?php

/*
 +-----------------------------------------------------------------------+
 | program/localization/index.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide a centralized location table                                |
 |   for keeping track of available languages                            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// langage codes according to ISO 639-1
// country codes according to ISO 3166-1 (Alpha-2)
// original names from http://www.mediaglyphs.org/mg/?p=langnames

$rcube_languages = array(
  'sq_AL' => 'Albanian (Shqip)',
  'ar'    => 'Arabic (العربية)',
  'ar_SA' => 'Arabic (Saudi Arabia)',
  'hy_AM' => 'Armenian (Հայերեն)',
  'ast'	  => 'Asturiana (Asturianu)',
  'az_AZ' => 'Azerbaijani (Azərbaycanca)',
  'eu_ES' => 'Basque (Euskara)',
  'be_BE' => 'Belarusian (беларуская мова)',
  'bn_BD' => 'Bengali (বাংলা)',
  'bs_BA' => 'Bosnian (Bosanski)',
  'br'	  => 'Breton (Brezhoneg)',
  'bg_BG' => 'Bulgarian (Български)',
  'ca_ES' => 'Catalan (Català)',
  'zh_CN' => 'Chinese (简体中文)',
  'zh_TW' => 'Chinese (正體中文)',
  'hr_HR' => 'Croatian (Hrvatski)',
  'cs_CZ' => 'Czech (Česky)',
  'da_DK' => 'Danish (Dansk)',
  'fa_AF' => 'Dari (ﻯﺭﺩ)',
  'de_DE' => 'German (Deutsch)',
  'de_CH' => 'German (Schweiz)',
  'nl_NL' => 'Dutch (Nederlands)',
  'en_CA' => 'English (Canada)',
  'en_GB' => 'English (GB)',
  'en_US' => 'English (US)',
  'eo'    => 'Esperanto',
  'et_EE' => 'Estonian (Eesti)',
  'fo_FO' => 'Faroese (Føroyskt)',
  'fi_FI' => 'Finnish (Suomi)',
  'nl_BE' => 'Flemish (Vlaams)',
  'fr_FR' => 'French (Français)',
  'gl_ES' => 'Galician (Galego)',
  'ka_GE' => 'Georgian (ქართული)',
  'el_GR' => 'Greek (Ελληνικά)',
  'he_IL' => 'Hebrew (עברית)',
  'hi_IN' => 'Hindi (हिनदी)',
  'hu_HU' => 'Hungarian (Magyar)',
  'is_IS' => 'Icelandic (Íslenska)',
  'id_ID' => 'Indonesian (Bahasa Indonesia)',
  'ia'    => 'Interlingua',
  'ga_IE' => 'Irish (Gaedhilge)',
  'it_IT' => 'Italian (Italiano)',
  'ja_JP' => 'Japanese (日本語)',
  'km_KH' => 'Khmer (ភាសាខ្មែរ)',
  'kn_IN' => 'Konkani (कोंकणी)',
  'ko_KR' => 'Korean (한국어)',
  'ku'    => 'Kurdish (Kurmancî)',
  'lv_LV' => 'Latvian (Latviešu)',
  'lt_LT' => 'Lithuanian (Lietuviškai)',
  'lb_LU' => 'Luxembourgish (Lëtzebuergesch)',
  'mk_MK' => 'Macedonian (Македонски)',
  'ms_MY' => 'Malay (Bahasa Melayu)',
  'ml_IN' => 'Malayalam (മലയാളം)',
  'mr_IN' => 'Marathi (मराठी)',
  'ne_NP' => 'Nepali (नेपाली)',
  'nb_NO' => 'Norwegian (Bokmål)',
  'nn_NO' => 'Norwegian (Nynorsk)',
  'ps' 	  => 'Pashto',
  'fa_IR' => 'Persian (فارسی)',
  'pl_PL' => 'Polish (Polski)',
  'pt_BR' => 'Portuguese (Brasil)',
  'pt_PT' => 'Portuguese (Português)',
  'ro_RO' => 'Romanian (Româneşte)',
  'ru_RU' => 'Russian (Русский)',
  'sr_CS' => 'Serbian (Српски)',
  'si_LK' => 'Sinhalese (සිංහල)',
  'sk_SK' => 'Slovak (Slovenčina)',
  'sl_SI' => 'Slovenian (Slovenščina)',
  'es_AR' => 'Spanish (Argentina)',
  'es_ES' => 'Spanish (Español)',
  'es_419' => 'Spanish (Latin America)',
  'sv_SE' => 'Swedish (Svenska)',
  'ta_IN' => 'Tamil (தமிழ்)',
  'ti'    => 'Tigrinya (ትግርኛ)',
  'th_TH' => 'Thai (ไทย)',
  'tr_TR' => 'Turkish (Türkçe)',
  'tzl'   => 'Talossan',
  'uk_UA' => 'Ukrainian (Українська)',
  'ur_PK' => 'Urdu (اُردو)',
  'vi_VN' => 'Vietnamese (Tiếng Việt)',
  'cy_GB' => 'Welsh (Cymraeg)',
  'fy_NL' => 'West Frisian (Frysk)',
);

$rcube_language_aliases = array(
  'am' => 'hy_AM',
  'az' => 'az_AZ',
  'bg' => 'bg_BG',
  'bs' => 'bs_BA',
  'ca' => 'ca_ES',
  'cn' => 'zh_CN',
  'cs' => 'cs_CZ',
  'cy' => 'cy_GB',
  'cz' => 'cs_CZ',
  'da' => 'da_DK',
  'de' => 'de_DE',
  'ee' => 'et_EE',
  'et' => 'et_EE',
  'el' => 'el_GR',
  'en' => 'en_US',
  'eu' => 'eu_ES',
  'fa' => 'fa_IR',
  'ga' => 'ga_IE',
  'ge' => 'ka_GE',
  'gl' => 'gl_ES',
  'he' => 'he_IL',
  'hi' => 'hi_IN',
  'hr' => 'hr_HR',
  'hy' => 'hy_AM',
  'ja' => 'ja_JP',
  'ka' => 'ka_GE',
  'ko' => 'ko_KR',
  'kr' => 'ko_KR',
  'kh' => 'km_KH',
  'kh_KH' => 'km_KH',
  'kn' => 'kn_IN',
  'km' => 'km_KH',
  'lb' => 'lb_LU',
  'ne' => 'ne_NP',
  'no' => 'nn_NO',
  'ms' => 'ms_MY',
  'mr' => 'mr_IN',
  'ml' => 'ml_IN',
  'ml_ML' => 'ml_IN',
  'pl' => 'pl_PL',
  'tr' => 'tr_TR',
  'tw' => 'zh_TW',
  'si' => 'si_LK',
  'sl' => 'sl_SI',
  'sr' => 'sr_CS',
  'sr_cyrillic' => 'sr_CS',
  'sr_latin' => 'bs_BA',
  'se' => 'sv_SE',
  'sv' => 'sv_SE',
  'sq' => 'sq_AL',
  'uk' => 'uk_UA',
  'vn' => 'vi_VN',
  'vi' => 'vi_VN',
  'zh' => 'zh_CN',
);

?>
