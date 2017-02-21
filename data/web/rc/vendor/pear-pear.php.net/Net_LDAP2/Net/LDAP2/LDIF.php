<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_LDIF interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2009 Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Includes
*/
require_once 'PEAR.php';
require_once 'Net/LDAP2.php';
require_once 'Net/LDAP2/Entry.php';
require_once 'Net/LDAP2/Util.php';

/**
* LDIF capabilitys for Net_LDAP2, closely taken from PERLs Net::LDAP
*
* It provides a means to convert between Net_LDAP2_Entry objects and LDAP entries
* represented in LDIF format files. Reading and writing are supported and may
* manipulate single entries or lists of entries.
*
* Usage example:
* <code>
* // Read and parse an ldif-file into Net_LDAP2_Entry objects
* // and print out the DNs. Store the entries for later use.
* require 'Net/LDAP2/LDIF.php';
* $options = array(
*       'onerror' => 'die'
* );
* $entries = array();
* $ldif = new Net_LDAP2_LDIF('test.ldif', 'r', $options);
* do {
*       $entry = $ldif->read_entry();
*       $dn    = $entry->dn();
*       echo " done building entry: $dn\n";
*       array_push($entries, $entry);
* } while (!$ldif->eof());
* $ldif->done();
*
*
* // write those entries to another file
* $ldif = new Net_LDAP2_LDIF('test.out.ldif', 'w', $options);
* $ldif->write_entry($entries);
* $ldif->done();
* </code>
*
* @category Net
* @package  Net_LDAP2
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP22/
* @see      http://www.ietf.org/rfc/rfc2849.txt
* @todo     Error handling should be PEARified
* @todo     LDAPv3 controls are not implemented yet
*/
class Net_LDAP2_LDIF extends PEAR
{
    /**
    * Options
    *
    * @access protected
    * @var array
    */
    protected $_options = array('encode'    => 'base64',
                                'onerror'   => null,
                                'change'    => 0,
                                'lowercase' => 0,
                                'sort'      => 0,
                                'version'   => null,
                                'wrap'      => 78,
                                'raw'       => ''
                               );

    /**
    * Errorcache
    *
    * @access protected
    * @var array
    */
    protected $_error = array('error' => null,
                              'line'  => 0
                             );

    /**
    * Filehandle for read/write
    *
    * @access protected
    * @var array
    */
    protected $_FH = null;

    /**
    * Says, if we opened the filehandle ourselves
    *
    * @access protected
    * @var array
    */
    protected $_FH_opened = false;

    /**
    * Linecounter for input file handle
    *
    * @access protected
    * @var array
    */
    protected $_input_line = 0;

    /**
    * counter for processed entries
    *
    * @access protected
    * @var int
    */
    protected $_entrynum = 0;

    /**
    * Mode we are working in
    *
    * Either 'r', 'a' or 'w'
    *
    * @access protected
    * @var string
    */
    protected $_mode = false;

    /**
    * Tells, if the LDIF version string was already written
    *
    * @access protected
    * @var boolean
    */
    protected $_version_written = false;

    /**
    * Cache for lines that have build the current entry
    *
    * @access protected
    * @var boolean
    */
    protected $_lines_cur = array();

    /**
    * Cache for lines that will build the next entry
    *
    * @access protected
    * @var boolean
    */
    protected $_lines_next = array();

