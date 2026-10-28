<?php

/**
 * Pattern match exception.
 *
 * Please report bugs on https://github.com/matthiasmullie/minify/issues
 *
 * @author Ere Maijala <ere.maijala@helsinki.fi>
 * @copyright Copyright (c) 2012, Matthias Mullie. All rights reserved
 * @license MIT License
 */

namespace MatthiasMullie\Minify\Exceptions;

/**
 * Pattern Match Exception Class.
 *
 * @author Ere Maijala <ere.maijala@helsinki.fi>
 */
class PatternMatchException extends BasicException
{
    /**
     * Create an exception from preg_last_error.
     *
     * @param string $msg Error message
     */
    public static function fromLastError($msg)
    {
        $msg .= ': Error ' . preg_last_error();
        if (PHP_MAJOR_VERSION >= 8) {
            $msg .= ' - ' . preg_last_error_msg();
        }

        return new PatternMatchException($msg);
    }
}
