<?php

namespace RobThree\Auth\Providers\Qr;

// http://qrickit.com/qrickit_apps/qrickit_api.php
class QRicketProvider extends BaseHTTPQRCodeProvider 
{
    public $errorcorrectionlevel;
    public $margin;
    public $qzone;
    public $bgcolor;
    public $color;
    public $format;

    function __construct($errorcorrectionlevel = 'L', $bgcolor = 'ffffff', $color = '000000', $format = 'p') 
    {
        $this->verifyssl = false;
        
        $this->errorcorrectionlevel = $errorcorrectionlevel;
        $this->bgcolor = $bgcolor;
        $this->color = $color;
        $this->format = $format;
    }
    
    public function getMimeType() 
    {
        switch (strtolower($this->format))
        {
        	case 'p':
                return 'image/png';
        	case 'g':
                return 'image/gif';
        	case 'j':
                return 'image/jpeg';
        }
        throw new \QRException(sprintf('Unknown MIME-type: %s', $this->format));
    }
    
    public function getQRCodeImage($qrtext, $size) 
    {
        return $this->getContent($this->getUrl($qrtext, $size));
    }
    
    public function getUrl($qrtext, $size) 
    {
        return 'http://qrickit.com/api/qr'
            . '?qrsize=' . $size
            . '&e=' . strtolower($this->errorcorrectionlevel)
            . '&bgdcolor=' . $this->bgcolor
            . '&fgdcolor=' . $this->color
            . '&t=' . strtolower($this->format)
            . '&d=' . rawurlencode($qrtext);
    }
}