    /**
    * Open LDIF file for reading or for writing
    *
    * new (FILE):
    * Open the file read-only. FILE may be the name of a file
    * or an already open filehandle.
    * If the file doesn't exist, it will be created if in write mode.
    *
    * new (FILE, MODE, OPTIONS):
    *     Open the file with the given MODE (see PHPs fopen()), eg "w" or "a".
    *     FILE may be the name of a file or an already open filehandle.
    *     PERLs Net_LDAP2 "FILE|" mode does not work curently.
    *
    *     OPTIONS is an associative array and may contain:
    *       encode => 'none' | 'canonical' | 'base64'
    *         Some DN values in LDIF cannot be written verbatim and have to be encoded in some way:
    *         'none'       No encoding.
    *         'canonical'  See "canonical_dn()" in Net::LDAP::Util.
    *         'base64'     Use base64. (default, this differs from the Perl interface.
    *                                   The perl default is "none"!)
    *
    *       onerror => 'die' | 'warn' | NULL
    *         Specify what happens when an error is detected.
    *         'die'  Net_LDAP2_LDIF will croak with an appropriate message.
    *         'warn' Net_LDAP2_LDIF will warn (echo) with an appropriate message.
    *         NULL   Net_LDAP2_LDIF will not warn (default), use error().
    *
    *       change => 1
    *         Write entry changes to the LDIF file instead of the entries itself. I.e. write LDAP
    *         operations acting on the entries to the file instead of the entries contents.
    *         This writes the changes usually carried out by an update() to the LDIF file.
    *
    *       lowercase => 1
    *         Convert attribute names to lowercase when writing.
    *
    *       sort => 1
    *         Sort attribute names when writing entries according to the rule:
    *         objectclass first then all other attributes alphabetically sorted by attribute name
    *
    *       version => '1'
    *         Set the LDIF version to write to the resulting LDIF file.
    *         According to RFC 2849 currently the only legal value for this option is 1.
    *         When this option is set Net_LDAP2_LDIF tries to adhere more strictly to
    *         the LDIF specification in RFC2489 in a few places.
    *         The default is NULL meaning no version information is written to the LDIF file.
    *
    *       wrap => 78
    *         Number of columns where output line wrapping shall occur.
    *         Default is 78. Setting it to 40 or lower inhibits wrapping.
    *
    *       raw => REGEX
    *         Use REGEX to denote the names of attributes that are to be
    *         considered binary in search results if writing entries.
    *         Example: raw => "/(?i:^jpegPhoto|;binary)/i"
    *
    * @param string|ressource $file    Filename or filehandle
    * @param string           $mode    Mode to open filename
    * @param array            $options Options like described above
    */
    public function __construct($file, $mode = 'r', $options = array())
    {
        parent::__construct('Net_LDAP2_Error'); // default error class

        // First, parse options
        // todo: maybe implement further checks on possible values
        foreach ($options as $option => $value) {
            if (!array_key_exists($option, $this->_options)) {
                $this->dropError('Net_LDAP2_LDIF error: option '.$option.' not known!');
                return;
            } else {
                $this->_options[$option] = strtolower($value);
            }
        }

        // setup LDIF class
        $this->version($this->_options['version']);

        // setup file mode
        if (!preg_match('/^[rwa]\+?$/', $mode)) {
            $this->dropError('Net_LDAP2_LDIF error: file mode '.$mode.' not supported!');
        } else {
            $this->_mode = $mode;

            // setup filehandle
            if (is_resource($file)) {
                // TODO: checks on mode possible?
                $this->_FH =& $file;
            } else {
                $imode = substr($this->_mode, 0, 1);
                if ($imode == 'r') {
                    if (!file_exists($file)) {
                        $this->dropError('Unable to open '.$file.' for read: file not found');
                        $this->_mode = false;
                    }
                    if (!is_readable($file)) {
                        $this->dropError('Unable to open '.$file.' for read: permission denied');
                        $this->_mode = false;
                    }
                }

                if (($imode == 'w' || $imode == 'a')) {
                    if (file_exists($file)) {
                        if (!is_writable($file)) {
                            $this->dropError('Unable to open '.$file.' for write: permission denied');
                            $this->_mode = false;
                        }
                    } else {
                        if (!@touch($file)) {
                            $this->dropError('Unable to create '.$file.' for write: permission denied');
                            $this->_mode = false;
                        }
                    }
                }

                if ($this->_mode) {
                    $this->_FH = @fopen($file, $this->_mode);
                    if (false === $this->_FH) {
                        // Fallback; should never be reached if tests above are good enough!
                        $this->dropError('Net_LDAP2_LDIF error: Could not open file '.$file);
                    } else {
                        $this->_FH_opened = true;
                    }
                }
            }
        }
    }

