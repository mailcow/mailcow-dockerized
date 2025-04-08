<?php

/******************************************************************************
 * Copyright (c) 2010 Jevon Wright and others.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * or
 *
 * LGPL which is available at http://www.gnu.org/licenses/lgpl.html
 *
 *
 * Contributors:
 *    Jevon Wright - initial API and implementation
 ****************************************************************************/

/**
 * This file allows you to convert through the command line.
 * Usage:
 *   php -f convert.php [input file]
 */

if (count($argv) < 2) {
	throw new \InvalidArgumentException("Expected: php -f convert.php [input file]");
}

if (!file_exists($argv[1])) {
	throw new \InvalidArgumentException("'" . $argv[1] . "' does not exist");
}

$input = file_get_contents($argv[1]);

require_once(__DIR__ . "/src/Html2Text.php");
require_once(__DIR__ . "/src/Html2TextException.php");

echo Html2Text\Html2Text::convert($input);
