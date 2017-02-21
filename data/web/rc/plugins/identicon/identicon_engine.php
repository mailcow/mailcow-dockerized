<?php

/**
 * @license GNU GPLv3+
 * @author Aleksander Machniak <alec@alec.pl>
 */
class identicon_engine
{
    private $ident;
    private $width;
    private $height;
    private $margin;
    private $binary;
    private $color;
    private $bgcolor  = '#F9F9F9';
    private $mimetype = 'image/png';
    private $palette  = array(
        '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3',
        '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39',
        '#FFEB3B', '#FFC107', '#FF9800', '#FF5722', '#795548', '#607D8B',
    );
    private $grid = array(
         0,  1,  2,  1,  0,
         3,  4,  5,  4,  3,
         6,  7,  8,  7,  6,
         9, 10, 11, 10,  9,
        12, 13, 14, 13, 12,
    );

    const GRID_SIZE = 5;
    const ICON_SIZE = 150;


    /**
     * Class constructor
     *
     * @param string $ident Unique identifier (email address)
     * @param int    $size  Icon size in pixels
     */
    public function __construct($ident, $size = null)
    {
        if (!$size) {
            $size = self::ICON_SIZE;
        }

        $this->ident  = $ident;
        $this->margin = (int) round($size / 10);
        $this->width  = (int) round(($size - $this->margin * 2) / self::GRID_SIZE) * self::GRID_SIZE + $this->margin * 2;
        $this->height = $this->width;

        $this->generate();
    }

    /**
     * Returns image mimetype
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    /**
     * Returns the image in binary form
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * Sends the image to the browser
     */
    public function sendOutput()
    {
        if ($this->binary) {
            $rcmail = rcmail::get_instance();
            $rcmail->output->future_expire_header(10 * 60);

            header('Content-Type: ' . $this->mimetype);
            header('Content-Size: ' . strlen($this->binary));
            echo $this->binary;

            return true;
        }

        return false;
    }

    /**
     * Icon generator
     */
    private function generate()
    {
        $ident = md5($this->ident, true);

        // set icon color
        $div         = intval(255/count($this->palette));
        $index       = intval(ord($ident[0]) / $div);
        $this->color = $this->palette[$index] ?: $this->palette[0];

        // set cell size
        $cell_width  = ($this->width - $this->margin * 2) / self::GRID_SIZE;
        $cell_height = ($this->height - $this->margin * 2) / self::GRID_SIZE;

        // create a grid
        foreach ($this->grid as $i => $idx) {
            $row_num    = intval($i / self::GRID_SIZE);
            $cell_num_h = $i - $row_num * self::GRID_SIZE;

            $this->grid[$i] = array(
                'active' => ord($ident[$idx]) % 2 > 0,
                'x1'     => $cell_width * $cell_num_h + $this->margin,
                'y1'     => $cell_height * $row_num + $this->margin,
                'x2'     => $cell_width * ($cell_num_h + 1) + $this->margin,
                'y2'     => $cell_height * ($row_num + 1) + $this->margin,
            );
        }

        // really generate the image using supported methods
        if (function_exists('imagepng')) {
            $this->generateGD();
        }
        else {
            // log an error
            $error = array(
                'code'    => 500,
                'message' => "PHP-GD module not found. It's required by identicon plugin.",
            );

            rcube::raise_error($error, true, false);
        }
    }

    /**
     * GD-based icon generation worker
     */
    private function generateGD()
    {
        $color   = $this->toRGB($this->color);
        $bgcolor = $this->toRGB($this->bgcolor);

        // create an image, setup colors
        $image   = imagecreate($this->width, $this->height);
        $color   = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        $bgcolor = imagecolorallocate($image, $bgcolor[0], $bgcolor[1], $bgcolor[2]);

        imagefilledrectangle($image, 0, 0, $this->width, $this->height, $bgcolor);

        // draw the grid created in self::generate()
        foreach ($this->grid as $item) {
            if ($item['active']) {
                imagefilledrectangle($image, $item['x1'], $item['y1'], $item['x2'], $item['y2'], $color);
            }
        }

        // generate an image and save it to a variable
        ob_start();
        imagepng($image, null, 6, PNG_ALL_FILTERS);
        $this->binary = ob_get_contents();
        ob_end_clean();

        // cleanup
        imagedestroy($image);
    }

    /**
     * Convert #FFFFFF color format to 3-value RGB
     */
    private function toRGB($color)
    {
        preg_match('/^#?([A-F0-9]{2})([A-F0-9]{2})([A-F0-9]{2})/i', $color, $m);

        return array(hexdec($m[1]), hexdec($m[2]), hexdec($m[3]));
    }
}