    /**
    * Read one entry from the file and return it as a Net::LDAP::Entry object.
    *
    * @return Net_LDAP2_Entry
    */
    public function read_entry()
    {
        // read fresh lines, set them as current lines and create the entry
        $attrs = $this->next_lines(true);
        if (count($attrs) > 0) {
            $this->_lines_cur = $attrs;
        }
        return $this->current_entry();
    }

    /**
    * Returns true when the end of the file is reached.
    *
    * @return boolean
    */
    public function eof()
    {
        return feof($this->_FH);
    }

    /**
    * Write the entry or entries to the LDIF file.
    *
    * If you want to build an LDIF file containing several entries AND
    * you want to call write_entry() several times, you must open the filehandle
    * in append mode ("a"), otherwise you will always get the last entry only.
    *
    * @param Net_LDAP2_Entry|array $entries Entry or array of entries
    *
    * @return void
    * @todo implement operations on whole entries (adding a whole entry)
    */
    public function write_entry($entries)
    {
        if (!is_array($entries)) {
            $entries = array($entries);
        }

        foreach ($entries as $entry) {
            $this->_entrynum++;
            if (!$entry instanceof Net_LDAP2_Entry) {
                $this->dropError('Net_LDAP2_LDIF error: entry '.$this->_entrynum.' is not an Net_LDAP2_Entry object');
            } else {
                if ($this->_options['change']) {
                    // LDIF change mode
                    // fetch change information from entry
                    $entry_attrs_changes = $entry->getChanges();
                    $num_of_changes      = count($entry_attrs_changes['add'])
                                           + count($entry_attrs_changes['replace'])
                                           + count($entry_attrs_changes['delete']);


                    $is_changed = ($num_of_changes > 0 || $entry->willBeDeleted() || $entry->willBeMoved());

                    // write version if not done yet
                    // also write DN of entry
                    if ($is_changed) {
                        if (!$this->_version_written) {
                            $this->write_version();
                        }
                        $this->writeDN($entry->currentDN());
                    }

                    // process changes
                    // TODO: consider DN add!
                    if ($entry->willBeDeleted()) {
                        $this->writeLine("changetype: delete".PHP_EOL);
                    } elseif ($entry->willBeMoved()) {
                        $this->writeLine("changetype: modrdn".PHP_EOL);
                        $olddn     = Net_LDAP2_Util::ldap_explode_dn($entry->currentDN(), array('casefold' => 'none')); // maybe gives a bug if using multivalued RDNs
                        $oldrdn    = array_shift($olddn);
                        $oldparent = implode(',', $olddn);
                        $newdn     = Net_LDAP2_Util::ldap_explode_dn($entry->dn(), array('casefold' => 'none')); // maybe gives a bug if using multivalued RDNs
                        $rdn       = array_shift($newdn);
                        $parent    = implode(',', $newdn);
                        $this->writeLine("newrdn: ".$rdn.PHP_EOL);
                        $this->writeLine("deleteoldrdn: 1".PHP_EOL);
                        if ($parent !== $oldparent) {
                            $this->writeLine("newsuperior: ".$parent.PHP_EOL);
                        }
                        // TODO: What if the entry has attribute changes as well?
                        //       I think we should check for that and make a dummy
                        //       entry with the changes that is written to the LDIF file
                    } elseif ($num_of_changes > 0) {
                        // write attribute change data
                        $this->writeLine("changetype: modify".PHP_EOL);
                        foreach ($entry_attrs_changes as $changetype => $entry_attrs) {
                            foreach ($entry_attrs as $attr_name => $attr_values) {
                                $this->writeLine("$changetype: $attr_name".PHP_EOL);
                                if ($attr_values !== null) $this->writeAttribute($attr_name, $attr_values, $changetype);
                                $this->writeLine("-".PHP_EOL);
                            }
                        }
                    }

                    // finish this entrys data if we had changes
                    if ($is_changed) {
                        $this->finishEntry();
                    }
                } else {
                    // LDIF-content mode
                    // fetch attributes for further processing
                    $entry_attrs = $entry->getValues();

                    // sort and put objectclass-attrs to first position
                    if ($this->_options['sort']) {
                        ksort($entry_attrs);
                        if (array_key_exists('objectclass', $entry_attrs)) {
                            $oc = $entry_attrs['objectclass'];
                            unset($entry_attrs['objectclass']);
                            $entry_attrs = array_merge(array('objectclass' => $oc), $entry_attrs);
                        }
                    }

                    // write data
                    if (!$this->_version_written) {
                        $this->write_version();
                    }
                    $this->writeDN($entry->dn());
                    foreach ($entry_attrs as $attr_name => $attr_values) {
                        $this->writeAttribute($attr_name, $attr_values);
                    }
                    $this->finishEntry();
                }
            }
        }
    }

