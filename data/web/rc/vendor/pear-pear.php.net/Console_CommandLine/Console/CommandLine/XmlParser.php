<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of the PEAR Console_CommandLine package.
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to the MIT license that is available
 * through the world-wide-web at the following URI:
 * http://opensource.org/licenses/mit-license.php
 *
 * @category  Console
 * @package   Console_CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Console_CommandLine
 * @since     File available since release 0.1.0
 * @filesource
 */

/**
 * Required file
 */
require_once 'Console/CommandLine.php';

/**
 * Parser for command line xml definitions.
 *
 * @category  Console
 * @package   Console_CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @version   Release: 1.2.2
 * @link      http://pear.php.net/package/Console_CommandLine
 * @since     Class available since release 0.1.0
 */
class Console_CommandLine_XmlParser
{
    // parse() {{{

    /**
     * Parses the given xml definition file and returns a
     * Console_CommandLine instance constructed with the xml data.
     *
     * @param string $xmlfile The xml file to parse
     *
     * @return Console_CommandLine A parser instance
     */
    public static function parse($xmlfile)
    {
        if (!is_readable($xmlfile)) {
            Console_CommandLine::triggerError('invalid_xml_file',
                E_USER_ERROR, array('{$file}' => $xmlfile));
        }
        $doc = new DomDocument();
        $doc->load($xmlfile);
        self::validate($doc);
        $nodes = $doc->getElementsByTagName('command');
        $root  = $nodes->item(0);
        return self::_parseCommandNode($root, true);
    }

    // }}}
    // parseString() {{{

    /**
     * Parses the given xml definition string and returns a
     * Console_CommandLine instance constructed with the xml data.
     *
     * @param string $xmlstr The xml string to parse
     *
     * @return Console_CommandLine A parser instance
     */
    public static function parseString($xmlstr)
    {
        $doc = new DomDocument();
        $doc->loadXml($xmlstr);
        self::validate($doc);
        $nodes = $doc->getElementsByTagName('command');
        $root  = $nodes->item(0);
        return self::_parseCommandNode($root, true);
    }

    // }}}
    // validate() {{{

    /**
     * Validates the xml definition using Relax NG.
     *
     * @param DomDocument $doc The document to validate
     *
     * @return boolean Whether the xml data is valid or not.
     * @throws Console_CommandLine_Exception
     * @todo use exceptions
     */
    public static function validate($doc)
    {
        $pkgRoot  = __DIR__ . '/../../';
        $paths = array(
            // PEAR/Composer
            '/www/roundcube/releases/roundcubemail-1.3-beta/vendor/pear-pear.php.net/Console_CommandLine/data/Console_CommandLine/data/xmlschema.rng',
            // Composer
            $pkgRoot . 'data/Console_CommandLine/data/xmlschema.rng',
            $pkgRoot . 'data/console_commandline/data/xmlschema.rng',
            // Git
            $pkgRoot . 'data/xmlschema.rng',
            'xmlschema.rng',
        );

        foreach ($paths as $path) {
            if (is_readable($path)) {
                return $doc->relaxNGValidate($path);
            }
        }
        Console_CommandLine::triggerError(
            'invalid_xml_file',
            E_USER_ERROR, array('{$file}' => $rngfile));
    }

    // }}}
    // _parseCommandNode() {{{

    /**
     * Parses the root command node or a command node and returns the
     * constructed Console_CommandLine or Console_CommandLine_Command instance.
     *
     * @param DomDocumentNode $node       The node to parse
     * @param bool            $isRootNode Whether it is a root node or not
     *
     * @return mixed Console_CommandLine or Console_CommandLine_Command
     */
    private static function _parseCommandNode($node, $isRootNode = false)
    {
        if ($isRootNode) {
            $obj = new Console_CommandLine();
        } else {
            include_once 'Console/CommandLine/Command.php';
            $obj = new Console_CommandLine_Command();
        }
        foreach ($node->childNodes as $cNode) {
            $cNodeName = $cNode->nodeName;
            switch ($cNodeName) {
            case 'name':
            case 'description':
            case 'version':
                $obj->$cNodeName = trim($cNode->nodeValue);
                break;
            case 'add_help_option':
            case 'add_version_option':
            case 'force_posix':
                $obj->$cNodeName = self::_bool(trim($cNode->nodeValue));
                break;
            case 'option':
                $obj->addOption(self::_parseOptionNode($cNode));
                break;
            case 'argument':
                $obj->addArgument(self::_parseArgumentNode($cNode));
                break;
            case 'command':
                $obj->addCommand(self::_parseCommandNode($cNode));
                break;
            case 'aliases':
                if (!$isRootNode) {
                    foreach ($cNode->childNodes as $subChildNode) {
                        if ($subChildNode->nodeName == 'alias') {
                            $obj->aliases[] = trim($subChildNode->nodeValue);
                        }
                    }
                }
                break;
            case 'messages':
                $obj->messages = self::_messages($cNode);
                break;
            default:
                break;
            }
        }
        return $obj;
    }

