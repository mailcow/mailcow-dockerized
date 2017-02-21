<?php

/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Endroid\QrCode;

use Endroid\QrCode\Exceptions\DataDoesntExistsException;
use Endroid\QrCode\Exceptions\FreeTypeLibraryMissingException;
use Endroid\QrCode\Exceptions\ImageFunctionFailedException;
use Endroid\QrCode\Exceptions\VersionTooLargeException;
use Endroid\QrCode\Exceptions\ImageSizeTooLargeException;
use Endroid\QrCode\Exceptions\ImageFunctionUnknownException;
use ReflectionFunction;

/**
 * Generate QR Code.
 */
class QrCode
{
    /** @const int Error Correction Level Low (7%) */
    const LEVEL_LOW = 1;

    /** @const int Error Correction Level Medium (15%) */
    const LEVEL_MEDIUM = 0;

    /** @const int Error Correction Level Quartile (25%) */
    const LEVEL_QUARTILE = 3;

    /** @const int Error Correction Level High (30%) */
    const LEVEL_HIGH = 2;

    /** @const string Image type png */
    const IMAGE_TYPE_PNG = 'png';

    /** @const string Image type gif */
    const IMAGE_TYPE_GIF = 'gif';

    /** @const string Image type jpeg */
    const IMAGE_TYPE_JPEG = 'jpeg';

    /** @const string Image type wbmp */
    const IMAGE_TYPE_WBMP = 'wbmp';

    /** @const int Horizontal label alignment to the center of image */
    const LABEL_HALIGN_CENTER = 0;

    /** @const int Horizontal label alignment to the left side of image */
    const LABEL_HALIGN_LEFT = 1;

    /** @const int Horizontal label alignment to the left border of QR Code */
    const LABEL_HALIGN_LEFT_BORDER = 2;

    /** @const int Horizontal label alignment to the left side of QR Code */
    const LABEL_HALIGN_LEFT_CODE = 3;

    /** @const int Horizontal label alignment to the right side of image */
    const LABEL_HALIGN_RIGHT = 4;

    /** @const int Horizontal label alignment to the right border of QR Code */
    const LABEL_HALIGN_RIGHT_BORDER = 5;

    /** @const int Horizontal label alignment to the right side of QR Code */
    const LABEL_HALIGN_RIGHT_CODE = 6;

    /** @const int Vertical label alignment to the top */
    const LABEL_VALIGN_TOP = 1;

    /** @const int Vertical label alignment to the top and hide border */
    const LABEL_VALIGN_TOP_NO_BORDER = 2;

    /** @const int Vertical label alignment to the middle*/
    const LABEL_VALIGN_MIDDLE = 3;

    /** @const int Vertical label alignment to the bottom */
    const LABEL_VALIGN_BOTTOM = 4;

    /** @var string */
    protected $logo = null;

    protected $logo_size = 48;

    /** @var string */
    protected $text = '';

    /** @var int */
    protected $size = 0;

    /** @var int */
    protected $padding = 16;

    /** @var bool */
    protected $draw_quiet_zone = false;

    /** @var bool */
    protected $draw_border = false;

