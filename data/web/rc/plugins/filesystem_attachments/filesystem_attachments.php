<?php
/**
 * Filesystem Attachments
 *
 * This is a core plugin which provides basic, filesystem based
 * attachment temporary file handling.  This includes storing
 * attachments of messages currently being composed, writing attachments
 * to disk when drafts with attachments are re-opened and writing
 * attachments to disk for inline display in current html compositions.
 *
 * Developers may wish to extend this class when creating attachment
 * handler plugins:
 *   require_once('plugins/filesystem_attachments/filesystem_attachments.php');
 *   class myCustom_attachments extends filesystem_attachments
 *
 * @license GNU GPLv3+
 * @author Ziba Scott <ziba@umich.edu>
 * @author Thomas Bruederli <roundcube@gmail.com>
 */
class filesystem_attachments extends rcube_plugin
{
    public $task = '?(?!login).*';

    function init()
    {
        // Save a newly uploaded attachment
        $this->add_hook('attachment_upload', array($this, 'upload'));

        // Save an attachment from a non-upload source (draft or forward)
        $this->add_hook('attachment_save', array($this, 'save'));

        // Remove an attachment from storage
        $this->add_hook('attachment_delete', array($this, 'remove'));

        // When composing an html message, image attachments may be shown
        $this->add_hook('attachment_display', array($this, 'display'));

        // Get the attachment from storage and place it on disk to be sent
        $this->add_hook('attachment_get', array($this, 'get'));

        // Delete all temp files associated with this user
        $this->add_hook('attachments_cleanup', array($this, 'cleanup'));
        $this->add_hook('session_destroy', array($this, 'cleanup'));
    }

    /**
     * Save a newly uploaded attachment
     */
    function upload($args)
    {
        $args['status'] = false;
        $group  = $args['group'];
        $rcmail = rcube::get_instance();

        // use common temp dir for file uploads
        $temp_dir = $rcmail->config->get('temp_dir');
        $tmpfname = tempnam($temp_dir, 'rcmAttmnt');

        if (move_uploaded_file($args['path'], $tmpfname) && file_exists($tmpfname)) {
            $args['id']     = $this->file_id();
            $args['path']   = $tmpfname;
            $args['status'] = true;
            @chmod($tmpfname, 0600);  // set correct permissions (#1488996)

            // Note the file for later cleanup
            $_SESSION['plugins']['filesystem_attachments'][$group][$args['id']] = $tmpfname;
        }

        return $args;
    }

    /**
     * Save an attachment from a non-upload source (draft or forward)
     */
    function save($args)
    {
        $group = $args['group'];
        $args['status'] = false;

        if (!$args['path']) {
            $rcmail   = rcube::get_instance();
            $temp_dir = $rcmail->config->get('temp_dir');
            $tmp_path = tempnam($temp_dir, 'rcmAttmnt');

            if ($fp = fopen($tmp_path, 'w')) {
                fwrite($fp, $args['data']);
                fclose($fp);
                $args['path'] = $tmp_path;
            }
            else {
                return $args;
            }
        }

        $args['id']     = $this->file_id();
        $args['status'] = true;

        // Note the file for later cleanup
        $_SESSION['plugins']['filesystem_attachments'][$group][$args['id']] = $args['path'];

        return $args;
    }

    /**
     * Remove an attachment from storage
     * This is triggered by the remove attachment button on the compose screen
     */
    function remove($args)
    {
        $args['status'] = @unlink($args['path']);
        return $args;
    }

    /**
     * When composing an html message, image attachments may be shown
     * For this plugin, the file is already in place, just check for
     * the existance of the proper metadata
     */
    function display($args)
    {
        $args['status'] = file_exists($args['path']);
        return $args;
    }

    /**
     * This attachment plugin doesn't require any steps to put the file
     * on disk for use.  This stub function is kept here to make this 
     * class handy as a parent class for other plugins which may need it.
     */
    function get($args)
    {
        return $args;
    }

    /**
     * Delete all temp files associated with this user
     */
    function cleanup($args)
    {
        // $_SESSION['compose']['attachments'] is not a complete record of
        // temporary files because loading a draft or starting a forward copies
        // the file to disk, but does not make an entry in that array
        if (is_array($_SESSION['plugins']['filesystem_attachments'])) {
            foreach ($_SESSION['plugins']['filesystem_attachments'] as $group => $files) {
                if ($args['group'] && $args['group'] != $group) {
                    continue;
                }

                foreach ((array)$files as $filename) {
                    if(file_exists($filename)) {
                        unlink($filename);
                    }
                }

                unset($_SESSION['plugins']['filesystem_attachments'][$group]);
            }
        }
        return $args;
    }

    function file_id()
    {
        $userid = rcube::get_instance()->user->ID;
        list($usec, $sec) = explode(' ', microtime());
        $id = preg_replace('/[^0-9]/', '', $userid . $sec . $usec);

        // make sure the ID is really unique (#1489546)
        while ($this->find_file_by_id($id)) {
            // increment last four characters
            $x  = substr($id, -4) + 1;
            $id = substr($id, 0, -4) . sprintf('%04d', ($x > 9999 ? $x - 9999 : $x));
        }

        return $id;
    }

    private function find_file_by_id($id)
    {
        foreach ((array) $_SESSION['plugins']['filesystem_attachments'] as $group => $files) {
            if (isset($files[$id])) {
                return true;
            }
        }
    }
}
