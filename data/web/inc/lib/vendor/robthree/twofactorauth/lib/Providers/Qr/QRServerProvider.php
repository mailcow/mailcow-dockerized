<?php

namespace RobThree\Auth\Providers\Qr;

// http://goqr.me/api/doc/create-qr-code/
class QRServerProvider extends BaseHTTPQRCodeProvider 
{
    public $errorcorrectionlevel;
    public $margin;
    public $qzone;
    public $bgcolor;
    public $color;
    public $format;

    function __construct($verifyssl = false, $errorcorrectionlevel = 'L', $margin = 4, $qzone = 1, $bgcolor = 'ffffff', $color = '000000', $format = 'png') 
    {
        if (!is_bool($verifyssl))
            throw new QRException('VerifySSL must be bool');

        $this->verifyssl = $verifyssl;
        
        $this->errorcorrectionlevel = $errorcorrectionlevel;
        $this->margin = $margin;
        $this->qzone = $qzone;
        $this->bgcolor = $bgcolor;
        $this->color = $color;
        $this->format = $format;
    }
    
    public function getMimeType() 
    {
        switch (strtolower($this->format))
        {
        	case 'png':
                return 'image/png';
        	case 'gif':
                return 'image/gif';
        	case 'jpg':
        	case 'jpeg':
                return 'image/jpeg';
        	case 'svg':
                return 'image/svg+xml';
        	case 'eps':
                return 'application/postscript';
        }
        throw new \QRException(sprintf('Unknown MIME-type: %s', $this->format));
    }
    
    public function getQRCodeImage($qrtext, $size) 
    {
        return $this->getContent($this->getUrl($qrtext, $size));
    }
    
    private function decodeColor($value) 
    {
        return vsprintf('%d-%d-%d', sscanf($value, "%02x%02x%02x"));
    }
    
    public function getUrl($qrtext, $size) 
    {
        return 'https://api.qrserver.com/v1/create-qr-code/'
            . '?size=' . $size . 'x' . $size
            . '&ecc=' . strtoupper($this->errorcorrectionlevel)
            . '&margin=' . $this->margin
            . '&qzone=' . $this->qzone
            . '&bgcolor=' . $this->decodeColor($this->bgcolor)
            . '&color=' . $this->decodeColor($this->color)
            . '&format=' . strtolower($this->format)
            . '&data=' . rawurlencode($qrtext);
    }
}