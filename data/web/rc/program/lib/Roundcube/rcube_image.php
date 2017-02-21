<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Image resizer and converter                                         |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Image resizer and converter
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_image
{
    private $image_file;

    const TYPE_GIF = 1;
    const TYPE_JPG = 2;
    const TYPE_PNG = 3;
    const TYPE_TIF = 4;

    public static $extensions = array(
        self::TYPE_GIF => 'gif',
        self::TYPE_JPG => 'jpg',
        self::TYPE_PNG => 'png',
        self::TYPE_TIF => 'tif',
    );


    /**
     * Class constructor
     *
     * @param string $filename Image file name/path
     */
    function __construct($filename)
    {
        $this->image_file = $filename;
    }

    /**
     * Get image properties.
     *
     * @return mixed Hash array with image props like type, width, height
     */
    public function props()
    {
        // use GD extension
        if (function_exists('getimagesize') && ($imsize = @getimagesize($this->image_file))) {
            $width   = $imsize[0];
            $height  = $imsize[1];
            $gd_type = $imsize[2];
            $type    = image_type_to_extension($gd_type, false);
            $channels = $imsize['channels'];
        }

        // use ImageMagick
        if (!$type && ($data = $this->identify())) {
            list($type, $width, $height) = $data;
            $channels = null;
        }

        if ($type) {
            return array(
                'type'    => $type,
                'gd_type' => $gd_type,
                'width'   => $width,
                'height'  => $height,
                'channels' => $channels,
            );
        }

        return null;
    }

    /**
     * Resize image to a given size. Use only to shrink an image.
     * If an image is smaller than specified size it will be not resized.
     *
     * @param int     $size           Max width/height size
     * @param string  $filename       Output filename
     * @param boolean $browser_compat Convert to image type displayable by any browser
     *
     * @return mixed Output type on success, False on failure
     */
    public function resize($size, $filename = null, $browser_compat = false)
    {
        $result  = false;
        $rcube   = rcube::get_instance();
        $convert = $rcube->config->get('im_convert_path', false);
        $props   = $this->props();

        if (empty($props)) {
            return false;
        }

        if (!$filename) {
            $filename = $this->image_file;
        }

        // use Imagemagick
        if ($convert || class_exists('Imagick', false)) {
            $p['out'] = $filename;
            $p['in']  = $this->image_file;
            $type     = $props['type'];

            if (!$type && ($data = $this->identify())) {
                $type = $data[0];
            }

            $type = strtr($type, array("jpeg" => "jpg", "tiff" => "tif", "ps" => "eps", "ept" => "eps"));
            $p['intype'] = $type;

            // convert to an image format every browser can display
            if ($browser_compat && !in_array($type, array('jpg','gif','png'))) {
                $type = 'jpg';
            }

            // If only one dimension is greater than the limit convert doesn't
            // work as expected, we need to calculate new dimensions
            $scale = $size / max($props['width'], $props['height']);

            // if file is smaller than the limit, we do nothing
            // but copy original file to destination file
            if ($scale >= 1 && $p['intype'] == $type) {
                $result = ($this->image_file == $filename || copy($this->image_file, $filename)) ? '' : false;
            }
            else {
                $valid_types = "bmp,eps,gif,jp2,jpg,png,svg,tif";

                if (in_array($type, explode(',', $valid_types))) { // Valid type?
                    if ($scale >= 1) {
                        $width  = $props['width'];
                        $height = $props['height'];
                    }
                    else {
                        $width  = intval($props['width']  * $scale);
                        $height = intval($props['height'] * $scale);
                    }

                    // use ImageMagick in command line
                    if ($convert) {
                        $p += array(
                            'type'    => $type,
                            'quality' => 75,
                            'size'    => $width . 'x' . $height,
                        );

                        $result = rcube::exec($convert . ' 2>&1 -flatten -auto-orient -colorspace sRGB -strip'
                            . ' -quality {quality} -resize {size} {intype}:{in} {type}:{out}', $p);
                    }
                    // use PHP's Imagick class
                    else {
                        try {
                            $image = new Imagick($this->image_file);

                            try {
                                // it throws exception on formats not supporting these features
                                $image->setImageBackgroundColor('white');
                                $image->setImageAlphaChannel(11);
                                $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                            }
                            catch (Exception $e) {
                                // ignore errors
                            }

                            $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
                            $image->setImageCompressionQuality(75);
                            $image->setImageFormat($type);
                            $image->stripImage();
                            $image->scaleImage($width, $height);

                            if ($image->writeImage($filename)) {
                                $result = '';
                            }
                        }
                        catch (Exception $e) {
                            rcube::raise_error($e, true, false);
                        }
                    }
                }
            }

            if ($result === '') {
                @chmod($filename, 0600);
                return $type;
            }
        }

        // do we have enough memory? (#1489937)
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' && !$this->mem_check($props)) {
            return false;
        }

        // use GD extension
        if ($props['gd_type']) {
            if ($props['gd_type'] == IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
                $image = imagecreatefromjpeg($this->image_file);
                $type  = 'jpg';
            }
            else if ($props['gd_type'] == IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
                $image = imagecreatefromgif($this->image_file);
                $type  = 'gif';
            }
            else if ($props['gd_type'] == IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
                $image = imagecreatefrompng($this->image_file);
                $type  = 'png';
            }
            else {
                // @TODO: print error to the log?
                return false;
            }

            if ($image === false) {
                return false;
            }

            $scale = $size / max($props['width'], $props['height']);

            // Imagemagick resize is implemented in shrinking mode (see -resize argument above)
            // we do the same here, if an image is smaller than specified size
            // we do nothing but copy original file to destination file
            if ($scale >= 1) {
                $result = $this->image_file == $filename || copy($this->image_file, $filename);
            }
            else {
                $width     = intval($props['width']  * $scale);
                $height    = intval($props['height'] * $scale);
                $new_image = imagecreatetruecolor($width, $height);

                if ($new_image === false) {
                    return false;
                }

                // Fix transparency of gif/png image
                if ($props['gd_type'] != IMAGETYPE_JPEG) {
                    imagealphablending($new_image, false);
                    imagesavealpha($new_image, true);
                    $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
                    imagefilledrectangle($new_image, 0, 0, $width, $height, $transparent);
                }

                imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, $props['width'], $props['height']);
                $image = $new_image;

                // fix rotation of image if EXIF data exists and specifies rotation (GD strips the EXIF data)
                if ($this->image_file && $type == 'jpg' && function_exists('exif_read_data')) {
                    $exif = exif_read_data($this->image_file);
                    if ($exif && $exif['Orientation']) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $image = imagerotate($image, 180, 0);
                                break;
                            case 6:
                                $image = imagerotate($image, -90, 0);
                                break;
                            case 8:
                                $image = imagerotate($image, 90, 0);
                                break;
                        }
                    }
                }

                if ($props['gd_type'] == IMAGETYPE_JPEG) {
                    $result = imagejpeg($image, $filename, 75);
                }
                elseif($props['gd_type'] == IMAGETYPE_GIF) {
                    $result = imagegif($image, $filename);
                }
                elseif($props['gd_type'] == IMAGETYPE_PNG) {
                    $result = imagepng($image, $filename, 6, PNG_ALL_FILTERS);
                }
            }

            if ($result) {
                @chmod($filename, 0600);
                return $type;
            }
        }

        // @TODO: print error to the log?
        return false;
    }

    /**
     * Convert image to a given type
     *
     * @param int    $type     Destination file type (see class constants)
     * @param string $filename Output filename (if empty, original file will be used
     *                         and filename extension will be modified)
     *
     * @return bool True on success, False on failure
     */
    public function convert($type, $filename = null)
    {
        $rcube   = rcube::get_instance();
        $convert = $rcube->config->get('im_convert_path', false);

        if (!$filename) {
            $filename = $this->image_file;

            // modify extension
            if ($extension = self::$extensions[$type]) {
                $filename = preg_replace('/\.[^.]+$/', '', $filename) . '.' . $extension;
            }
        }

        // use ImageMagick in command line
        if ($convert) {
            $p['in']   = $this->image_file;
            $p['out']  = $filename;
            $p['type'] = self::$extensions[$type];

            $result = rcube::exec($convert . ' 2>&1 -colorspace sRGB -strip -quality 75 {in} {type}:{out}', $p);

            if ($result === '') {
                chmod($filename, 0600);
                return true;
            }
        }

        // use PHP's Imagick class
        if (class_exists('Imagick', false)) {
            try {
                $image = new Imagick($this->image_file);

                $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
                $image->setImageCompressionQuality(75);
                $image->setImageFormat(self::$extensions[$type]);
                $image->stripImage();

                if ($image->writeImage($filename)) {
                    @chmod($filename, 0600);
                    return true;
                }
            }
            catch (Exception $e) {
                rcube::raise_error($e, true, false);
            }
        }

        // use GD extension (TIFF isn't supported)
        $props = $this->props();

        // do we have enough memory? (#1489937)
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' && !$this->mem_check($props)) {
            return false;
        }

        if ($props['gd_type']) {
            if ($props['gd_type'] == IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
                $image = imagecreatefromjpeg($this->image_file);
            }
            else if ($props['gd_type'] == IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
                $image = imagecreatefromgif($this->image_file);
            }
            else if ($props['gd_type'] == IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
                $image = imagecreatefrompng($this->image_file);
            }
            else {
                // @TODO: print error to the log?
                return false;
            }

            if ($type == self::TYPE_JPG) {
                $result = imagejpeg($image, $filename, 75);
            }
            else if ($type == self::TYPE_GIF) {
                $result = imagegif($image, $filename);
            }
            else if ($type == self::TYPE_PNG) {
                $result = imagepng($image, $filename, 6, PNG_ALL_FILTERS);
            }

            if ($result) {
                @chmod($filename, 0600);
                return true;
            }
        }

        // @TODO: print error to the log?
        return false;
    }

    /**
     * Checks if image format conversion is supported
     *
     * @return boolean True if specified format can be converted to another format
     */
    public static function is_convertable($mimetype = null)
    {
        $rcube = rcube::get_instance();

        // @TODO: check if specified mimetype is really supported
        return class_exists('Imagick', false) || $rcube->config->get('im_convert_path');
    }

    /**
     * ImageMagick based image properties read.
     */
    private function identify()
    {
        $rcube = rcube::get_instance();

        // use ImageMagick in command line
        if ($cmd = $rcube->config->get('im_identify_path')) {
            $args = array('in' => $this->image_file, 'format' => "%m %[fx:w] %[fx:h]");
            $id   = rcube::exec($cmd. ' 2>/dev/null -format {format} {in}', $args);

            if ($id) {
                return explode(' ', strtolower($id));
            }
        }

        // use PHP's Imagick class
        if (class_exists('Imagick', false)) {
            try {
                $image = new Imagick($this->image_file);

                return array(
                    strtolower($image->getImageFormat()),
                    $image->getImageWidth(),
                    $image->getImageHeight(),
                );
            }
            catch (Exception $e) {}
        }
    }

    /**
     * Check if we have enough memory to load specified image
     */
    private function mem_check($props)
    {
        // image size is unknown, we can't calculate required memory
        if (!$props['width']) {
            return true;
        }

        // channels: CMYK - 4, RGB - 3
        $multip = ($props['channels'] ?: 3) + 1;

        // calculate image size in memory (in bytes)
        $size = $props['width'] * $props['height'] * $multip;
        return rcube_utils::mem_check($size);
    }
}