    /**
    * Write version to LDIF
    *
    * If the object's version is defined, this method allows to explicitely write the version before an entry is written.
    * If not called explicitely, it gets called automatically when writing the first entry.
    *
    * @return void
    */
    public function write_version()
    {
        $this->_version_written = true;
        if (!is_null($this->version())) {
            return $this->writeLine('version: '.$this->version().PHP_EOL, 'Net_LDAP2_LDIF error: unable to write version');
        }
    }

    /**
    * Get or set LDIF version
    *
    * If called without arguments it returns the version of the LDIF file or NULL if no version has been set.
    * If called with an argument it sets the LDIF version to VERSION.
    * According to RFC 2849 currently the only legal value for VERSION is 1.
    *
    * @param int $version (optional) LDIF version to set
    *
    * @return int
    */
    public function version($version = null)
    {
        if ($version !== null) {
            if ($version != 1) {
                $this->dropError('Net_LDAP2_LDIF error: illegal LDIF version set');
            } else {
                $this->_options['version'] = $version;
            }
        }
        return $this->_options['version'];
    }

    /**
    * Returns the file handle the Net_LDAP2_LDIF object reads from or writes to.
    *
    * You can, for example, use this to fetch the content of the LDIF file yourself
    *
    * @return null|resource
    */
    public function &handle()
    {
        if (!is_resource($this->_FH)) {
            $this->dropError('Net_LDAP2_LDIF error: invalid file resource');
            $null = null;
            return $null;
        } else {
            return $this->_FH;
        }
    }

    /**
    * Clean up
    *
    * This method signals that the LDIF object is no longer needed.
    * You can use this to free up some memory and close the file handle.
    * The file handle is only closed, if it was opened from Net_LDAP2_LDIF.
    *
    * @return void
    */
    public function done()
    {
        // close FH if we opened it
        if ($this->_FH_opened) {
            fclose($this->handle());
        }

        // free variables
        foreach (get_object_vars($this) as $name => $value) {
            unset($this->$name);
        }
    }

    /**
    * Returns last error message if error was found.
    *
    * Example:
    * <code>
    *  $ldif->someAction();
    *  if ($ldif->error()) {
    *     echo "Error: ".$ldif->error()." at input line: ".$ldif->error_lines();
    *  }
    * </code>
    *
    * @param boolean $as_string If set to true, only the message is returned
    *
    * @return false|Net_LDAP2_Error
    */
    public function error($as_string = false)
    {
        if (Net_LDAP2::isError($this->_error['error'])) {
            return ($as_string)? $this->_error['error']->getMessage() : $this->_error['error'];
        } else {
            return false;
        }
    }

    /**
    * Returns lines that resulted in error.
    *
    * Perl returns an array of faulty lines in list context,
    * but we always just return an int because of PHPs language.
    *
    * @return int
    */
    public function error_lines()
    {
        return $this->_error['line'];
    }

    /**
    * Returns the current Net::LDAP::Entry object.
    *
    * @return Net_LDAP2_Entry|false
    */
    public function current_entry()
    {
        return $this->parseLines($this->current_lines());
    }