    /** @var array */
    protected $color_foreground = array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0);

    /** @var array */
    protected $color_background = array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0);

    /** @var string */
    protected $label = '';

    /** @var int */
    protected $label_font_size = 16;

    /** @var string */
    protected $label_font_path = '';

    /** @var int */
    protected $label_halign = self::LABEL_HALIGN_CENTER;

    /** @var int */
    protected $label_valign = self::LABEL_VALIGN_MIDDLE;

    /** @var resource */
    protected $image = null;

    /** @var int */
    protected $version;

    /** @var int */
    protected $error_correction = self::LEVEL_MEDIUM;

    /** @var array */
    protected $error_corrections_available = array(
        self::LEVEL_LOW,
        self::LEVEL_MEDIUM,
        self::LEVEL_QUARTILE,
        self::LEVEL_HIGH,
    );

    /** @var int */
    protected $module_size;

    /** @var string */
    protected $image_type = self::IMAGE_TYPE_PNG;

    /** @var array */
    protected $image_types_available = array(
        self::IMAGE_TYPE_GIF,
        self::IMAGE_TYPE_PNG,
        self::IMAGE_TYPE_JPEG,
        self::IMAGE_TYPE_WBMP,
    );

    /** @var string */
    protected $image_path;

    /** @var string */
    protected $path;

    /** @var int */
    protected $structure_append_n;

    /** @var int */
    protected $structure_append_m;

    /** @var int */
    protected $structure_append_parity;

    /** @var string */
    protected $structure_append_original_data;

    /**
     * Class constructor.
     *
     * @param string $text
     */
    public function __construct($text = '')
    {
        $this->setPath(__DIR__.'/../assets/data');
        $this->setImagePath(__DIR__.'/../assets/image');
        $this->setLabelFontPath(__DIR__.'/../assets/font/opensans.ttf');
        $this->setText($text);
    }

    /**
     * Set structure append.
     *
     * @param int    $n
     * @param int    $m
     * @param int    $parity        Parity
     * @param string $original_data Original data
     *
     * @return QrCode
     */
    public function setStructureAppend($n, $m, $parity, $original_data)
    {
        $this->structure_append_n = $n;
        $this->structure_append_m = $m;
        $this->structure_append_parity = $parity;
        $this->structure_append_original_data = $original_data;

        return $this;
    }

    /**
     * Set QR Code version.
     *
     * @param int $version QR Code version
     *
     * @return QrCode
     */
    public function setVersion($version)
    {
        if ($version <= 40 && $version >= 0) {
            $this->version = $version;
        }

        return $this;
    }

    /**
     * Return QR Code version.
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set QR Code error correction level.
     *
     * @param mixed $error_correction Error Correction Level
     *
     * @return QrCode
     */
    public function setErrorCorrection($error_correction)
    {
        if (!is_numeric($error_correction)) {
            $level_constant = 'Endroid\QrCode\QrCode::LEVEL_'.strtoupper($error_correction);
            $error_correction = constant($level_constant);
        }

        if (in_array($error_correction, $this->error_corrections_available)) {
            $this->error_correction = $error_correction;
        }

        return $this;
    }

    /**
     * Return QR Code error correction level.
     *
     * @return int
     */
    public function getErrorCorrection()
    {
        return $this->error_correction;
    }

    /**
     * Set QR Code module size.
     *
     * @param int $module_size Module size
     *
     * @return QrCode
     */
    public function setModuleSize($module_size)
    {
        $this->module_size = $module_size;

        return $this;
    }

    /**
     * Return QR Code module size.
     *
     * @return int
     */
    public function getModuleSize()
    {
        return $this->module_size;
    }

    /**
     * Set image type for rendering.
     *
     * @param string $image_type Image type
     *
     * @return QrCode
     */
    public function setImageType($image_type)
    {
        if (in_array($image_type, $this->image_types_available)) {
            $this->image_type = $image_type;
        }

        return $this;
    }

    /**
     * Return image type for rendering.
     *
     * @return string
     */
    public function getImageType()
    {
        return $this->image_type;
    }

    /**
     * Set image type for rendering via extension.
     *
     * @param string $extension Image extension
     *
     * @return QrCode
     */
    public function setExtension($extension)
    {
        if ($extension == 'jpg') {
            $this->setImageType('jpeg');
        } else {
            $this->setImageType($extension);
        }

        return $this;
    }

    /**
     * Set path to the images directory.
     *
     * @param string $image_path Image directory
     *
     * @return QrCode
     */
    public function setImagePath($image_path)
    {
        $this->image_path = $image_path;

        return $this;
    }

    /**
     * Return path to the images directory.
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->image_path;
    }

    /**
     * Set path to the data directory.
     *
     * @param string $path Data directory
     *
     * @return QrCode
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Return path to the data directory.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set logo in QR Code.
     *
     * @param string $logo Logo Path
     *
     * @throws Exceptions\DataDoesntExistsException
     *
     * @return QrCode
     */
    public function setLogo($logo)
    {
        if (!file_exists($logo)) {
            throw new DataDoesntExistsException("$logo file does not exist");
        }

        $this->logo = $logo;

        return $this;
    }

    /**
     * Set logo size in QR Code(default 48).
     *
     * @param int $logo_size Logo Size
     *
     * @return QrCode
     */
    public function setLogoSize($logo_size)
    {
        $this->logo_size = $logo_size;

        return $this;
    }

    /**
     * Set text to hide in QR Code.
     *
     * @param string $text Text to hide
     *
     * @return QrCode
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Return text that will be hid in QR Code.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set QR Code size (width).
     *
     * @param int $size Width of the QR Code
     *
     * @return QrCode
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Return QR Code size (width).
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Set padding around the QR Code.
     *
     * @param int $padding Padding around QR Code
     *
     * @return QrCode
     */
    public function setPadding($padding)
    {
        $this->padding = $padding;

        return $this;
    }

    /**
     * Return padding around the QR Code.
     *
     * @return int
     */
    public function getPadding()
    {
        return $this->padding;
    }

    /**
     * Set draw required four-module wide margin.
     *
     * @param bool $draw_quiet_zone State of required four-module wide margin drawing
     *
     * @return QrCode
     */
    public function setDrawQuietZone($draw_quiet_zone)
    {
        $this->draw_quiet_zone = $draw_quiet_zone;

        return $this;
    }

    /**
     * Return draw required four-module wide margin.
     *
     * @return bool
     */
    public function getDrawQuietZone()
    {
        return $this->draw_quiet_zone;
    }

    /**
     * Set draw border around QR Code.
     *
     * @param bool $draw_border State of border drawing
     *
     * @return QrCode
     */
    public function setDrawBorder($draw_border)
    {
        $this->draw_border = $draw_border;

        return $this;
    }

    /**
     * Return draw border around QR Code.
     *
     * @return bool
     */
    public function getDrawBorder()
    {
        return $this->draw_border;
    }

    /**
     * Set QR Code label (text).
     *
     * @param int|string $label Label to print under QR code
     *
     * @return QrCode
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Return QR Code label (text).
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set QR Code label font size.
     *
     * @param int $label_font_size Font size of the QR code label
     *
     * @return QrCode
     */
    public function setLabelFontSize($label_font_size)
    {
        $this->label_font_size = $label_font_size;

        return $this;
    }

    /**
     * Return QR Code label font size.
     *
     * @return int
     */
    public function getLabelFontSize()
    {
        return $this->label_font_size;
    }

    /**
     * Set QR Code label font path.
     *
     * @param int $label_font_path Path to the QR Code label's TTF font file
     *
     * @return QrCode
     */
    public function setLabelFontPath($label_font_path)
    {
        $this->label_font_path = $label_font_path;

        return $this;
    }

    /**
     * Return path to the QR Code label's TTF font file.
     *
     * @return string
     */
    public function getLabelFontPath()
    {
        return $this->label_font_path;
    }

    /**
     * Set label horizontal alignment.
     *
     * @param int $label_halign Label horizontal alignment
     *
     * @return QrCode
     */
    public function setLabelHalign($label_halign)
    {
        $this->label_halign = $label_halign;

        return $this;
    }

    /**
     * Return label horizontal alignment.
     *
     * @return int
     */
    public function getLabelHalign()
    {
        return $this->label_halign;
    }

    /**
     * Set label vertical alignment.
     *
     * @param int $label_valign Label vertical alignment
     *
     * @return QrCode
     */
    public function setLabelValign($label_valign)
    {
        $this->label_valign = $label_valign;

        return $this;
    }

    /**
     * Return label vertical alignment.
     *
     * @return int
     */
    public function getLabelValign()
    {
        return $this->label_valign;
    }

    /**
     * Set foreground color of the QR Code.
     *
     * @param array $color_foreground RGB color
     *
     * @return QrCode
     */
    public function setForegroundColor($color_foreground)
    {
        if (!isset($color_foreground['a'])) {
            $color_foreground['a'] = 0;
        }

        $this->color_foreground = $color_foreground;

        return $this;
    }

    /**
     * Return foreground color of the QR Code.
     *
     * @return array
     */
    public function getForegroundColor()
    {
        return $this->color_foreground;
    }

    /**
     * Set background color of the QR Code.
     *
     * @param array $color_background RGB color
     *
     * @return QrCode
     */
    public function setBackgroundColor($color_background)
    {
        if (!isset($color_background['a'])) {
            $color_background['a'] = 0;
        }

        $this->color_background = $color_background;

        return $this;
    }

    /**
     * Return background color of the QR Code.
     *
     * @return array
     */
    public function getBackgroundColor()
    {
        return $this->color_background;
    }

    /**
     * Return the image resource.
     *
     * @return resource
     */
    public function getImage()
    {
        if (empty($this->image)) {
            $this->create();
        }

        return $this->image;
    }

    /**
     * Return the data URI.
     *
     * @return string
     */
    public function getDataUri()
    {
        if (empty($this->image)) {
            $this->create();
        }

        ob_start();
        call_user_func('image'.$this->image_type, $this->image);
        $contents = ob_get_clean();

        return 'data:image/'.$this->image_type.';base64,'.base64_encode($contents);
    }

    /**
     * Render the QR Code then save it to given file name.
     *
     * @param string $filename File name of the QR Code
     *
     * @return QrCode
     */
    public function save($filename)
    {
        $this->render($filename);

        return $this;
    }

    /**
     * Render the QR Code then save it to given file name or
     * output it to the browser when file name omitted.
     *
     * @param null|string $filename File name of the QR Code
     * @param null|string $format   Format of the file (png, jpeg, jpg, gif, wbmp)
     *
     * @throws ImageFunctionUnknownException
     * @throws ImageFunctionFailedException
     *
     * @return QrCode
     */
    public function render($filename = null, $format = 'png')
    {
        $this->create();

        if ($format == 'jpg') {
            $format = 'jpeg';
        }

        if (!in_array($format, $this->image_types_available)) {
            $format = $this->image_type;
        }

        if (!function_exists('image'.$format)) {
            throw new ImageFunctionUnknownException('QRCode: function image'.$format.' does not exists.');
        }

        if ($filename === null) {
            $success = call_user_func('image'.$format, $this->image);
        } else {
            $success = call_user_func_array('image'.$format, array($this->image, $filename));
        }

        if ($success === false) {
            throw new ImageFunctionFailedException('QRCode: function image'.$format.' failed.');
        }

        return $this;
    }

    /**
     * Create QR Code and return its content.
     *
     * @param string|null $format Image type (gif, png, wbmp, jpeg)
     *
     * @throws ImageFunctionUnknownException
     * @throws ImageFunctionFailedException
     *
     * @return string
     */
    public function get($format = null)
    {
        $this->create();

        if ($format == 'jpg') {
            $format = 'jpeg';
        }

        if (!in_array($format, $this->image_types_available)) {
            $format = $this->image_type;
        }

        if (!function_exists('image'.$format)) {
            throw new ImageFunctionUnknownException('QRCode: function image'.$format.' does not exists.');
        }

        ob_start();
        $success = call_user_func('image'.$format, $this->image);

        if ($success === false) {
            throw new ImageFunctionFailedException('QRCode: function image'.$format.' failed.');
        }

        $content = ob_get_clean();

        return $content;
    }

    /**
     * Create the image.
     *
     * @throws Exceptions\DataDoesntExistsException
     * @throws Exceptions\VersionTooLargeException
     * @throws Exceptions\ImageSizeTooLargeException
     * @throws \OverflowException
     */
    public function create()
    {
        $image_path = $this->image_path;
        $path = $this->path;

        $version_ul = 40;

        $qrcode_data_string = $this->text;//Previously from $_GET["d"];

        $qrcode_error_correct = $this->error_correction;//Previously from $_GET["e"];
        $qrcode_module_size = $this->module_size;//Previously from $_GET["s"];
        $qrcode_version = $this->version;//Previously from $_GET["v"];
        $qrcode_image_type = $this->image_type;//Previously from $_GET["t"];

        $qrcode_structureappend_n = $this->structure_append_n;//Previously from $_GET["n"];
        $qrcode_structureappend_m = $this->structure_append_m;//Previously from $_GET["m"];
        $qrcode_structureappend_parity = $this->structure_append_parity;//Previously from $_GET["p"];
        $qrcode_structureappend_originaldata = $this->structure_append_original_data;//Previously from $_GET["o"];

        if ($qrcode_module_size > 0) {
        } else {
            if ($qrcode_image_type == 'jpeg') {
                $qrcode_module_size = 8;
            } else {
                $qrcode_module_size = 4;
            }
        }
        $data_length = strlen($qrcode_data_string);
        if ($data_length <= 0) {
            throw new DataDoesntExistsException('QRCode: data does not exist.');
        }
        $data_counter = 0;
        if ($qrcode_structureappend_n > 1
         && $qrcode_structureappend_n <= 16
         && $qrcode_structureappend_m > 0
         && $qrcode_structureappend_m <= 16) {
            $data_value[0] = 3;
            $data_bits[0] = 4;

            $data_value[1] = $qrcode_structureappend_m - 1;
            $data_bits[1] = 4;

            $data_value[2] = $qrcode_structureappend_n - 1;
            $data_bits[2] = 4;

            $originaldata_length = strlen($qrcode_structureappend_originaldata);
            if ($originaldata_length > 1) {
                $qrcode_structureappend_parity = 0;
                $i = 0;
                while ($i < $originaldata_length) {
                    $qrcode_structureappend_parity = ($qrcode_structureappend_parity ^ ord(substr($qrcode_structureappend_originaldata, $i, 1)));
                    ++$i;
                }
            }

            $data_value[3] = $qrcode_structureappend_parity;
            $data_bits[3] = 8;

            $data_counter = 4;
        }

        $data_bits[$data_counter] = 4;

        /*  --- determine encode mode */

        if (preg_match('/[^0-9]/', $qrcode_data_string) != 0) {
            if (preg_match("/[^0-9A-Z \$\*\%\+\.\/\:\-]/", $qrcode_data_string) != 0) {
                /*  --- 8bit byte mode */

                $codeword_num_plus = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8,
        8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8, );

                $data_value[$data_counter] = 4;
                ++$data_counter;
                $data_value[$data_counter] = $data_length;
                $data_bits[$data_counter] = 8;   /* #version 1-9 */
                $codeword_num_counter_value = $data_counter;

                ++$data_counter;
                $i = 0;
                while ($i < $data_length) {
                    $data_value[$data_counter] = ord(substr($qrcode_data_string, $i, 1));
                    $data_bits[$data_counter] = 8;
                    ++$data_counter;
                    ++$i;
                }
            } else {
                /* ---- alphanumeric mode */

                $codeword_num_plus = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2,
        4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, );

                $data_value[$data_counter] = 2;
                ++$data_counter;
                $data_value[$data_counter] = $data_length;
                $data_bits[$data_counter] = 9;  /* #version 1-9 */
                $codeword_num_counter_value = $data_counter;

                $alphanumeric_character_hash = array('0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, 'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14,
        'F' => 15, 'G' => 16, 'H' => 17, 'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23,
        'O' => 24, 'P' => 25, 'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31,
        'W' => 32, 'X' => 33, 'Y' => 34, 'Z' => 35, ' ' => 36, '$' => 37, '%' => 38, '*' => 39,
        '+' => 40, '-' => 41, '.' => 42, '/' => 43, ':' => 44, );

                $i = 0;
                ++$data_counter;
                while ($i < $data_length) {
                    if (($i % 2) == 0) {
                        $data_value[$data_counter] = $alphanumeric_character_hash[substr($qrcode_data_string, $i, 1)];
                        $data_bits[$data_counter] = 6;
                    } else {
                        $data_value[$data_counter] = $data_value[$data_counter] * 45 + $alphanumeric_character_hash[substr($qrcode_data_string, $i, 1)];
                        $data_bits[$data_counter] = 11;
                        ++$data_counter;
                    }
                    ++$i;
                }
            }
        } else {
            /* ---- numeric mode */

            $codeword_num_plus = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2,
        4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, );

            $data_value[$data_counter] = 1;
            ++$data_counter;
            $data_value[$data_counter] = $data_length;
            $data_bits[$data_counter] = 10;   /* #version 1-9 */
            $codeword_num_counter_value = $data_counter;

            $i = 0;
            ++$data_counter;
            while ($i < $data_length) {
                if (($i % 3) == 0) {
                    $data_value[$data_counter] = substr($qrcode_data_string, $i, 1);
                    $data_bits[$data_counter] = 4;
                } else {
                    $data_value[$data_counter] = $data_value[$data_counter] * 10 + substr($qrcode_data_string, $i, 1);
                    if (($i % 3) == 1) {
                        $data_bits[$data_counter] = 7;
                    } else {
                        $data_bits[$data_counter] = 10;
                        ++$data_counter;
                    }
                }
                ++$i;
            }
        }
        if (array_key_exists($data_counter, $data_bits) && $data_bits[$data_counter] > 0) {
            ++$data_counter;
        }
        $i = 0;
        $total_data_bits = 0;
        while ($i < $data_counter) {
            $total_data_bits += $data_bits[$i];
            ++$i;
        }

        $ecc_character_hash = array('L' => '1',
        'l' => '1',
        'M' => '0',
        'm' => '0',
        'Q' => '3',
        'q' => '3',
        'H' => '2',
        'h' => '2', );

        if (!is_numeric($qrcode_error_correct)) {
            $ec = @$ecc_character_hash[$qrcode_error_correct];
        } else {
            $ec = $qrcode_error_correct;
        }

        if (!$ec) {
            $ec = 0;
        }

        $max_data_bits = 0;

        $max_data_bits_array = array(
        0, 128, 224, 352, 512, 688, 864, 992, 1232, 1456, 1728,
        2032, 2320, 2672, 2920, 3320, 3624, 4056, 4504, 5016, 5352,
        5712, 6256, 6880, 7312, 8000, 8496, 9024, 9544, 10136, 10984,
        11640, 12328, 13048, 13800, 14496, 15312, 15936, 16816, 17728, 18672,

        152, 272, 440, 640, 864, 1088, 1248, 1552, 1856, 2192,
        2592, 2960, 3424, 3688, 4184, 4712, 5176, 5768, 6360, 6888,
        7456, 8048, 8752, 9392, 10208, 10960, 11744, 12248, 13048, 13880,
        14744, 15640, 16568, 17528, 18448, 19472, 20528, 21616, 22496, 23648,

        72, 128, 208, 288, 368, 480, 528, 688, 800, 976,
        1120, 1264, 1440, 1576, 1784, 2024, 2264, 2504, 2728, 3080,
        3248, 3536, 3712, 4112, 4304, 4768, 5024, 5288, 5608, 5960,
        6344, 6760, 7208, 7688, 7888, 8432, 8768, 9136, 9776, 10208,

        104, 176, 272, 384, 496, 608, 704, 880, 1056, 1232,
        1440, 1648, 1952, 2088, 2360, 2600, 2936, 3176, 3560, 3880,
        4096, 4544, 4912, 5312, 5744, 6032, 6464, 6968, 7288, 7880,
        8264, 8920, 9368, 9848, 10288, 10832, 11408, 12016, 12656, 13328,
        );
        if (!is_numeric($qrcode_version)) {
            $qrcode_version = 0;
        }
        if (!$qrcode_version) {
            /* #--- auto version select */
            $i = 1 + 40 * $ec;
            $j = $i + 39;
            $qrcode_version = 1;
            while ($i <= $j) {
                if (($max_data_bits_array[$i]) >= $total_data_bits + $codeword_num_plus[$qrcode_version]) {
                    $max_data_bits = $max_data_bits_array[$i];
                    break;
                }
                ++$i;
                ++$qrcode_version;
            }
        } else {
            $max_data_bits = $max_data_bits_array[$qrcode_version + 40 * $ec];
        }
        if ($qrcode_version > $version_ul) {
            throw new VersionTooLargeException('QRCode : version too large');
        }

        $total_data_bits += $codeword_num_plus[$qrcode_version];
        $data_bits[$codeword_num_counter_value] += $codeword_num_plus[$qrcode_version];

        $max_codewords_array = array(0, 26, 44, 70, 100, 134, 172, 196, 242,
        292, 346, 404, 466, 532, 581, 655, 733, 815, 901, 991, 1085, 1156,
        1258, 1364, 1474, 1588, 1706, 1828, 1921, 2051, 2185, 2323, 2465,
        2611, 2761, 2876, 3034, 3196, 3362, 3532, 3706, );

        $max_codewords = $max_codewords_array[$qrcode_version];
        $max_modules_1side = 17 + ($qrcode_version << 2);

        $matrix_remain_bit = array(0, 0, 7, 7, 7, 7, 7, 0, 0, 0, 0, 0, 0, 0, 3, 3, 3, 3, 3, 3, 3,
        4, 4, 4, 4, 4, 4, 4, 3, 3, 3, 3, 3, 3, 3, 0, 0, 0, 0, 0, 0, );

        /* ---- read version ECC data file */

        $byte_num = $matrix_remain_bit[$qrcode_version] + ($max_codewords << 3);
        $filename = $path.'/qrv'.$qrcode_version.'_'.$ec.'.dat';
        $fp1 = fopen($filename, 'rb');
        $matx = fread($fp1, $byte_num);
        $maty = fread($fp1, $byte_num);
        $masks = fread($fp1, $byte_num);
        $fi_x = fread($fp1, 15);
        $fi_y = fread($fp1, 15);
        $rs_ecc_codewords = ord(fread($fp1, 1));
        $rso = fread($fp1, 128);
        fclose($fp1);

        $matrix_x_array = unpack('C*', $matx);
        $matrix_y_array = unpack('C*', $maty);
        $mask_array = unpack('C*', $masks);

        $rs_block_order = unpack('C*', $rso);

        $format_information_x2 = unpack('C*', $fi_x);
        $format_information_y2 = unpack('C*', $fi_y);

        $format_information_x1 = array(0, 1, 2, 3, 4, 5, 7, 8, 8, 8, 8, 8, 8, 8, 8);
        $format_information_y1 = array(8, 8, 8, 8, 8, 8, 8, 8, 7, 5, 4, 3, 2, 1, 0);

        $max_data_codewords = ($max_data_bits >> 3);

        $filename = $path.'/rsc'.$rs_ecc_codewords.'.dat';
        $fp0 = fopen($filename, 'rb');
        $i = 0;
        $rs_cal_table_array = array();
        while ($i < 256) {
            $rs_cal_table_array[$i] = fread($fp0, $rs_ecc_codewords);
            ++$i;
        }
        fclose($fp0);

        /*  --- set terminator */

        if ($total_data_bits <= $max_data_bits - 4) {
            $data_value[$data_counter] = 0;
            $data_bits[$data_counter] = 4;
        } else {
            if ($total_data_bits < $max_data_bits) {
                $data_value[$data_counter] = 0;
                $data_bits[$data_counter] = $max_data_bits - $total_data_bits;
            } else {
                if ($total_data_bits > $max_data_bits) {
                    throw new \OverflowException('QRCode: overflow error');
                }
            }
        }

        /* ----divide data by 8bit */

        $i = 0;
        $codewords_counter = 0;
        $codewords[0] = 0;
        $remaining_bits = 8;

        while ($i <= $data_counter) {
            $buffer = @$data_value[$i];
            $buffer_bits = @$data_bits[$i];

            $flag = 1;
            while ($flag) {
                if ($remaining_bits > $buffer_bits) {
                    $codewords[$codewords_counter] = ((@$codewords[$codewords_counter] << $buffer_bits) | $buffer);
                    $remaining_bits -= $buffer_bits;
                    $flag = 0;
                } else {
                    $buffer_bits -= $remaining_bits;
                    $codewords[$codewords_counter] = (($codewords[$codewords_counter] << $remaining_bits) | ($buffer >> $buffer_bits));

                    if ($buffer_bits == 0) {
                        $flag = 0;
                    } else {
                        $buffer = ($buffer & ((1 << $buffer_bits) - 1));
                        $flag = 1;
                    }

                    ++$codewords_counter;
                    if ($codewords_counter < $max_data_codewords - 1) {
                        $codewords[$codewords_counter] = 0;
                    }
                    $remaining_bits = 8;
                }
            }
            ++$i;
        }
        if ($remaining_bits != 8) {
            $codewords[$codewords_counter] = $codewords[$codewords_counter] << $remaining_bits;
        } else {
            --$codewords_counter;
        }

        /* ----  set padding character */

        if ($codewords_counter < $max_data_codewords - 1) {
            $flag = 1;
            while ($codewords_counter < $max_data_codewords - 1) {
                ++$codewords_counter;
                if ($flag == 1) {
                    $codewords[$codewords_counter] = 236;
                } else {
                    $codewords[$codewords_counter] = 17;
                }
                $flag = $flag * (-1);
            }
        }

        /* ---- RS-ECC prepare */

        $i = 0;
        $j = 0;
        $rs_block_number = 0;
        $rs_temp[0] = '';

        while ($i < $max_data_codewords) {
            $rs_temp[$rs_block_number] .= chr($codewords[$i]);
            ++$j;

            if ($j >= $rs_block_order[$rs_block_number + 1] - $rs_ecc_codewords) {
                $j = 0;
                ++$rs_block_number;
                $rs_temp[$rs_block_number] = '';
            }
            ++$i;
        }

        /*
        #
        # RS-ECC main
        #
        */

        $rs_block_number = 0;
        $rs_block_order_num = count($rs_block_order);

        while ($rs_block_number < $rs_block_order_num) {
            $rs_codewords = $rs_block_order[$rs_block_number + 1];
            $rs_data_codewords = $rs_codewords - $rs_ecc_codewords;

            $rstemp = $rs_temp[$rs_block_number].str_repeat(chr(0), $rs_ecc_codewords);
            $padding_data = str_repeat(chr(0), $rs_data_codewords);

            $j = $rs_data_codewords;
            while ($j > 0) {
                $first = ord(substr($rstemp, 0, 1));

                if ($first) {
                    $left_chr = substr($rstemp, 1);
                    $cal = $rs_cal_table_array[$first].$padding_data;
                    $rstemp = $left_chr ^ $cal;
                } else {
                    $rstemp = substr($rstemp, 1);
                }

                --$j;
            }

            $codewords = array_merge($codewords, unpack('C*', $rstemp));

            ++$rs_block_number;
        }

        /* ---- flash matrix */
        $matrix_content = array();
        $i = 0;
        while ($i < $max_modules_1side) {
            $j = 0;
            while ($j < $max_modules_1side) {
                $matrix_content[$j][$i] = 0;
                ++$j;
            }
            ++$i;
        }

        /* --- attach data */

        $i = 0;
        while ($i < $max_codewords) {
            $codeword_i = $codewords[$i];
            $j = 8;
            while ($j >= 1) {
                $codeword_bits_number = ($i << 3) +  $j;
                $matrix_content[ $matrix_x_array[$codeword_bits_number] ][ $matrix_y_array[$codeword_bits_number] ] = ((255 * ($codeword_i & 1)) ^ $mask_array[$codeword_bits_number]);
                $codeword_i = $codeword_i >> 1;
                --$j;
            }
            ++$i;
        }

        $matrix_remain = $matrix_remain_bit[$qrcode_version];
        while ($matrix_remain) {
            $remain_bit_temp = $matrix_remain + ($max_codewords << 3);
            $matrix_content[ $matrix_x_array[$remain_bit_temp] ][ $matrix_y_array[$remain_bit_temp] ] = (255 ^ $mask_array[$remain_bit_temp]);
            --$matrix_remain;
        }

        #--- mask select

        $min_demerit_score = 0;
        $hor_master = '';
        $ver_master = '';
        $k = 0;
        while ($k < $max_modules_1side) {
            $l = 0;
            while ($l < $max_modules_1side) {
                $hor_master = $hor_master.chr($matrix_content[$l][$k]);
                $ver_master = $ver_master.chr($matrix_content[$k][$l]);
                ++$l;
            }
            ++$k;
        }
        $i = 0;
        $all_matrix = $max_modules_1side * $max_modules_1side;
        $mask_number = 0;
        while ($i < 8) {
            $demerit_n1 = 0;
            $ptn_temp = array();
            $bit = 1 << $i;
            $bit_r = (~$bit) & 255;
            $bit_mask = str_repeat(chr($bit), $all_matrix);
            $hor = $hor_master & $bit_mask;
            $ver = $ver_master & $bit_mask;

            $ver_shift1 = $ver.str_repeat(chr(170), $max_modules_1side);
            $ver_shift2 = str_repeat(chr(170), $max_modules_1side).$ver;
            $ver_shift1_0 = $ver.str_repeat(chr(0), $max_modules_1side);
            $ver_shift2_0 = str_repeat(chr(0), $max_modules_1side).$ver;
            $ver_or = chunk_split(~($ver_shift1 | $ver_shift2), $max_modules_1side, chr(170));
            $ver_and = chunk_split(~($ver_shift1_0 & $ver_shift2_0), $max_modules_1side, chr(170));

            $hor = chunk_split(~$hor, $max_modules_1side, chr(170));
            $ver = chunk_split(~$ver, $max_modules_1side, chr(170));
            $hor = $hor.chr(170).$ver;

            $n1_search = '/'.str_repeat(chr(255), 5).'+|'.str_repeat(chr($bit_r), 5).'+/';
            $n3_search = chr($bit_r).chr(255).chr($bit_r).chr($bit_r).chr($bit_r).chr(255).chr($bit_r);

            $demerit_n3 = substr_count($hor, $n3_search) * 40;
            $demerit_n4 = floor(abs(((100 * (substr_count($ver, chr($bit_r)) / ($byte_num))) - 50) / 5)) * 10;

            $n2_search1 = '/'.chr($bit_r).chr($bit_r).'+/';
            $n2_search2 = '/'.chr(255).chr(255).'+/';
            $demerit_n2 = 0;
            preg_match_all($n2_search1, $ver_and, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n2 += (strlen($str_temp) - 1);
            }
            $ptn_temp = array();
            preg_match_all($n2_search2, $ver_or, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n2 += (strlen($str_temp) - 1);
            }
            $demerit_n2 *= 3;

            $ptn_temp = array();

            preg_match_all($n1_search, $hor, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n1 += (strlen($str_temp) - 2);
            }

            $demerit_score = $demerit_n1 + $demerit_n2 + $demerit_n3 + $demerit_n4;

            if ($demerit_score <= $min_demerit_score || $i == 0) {
                $mask_number = $i;
                $min_demerit_score = $demerit_score;
            }

            ++$i;
        }

        $mask_content = 1 << $mask_number;

        # --- format information

        $format_information_value = (($ec << 3) | $mask_number);
        $format_information_array = array('101010000010010', '101000100100101',
        '101111001111100', '101101101001011', '100010111111001', '100000011001110',
        '100111110010111', '100101010100000', '111011111000100', '111001011110011',
        '111110110101010', '111100010011101', '110011000101111', '110001100011000',
        '110110001000001', '110100101110110', '001011010001001', '001001110111110',
        '001110011100111', '001100111010000', '000011101100010', '000001001010101',
        '000110100001100', '000100000111011', '011010101011111', '011000001101000',
        '011111100110001', '011101000000110', '010010010110100', '010000110000011',
        '010111011011010', '010101111101101', );
        $i = 0;
        while ($i < 15) {
            $content = substr($format_information_array[$format_information_value], $i, 1);

            $matrix_content[$format_information_x1[$i]][$format_information_y1[$i]] = $content * 255;
            $matrix_content[$format_information_x2[$i + 1]][$format_information_y2[$i + 1]] = $content * 255;
            ++$i;
        }

        $mib = $max_modules_1side + 8;

        if ($this->size == 0) {
            $this->size = $mib * $qrcode_module_size;
            if ($this->size > 1480) {
                throw new ImageSizeTooLargeException('QRCode: image size too large');
            }
        }

        $image_width = $this->size + $this->padding * 2;
        $image_height = $this->size + $this->padding * 2;

        if (!empty($this->label)) {
            if (!function_exists('imagettfbbox')) {
                throw new FreeTypeLibraryMissingException('QRCode: missing function "imagettfbbox". Did you install the FreeType library?');
            }
            $font_box = imagettfbbox($this->label_font_size, 0, $this->label_font_path, $this->label);
            $label_width = (int) $font_box[2] - (int) $font_box[0];
            $label_height = (int) $font_box[0] - (int) $font_box[7];

            if ($this->label_valign == self::LABEL_VALIGN_MIDDLE) {
                $image_height += $label_height + $this->padding;
            } else {
                $image_height += $label_height;
            }
        }

        $output_image = imagecreate($image_width, $image_height);
        imagecolorallocate($output_image, 255, 255, 255);

        $image_path = $image_path.'/qrv'.$qrcode_version.'.png';

        $base_image = imagecreatefrompng($image_path);
        $code_size = $this->size;
        $module_size = function ($size = 1) use ($code_size, $base_image) {
            return round($code_size / imagesx($base_image) * $size);
        };

        $col[1] = imagecolorallocate($base_image, 0, 0, 0);
        $col[0] = imagecolorallocate($base_image, 255, 255, 255);

        $i = 4;
        $mxe = 4 + $max_modules_1side;
        $ii = 0;
        while ($i < $mxe) {
            $j = 4;
            $jj = 0;
            while ($j < $mxe) {
                if ($matrix_content[$ii][$jj] & $mask_content) {
                    imagesetpixel($base_image, $i, $j, $col[1]);
                }
                ++$j;
                ++$jj;
            }
            ++$i;
            ++$ii;
        }

        if ($this->draw_quiet_zone == true) {
            imagecopyresampled($output_image, $base_image, $this->padding, $this->padding, 0, 0, $this->size, $this->size, $mib, $mib);
        } else {
            imagecopyresampled($output_image, $base_image, $this->padding, $this->padding, 4, 4, $this->size, $this->size, $mib - 8, $mib - 8);
        }

        if ($this->draw_border == true) {
            $border_width = $this->padding;
            $border_height = $this->size + $this->padding - 1;
            $border_color = imagecolorallocate($output_image, 0, 0, 0);
            imagerectangle($output_image, $border_width, $border_width, $border_height, $border_height, $border_color);
        }

        if (!empty($this->label)) {
            // Label horizontal alignment
            switch ($this->label_halign) {
                case self::LABEL_HALIGN_LEFT:
                    $font_x = 0;
                    break;

                case self::LABEL_HALIGN_LEFT_BORDER:
                    $font_x = $this->padding;
                    break;

                case self::LABEL_HALIGN_LEFT_CODE:
                    if ($this->draw_quiet_zone == true) {
                        $font_x = $this->padding + $module_size(4);
                    } else {
                        $font_x = $this->padding;
                    }
                    break;

                case self::LABEL_HALIGN_RIGHT:
                    $font_x = $this->size + ($this->padding * 2) - $label_width;
                    break;

                case self::LABEL_HALIGN_RIGHT_BORDER:
                    $font_x = $this->size + $this->padding - $label_width;
                    break;

                case self::LABEL_HALIGN_RIGHT_CODE:
                    if ($this->draw_quiet_zone == true) {
                        $font_x = $this->size + $this->padding - $label_width - $module_size(4);
                    } else {
                        $font_x = $this->size + $this->padding - $label_width;
                    }
                    break;

                default:
                    $font_x = floor($image_width - $label_width) / 2;
            }

            // Label vertical alignment
            switch ($this->label_valign) {
                case self::LABEL_VALIGN_TOP_NO_BORDER:
                    $font_y = $image_height - $this->padding - 1;
                    break;

                case self::LABEL_VALIGN_BOTTOM:
                    $font_y = $image_height;
                    break;

                default:
                    $font_y = $image_height - $this->padding;
            }

            $label_bg_x1 = $font_x - $module_size(2);
            $label_bg_y1 = $font_y - $label_height;
            $label_bg_x2 = $font_x + $label_width + $module_size(2);
            $label_bg_y2 = $font_y;

            $color = imagecolorallocate($output_image, 0, 0, 0);
            $label_bg_color = imagecolorallocate($output_image, 255, 255, 255);

            imagefilledrectangle($output_image, $label_bg_x1, $label_bg_y1, $label_bg_x2, $label_bg_y2, $label_bg_color);
            imagettftext($output_image, $this->label_font_size, 0, $font_x, $font_y, $color, $this->label_font_path, $this->label);
        }

        $imagecolorset_function = new ReflectionFunction('imagecolorset');
        $allow_alpha = $imagecolorset_function->getNumberOfParameters() == 6;

        if ($this->color_background != null) {
            $index = imagecolorclosest($output_image, 255, 255, 255);
            if ($allow_alpha) {
                imagecolorset($output_image, $index, $this->color_background['r'], $this->color_background['g'], $this->color_background['b'], $this->color_background['a']);
            } else {
                imagecolorset($output_image, $index, $this->color_background['r'], $this->color_background['g'], $this->color_background['b']);
            }
        }

        if ($this->color_foreground != null) {
            $index = imagecolorclosest($output_image, 0, 0, 0);
            if ($allow_alpha) {
                imagecolorset($output_image, $index, $this->color_foreground['r'], $this->color_foreground['g'], $this->color_foreground['b'], $this->color_foreground['a']);
            } else {
                imagecolorset($output_image, $index, $this->color_foreground['r'], $this->color_foreground['g'], $this->color_foreground['b']);
            }
        }

        if (!empty($this->logo)) {
            $output_image_org = $output_image;
            $output_image = imagecreatetruecolor($image_width, $image_height);
            imagecopy($output_image, $output_image_org, 0, 0, 0, 0, $image_width, $image_height);

            $logo_image = call_user_func('imagecreatefrom'.$this->image_type, $this->logo);
            if (!$logo_image) {
                throw new ImageFunctionFailedException('imagecreatefrom'.$this->image_type.' '.$this->logo.' failed');
            }
            $src_w = imagesx($logo_image);
            $src_h = imagesy($logo_image);

            $dst_x = ($image_width - $this->logo_size) / 2;
            $dst_y = ($this->size + $this->padding * 2 - $this->logo_size) / 2;

            $successful = imagecopyresampled($output_image, $logo_image, $dst_x, $dst_y, 0, 0, $this->logo_size, $this->logo_size, $src_w, $src_h);
            if (!$successful) {
                throw new ImageFunctionFailedException('add logo [image'.$this->format.'] failed.');
            }
            imagedestroy($logo_image);
        }
        $this->image = $output_image;
    }
}
