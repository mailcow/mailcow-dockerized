<?php

/**
 * This file is part of the Carbon package.
 *
 * (c) Brian Nesbitt <brian@nesbot.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Authors:
 * - Glavić
 * - Milos Sakovic
 */

use Carbon\CarbonInterface;
use Symfony\Component\Translation\PluralizationRules;

// @codeCoverageIgnoreStart
if (class_exists(PluralizationRules::class)) {
    PluralizationRules::set(static function ($number) {
        return PluralizationRules::get($number, 'sr');
    }, 'sr_Cyrl_ME');
}
// @codeCoverageIgnoreEnd

return [
    'year' => ':count година|:count године|:count година',
    'y' => ':count г.',
    'month' => ':count мјесец|:count мјесеца|:count мјесеци',
    'm' => ':count мј.',
    'week' => ':count недјеља|:count недјеље|:count недјеља',
    'w' => ':count нед.',
    'day' => ':count дан|:count дана|:count дана',
    'd' => ':count д.',
    'hour' => ':count сат|:count сата|:count сати',
    'h' => ':count ч.',
    'minute' => ':count минут|:count минута|:count минута',
    'min' => ':count мин.',
    'second' => ':count секунд|:count секунде|:count секунди',
    's' => ':count сек.',
    'ago' => 'прије :time',
    'from_now' => 'за :time',
    'after' => ':time након',
    'before' => ':time прије',

    'year_from_now' => ':count годину|:count године|:count година',
    'year_ago' => ':count годину|:count године|:count година',

    'week_from_now' => ':count недјељу|:count недјеље|:count недјеља',
    'week_ago' => ':count недјељу|:count недјеље|:count недјеља',

    'diff_now' => 'управо сада',
    'diff_today' => 'данас',
    'diff_today_regexp' => 'данас(?:\\s+у)?',
    'diff_yesterday' => 'јуче',
    'diff_yesterday_regexp' => 'јуче(?:\\s+у)?',
    'diff_tomorrow' => 'сутра',
    'diff_tomorrow_regexp' => 'сутра(?:\\s+у)?',
    'diff_before_yesterday' => 'прекјуче',
    'diff_after_tomorrow' => 'прекосјутра',
    'formats' => [
        'LT' => 'H:mm',
        'LTS' => 'H:mm:ss',
        'L' => 'DD.MM.YYYY',
        'LL' => 'D. MMMM YYYY',
        'LLL' => 'D. MMMM YYYY H:mm',
        'LLLL' => 'dddd, D. MMMM YYYY H:mm',
    ],
    'calendar' => [
        'sameDay' => '[данас у] LT',
        'nextDay' => '[сутра у] LT',
        'nextWeek' => function (CarbonInterface $date) {
            switch ($date->dayOfWeek) {
                case 0:
                    return '[у недељу у] LT';
                case 3:
                    return '[у среду у] LT';
                case 6:
                    return '[у суботу у] LT';
                default:
                    return '[у] dddd [у] LT';
            }
        },
        'lastDay' => '[јуче у] LT',
        'lastWeek' => function (CarbonInterface $date) {
            switch ($date->dayOfWeek) {
                case 0:
                    return '[прошле недеље у] LT';
                case 1:
                    return '[прошлог понедељка у] LT';
                case 2:
                    return '[прошлог уторка у] LT';
                case 3:
                    return '[прошле среде у] LT';
                case 4:
                    return '[прошлог четвртка у] LT';
                case 5:
                    return '[прошлог петка у] LT';
                default:
                    return '[прошле суботе у] LT';
            }
        },
        'sameElse' => 'L',
    ],
    'ordinal' => ':number.',
    'months' => ['јануар', 'фебруар', 'март', 'април', 'мај', 'јун', 'јул', 'август', 'септембар', 'октобар', 'новембар', 'децембар'],
    'months_short' => ['јан.', 'феб.', 'мар.', 'апр.', 'мај', 'јун', 'јул', 'авг.', 'сеп.', 'окт.', 'нов.', 'дец.'],
    'weekdays' => ['недеља', 'понедељак', 'уторак', 'среда', 'четвртак', 'петак', 'субота'],
    'weekdays_short' => ['нед.', 'пон.', 'уто.', 'сре.', 'чет.', 'пет.', 'суб.'],
    'weekdays_min' => ['не', 'по', 'ут', 'ср', 'че', 'пе', 'су'],
    'first_day_of_week' => 1,
    'day_of_first_week_of_year' => 1,
    'list' => [', ', ' и '],
    'meridiem' => ['АМ', 'ПМ'],
];