    /**
    * Parse LDIF lines of one entry into an Net_LDAP2_Entry object
    *
    * @param array $lines LDIF lines for one entry
    *
    * @return Net_LDAP2_Entry|false Net_LDAP2_Entry object for those lines
    * @todo what about file inclusions and urls? "jpegphoto:< file:///usr/local/directory/photos/fiona.jpg"
    */
    public function parseLines($lines)
    {
        // parse lines into an array of attributes and build the entry
        $attributes = array();
        $dn = false;
        foreach ($lines as $line) {
            if (preg_match('/^(\w+(;binary)?)(:|::|:<)\s(.+)$/', $line, $matches)) {
                $attr  =& $matches[1] . $matches[2];
                $delim =& $matches[3];
                $data  =& $matches[4];

                if ($delim == ':') {
                    // normal data
                    $attributes[$attr][] = $data;
                } elseif ($delim == '::') {
                    // base64 data
                    $attributes[$attr][] = base64_decode($data);
                } elseif ($delim == ':<') {
                    // file inclusion
                    // TODO: Is this the job of the LDAP-client or the server?
                    $this->dropError('File inclusions are currently not supported');
                    //$attributes[$attr][] = ...;
                } else {
                    // since the pattern above, the delimeter cannot be something else.
                    $this->dropError('Net_LDAP2_LDIF parsing error: invalid syntax at parsing entry line: '.$line);
                    continue;
                }

                if (strtolower($attr) == 'dn') {
                    // DN line detected
                    $dn = $attributes[$attr][0];  // save possibly decoded DN
                    unset($attributes[$attr]);    // remove wrongly added "dn: " attribute
                }
            } else {
                // line not in "attr: value" format -> ignore
                // maybe we should rise an error here, but this should be covered by
                // next_lines() already. A problem arises, if users try to feed data of
                // several entries to this method - the resulting entry will
                // get wrong attributes. However, this is already mentioned in the
                // methods documentation above.
            }
        }

        if (false === $dn) {
            $this->dropError('Net_LDAP2_LDIF parsing error: unable to detect DN for entry');
            return false;
        } else {
            $newentry = Net_LDAP2_Entry::createFresh($dn, $attributes);
            return $newentry;
        }
    }

    /**
    * Returns the lines that generated the current Net::LDAP::Entry object.
    *
    * Note that this returns an empty array if no lines have been read so far.
    *
    * @return array Array of lines
    */
    public function current_lines()
    {
        return $this->_lines_cur;
    }

    /**
    * Returns the lines that will generate the next Net::LDAP::Entry object.
    *
    * If you set $force to TRUE then you can iterate over the lines that build
    * up entries manually. Otherwise, iterating is done using {@link read_entry()}.
    * Force will move the file pointer forward, thus returning the next entries lines.
    *
    * Wrapped lines will be unwrapped. Comments are stripped.
    *
    * @param boolean $force Set this to true if you want to iterate over the lines manually
    *
    * @return array
    */
    public function next_lines($force = false)
    {
        // if we already have those lines, just return them, otherwise read
        if (count($this->_lines_next) == 0 || $force) {
            $this->_lines_next = array(); // empty in case something was left (if used $force)
            $entry_done        = false;
            $fh                = &$this->handle();
            $commentmode       = false; // if we are in an comment, for wrapping purposes
            $datalines_read    = 0;     // how many lines with data we have read

            while (!$entry_done && !$this->eof()) {
                $this->_input_line++;
                // Read line. Remove line endings, we want only data;
                // this is okay since ending spaces should be encoded
                $data = rtrim(fgets($fh));
                if ($data === false) {
                    // error only, if EOF not reached after fgets() call
                    if (!$this->eof()) {
                        $this->dropError('Net_LDAP2_LDIF error: error reading from file at input line '.$this->_input_line, $this->_input_line);
                    }
                    break;
                } else {
                    if (count($this->_lines_next) > 0 && preg_match('/^$/', $data)) {
                        // Entry is finished if we have an empty line after we had data
                        $entry_done = true;

                        // Look ahead if the next EOF is nearby. Comments and empty
                        // lines at the file end may cause problems otherwise
                        $current_pos = ftell($fh);
                        $data        = fgets($fh);
                        while (!feof($fh)) {
                            if (preg_match('/^\s*$/', $data) || preg_match('/^#/', $data)) {
                                // only empty lines or comments, continue to seek
                                // TODO: Known bug: Wrappings for comments are okay but are treaten as
                                //       error, since we do not honor comment mode here.
                                //       This should be a very theoretically case, however
                                //       i am willing to fix this if really necessary.
                                $this->_input_line++;
                                $current_pos = ftell($fh);
                                $data        = fgets($fh);
                            } else {
                                // Data found if non emtpy line and not a comment!!
                                // Rewind to position prior last read and stop lookahead
                                fseek($fh, $current_pos);
                                break;
                            }
                        }
                        // now we have either the file pointer at the beginning of
                        // a new data position or at the end of file causing feof() to return true

                    } else {
                        // build lines
                        if (preg_match('/^version:\s(.+)$/', $data, $match)) {
                            // version statement, set version
                            $this->version($match[1]);
                        } elseif (preg_match('/^\w+(;binary)?::?\s.+$/', $data)) {
                            // normal attribute: add line
                            $commentmode         = false;
                            $this->_lines_next[] = trim($data);
                            $datalines_read++;
                        } elseif (preg_match('/^\s(.+)$/', $data, $matches)) {
                            // wrapped data: unwrap if not in comment mode
                            // note that the \s above is some more liberal than
                            // the RFC requests as it also matches tabs etc.
                            if (!$commentmode) {
                                if ($datalines_read == 0) {
                                    // first line of entry: wrapped data is illegal
                                    $this->dropError('Net_LDAP2_LDIF error: illegal wrapping at input line '.$this->_input_line, $this->_input_line);
                                } else {
                                    $last                = array_pop($this->_lines_next);
                                    $last                = $last.$matches[1];
                                    $this->_lines_next[] = $last;
                                    $datalines_read++;
                                }
                            }
                        } elseif (preg_match('/^#/', $data)) {
                            // LDIF comments
                            $commentmode = true;
                        } elseif (preg_match('/^\s*$/', $data)) {
                            // empty line but we had no data for this
                            // entry, so just ignore this line
                            $commentmode = false;
                        } else {
                            $this->dropError('Net_LDAP2_LDIF error: invalid syntax at input line '.$this->_input_line, $this->_input_line);
                            continue;
                        }

                    }
                }
            }
        }
        return $this->_lines_next;
    }