    // }}}
    // _parseOptionNode() {{{

    /**
     * Parses an option node and returns the constructed
     * Console_CommandLine_Option instance.
     *
     * @param DomDocumentNode $node The node to parse
     *
     * @return Console_CommandLine_Option The built option
     */
    private static function _parseOptionNode($node)
    {
        include_once 'Console/CommandLine/Option.php';
        $obj = new Console_CommandLine_Option($node->getAttribute('name'));
        foreach ($node->childNodes as $cNode) {
            $cNodeName = $cNode->nodeName;
            switch ($cNodeName) {
            case 'choices':
                foreach ($cNode->childNodes as $subChildNode) {
                    if ($subChildNode->nodeName == 'choice') {
                        $obj->choices[] = trim($subChildNode->nodeValue);
                    }
                }
                break;
            case 'messages':
                $obj->messages = self::_messages($cNode);
                break;
            default:
                if (property_exists($obj, $cNodeName)) {
                    $obj->$cNodeName = trim($cNode->nodeValue);
                }
                break;
            }
        }
        if ($obj->action == 'Password') {
            $obj->argument_optional = true;
        }
        return $obj;
    }

    // }}}
    // _parseArgumentNode() {{{

    /**
     * Parses an argument node and returns the constructed
     * Console_CommandLine_Argument instance.
     *
     * @param DomDocumentNode $node The node to parse
     *
     * @return Console_CommandLine_Argument The built argument
     */
    private static function _parseArgumentNode($node)
    {
        include_once 'Console/CommandLine/Argument.php';
        $obj = new Console_CommandLine_Argument($node->getAttribute('name'));
        foreach ($node->childNodes as $cNode) {
            $cNodeName = $cNode->nodeName;
            switch ($cNodeName) {
            case 'description':
            case 'help_name':
            case 'default':
                $obj->$cNodeName = trim($cNode->nodeValue);
                break;
            case 'multiple':
                $obj->multiple = self::_bool(trim($cNode->nodeValue));
                break;
            case 'optional':
                $obj->optional = self::_bool(trim($cNode->nodeValue));
                break;
            case 'choices':
                foreach ($cNode->childNodes as $subChildNode) {
                    if ($subChildNode->nodeName == 'choice') {
                        $obj->choices[] = trim($subChildNode->nodeValue);
                    }
                }
                break;
            case 'messages':
                $obj->messages = self::_messages($cNode);
                break;
            default:
                break;
            }
        }
        return $obj;
    }

    // }}}
    // _bool() {{{

    /**
     * Returns a boolean according to true/false possible strings.
     *
     * @param string $str The string to process
     *
     * @return boolean
     */
    private static function _bool($str)
    {
        return in_array(strtolower((string)$str), array('true', '1', 'on', 'yes'));
    }

    // }}}
    // _messages() {{{

    /**
     * Returns an array of custom messages for the element
     *
     * @param DOMNode $node The messages node to process
     *
     * @return array an array of messages
     *
     * @see Console_CommandLine::$messages
     * @see Console_CommandLine_Element::$messages
     */
    private static function _messages(DOMNode $node)
    {
        $messages = array();

        foreach ($node->childNodes as $cNode) {
            if ($cNode->nodeType == XML_ELEMENT_NODE) {
                $name  = $cNode->getAttribute('name');
                $value = trim($cNode->nodeValue);

                $messages[$name] = $value;
            }
        }

        return $messages;
    }

    // }}}
}
