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
 * This file is available if you still want to use functions rather than
 * autoloading classes.
 */

require_once(__DIR__ . "/src/Html2Text.php");
require_once(__DIR__ . "/src/Html2TextException.php");

function convert_html_to_text($html) {
	return Html2Text\Html2Text::convert($html);
}

function fix_newlines($text) {
	return Html2Text\Html2Text::fixNewlines($text);
}