    /**
    * Convert an attribute and value to LDIF string representation
    *
    * It honors correct encoding of values according to RFC 2849.
    * Line wrapping will occur at the configured maximum but only if
    * the value is greater than 40 chars.
    *
    * @param string $attr_name  Name of the attribute
    * @param string $attr_value Value of the attribute
    *
    * @access protected
    * @return string LDIF string for that attribute and value
    */
    protected function convertAttribute($attr_name, $attr_value)
    {
        // Handle empty attribute or process
        if (strlen($attr_value) == 0) {
            $attr_value = " ";
        } else {
            $base64 = false;
            // ASCII-chars that are NOT safe for the
            // start and for being inside the value.
            // These are the int values of those chars.
            $unsafe_init = array(0, 10, 13, 32, 58, 60);
            $unsafe      = array(0, 10, 13);

            // Test for illegal init char
            $init_ord = ord(substr($attr_value, 0, 1));
            if ($init_ord > 127 || in_array($init_ord, $unsafe_init)) {
                $base64 = true;
            }

            // Test for illegal content char
            for ($i = 0; $i < strlen($attr_value); $i++) {
                $char_ord = ord(substr($attr_value, $i, 1));
                if ($char_ord > 127 || in_array($char_ord, $unsafe)) {
                    $base64 = true;
                }
            }

            // Test for ending space
            if (substr($attr_value, -1) == ' ') {
                $base64 = true;
            }

            // If converting is needed, do it
            // Either we have some special chars or a matching "raw" regex
            if ($base64 || ($this->_options['raw'] && preg_match($this->_options['raw'], $attr_name))) {
                $attr_name .= ':';
                $attr_value = base64_encode($attr_value);
            }

            // Lowercase attr names if requested
            if ($this->_options['lowercase']) $attr_name = strtolower($attr_name);

            // Handle line wrapping
            if ($this->_options['wrap'] > 40 && strlen($attr_value) > $this->_options['wrap']) {
                $attr_value = wordwrap($attr_value, $this->_options['wrap'], PHP_EOL." ", true);
            }
        }

        return $attr_name.': '.$attr_value;
    }

    /**
    * Convert an entries DN to LDIF string representation
    *
    * It honors correct encoding of values according to RFC 2849.
    *
    * @param string $dn UTF8-Encoded DN
    *
    * @access protected
    * @return string LDIF string for that DN
    * @todo I am not sure, if the UTF8 stuff is correctly handled right now
    */
    protected function convertDN($dn)
    {
        $base64 = false;
        // ASCII-chars that are NOT safe for the
        // start and for being inside the dn.
        // These are the int values of those chars.
        $unsafe_init = array(0, 10, 13, 32, 58, 60);
        $unsafe      = array(0, 10, 13);

        // Test for illegal init char
        $init_ord = ord(substr($dn, 0, 1));
        if ($init_ord >= 127 || in_array($init_ord, $unsafe_init)) {
            $base64 = true;
        }

        // Test for illegal content char
        for ($i = 0; $i < strlen($dn); $i++) {
            $char = substr($dn, $i, 1);
            if (ord($char) >= 127 || in_array($init_ord, $unsafe)) {
                $base64 = true;
            }
        }

        // Test for ending space
        if (substr($dn, -1) == ' ') {
            $base64 = true;
        }

        // if converting is needed, do it
        return ($base64)? 'dn:: '.base64_encode($dn) : 'dn: '.$dn;
    }

    /**
    * Writes an attribute to the filehandle
    *
    * @param string       $attr_name   Name of the attribute
    * @param string|array $attr_values Single attribute value or array with attribute values
    *
    * @access protected
    * @return void
    */
    protected function writeAttribute($attr_name, $attr_values)
    {
        // write out attribute content
        if (!is_array($attr_values)) {
            $attr_values = array($attr_values);
        }
        foreach ($attr_values as $attr_val) {
            $line = $this->convertAttribute($attr_name, $attr_val).PHP_EOL;
            $this->writeLine($line, 'Net_LDAP2_LDIF error: unable to write attribute '.$attr_name.' of entry '.$this->_entrynum);
        }
    }

    /**
    * Writes a DN to the filehandle
    *
    * @param string $dn DN to write
    *
    * @access protected
    * @return void
    */
    protected function writeDN($dn)
    {
        // prepare DN
        if ($this->_options['encode'] == 'base64') {
            $dn = $this->convertDN($dn).PHP_EOL;
        } elseif ($this->_options['encode'] == 'canonical') {
            $dn = Net_LDAP2_Util::canonical_dn($dn, array('casefold' => 'none')).PHP_EOL;
        } else {
            $dn = $dn.PHP_EOL;
        }
        $this->writeLine($dn, 'Net_LDAP2_LDIF error: unable to write DN of entry '.$this->_entrynum);
    }

    /**
    * Finishes an LDIF entry
    *
    * @access protected
    * @return void
    */
    protected function finishEntry()
    {
        $this->writeLine(PHP_EOL, 'Net_LDAP2_LDIF error: unable to close entry '.$this->_entrynum);
    }

    /**
    * Just write an arbitary line to the filehandle
    *
    * @param string $line  Content to write
    * @param string $error If error occurs, drop this message
    *
    * @access protected
    * @return true|false
    */
    protected function writeLine($line, $error = 'Net_LDAP2_LDIF error: unable to write to filehandle')
    {
        if (is_resource($this->handle()) && fwrite($this->handle(), $line, strlen($line)) === false) {
            $this->dropError($error);
            return false;
        } else {
            return true;
        }
    }

    /**
    * Optionally raises an error and pushes the error on the error cache
    *
    * @param string $msg  Errortext
    * @param int    $line Line in the LDIF that caused the error
    *
    * @access protected
    * @return void
    */
    protected function dropError($msg, $line = null)
    {
        $this->_error['error'] = new Net_LDAP2_Error($msg);
        if ($line !== null) $this->_error['line'] = $line;

        if ($this->_options['onerror'] == 'die') {
            die($msg.PHP_EOL);
        } elseif ($this->_options['onerror'] == 'warn') {
            echo $msg.PHP_EOL;
        }
    }
}
?>
