<?php

namespace RobThree\Auth\Providers\Qr;

// https://developers.google.com/chart/infographics/docs/qr_codes
class GoogleQRCodeProvider extends BaseHTTPQRCodeProvider 
{
    public $errorcorrectionlevel;
    public $margin;

    function __construct($verifyssl = false, $errorcorrectionlevel = 'L', $margin = 1) 
    {
        if (!is_bool($verifyssl))
            throw new \QRException('VerifySSL must be bool');

        $this->verifyssl = $verifyssl;
        
        $this->errorcorrectionlevel = $errorcorrectionlevel;
        $this->margin = $margin;
    }
    
    public function getMimeType() 
    {
        return 'image/png';
    }
    
    public function getQRCodeImage($qrtext, $size) 
    {
        return $this->getContent($this->getUrl($qrtext, $size));
    }
    
    public function getUrl($qrtext, $size) 
    {
        return 'https://chart.googleapis.com/chart?cht=qr'
            . '&chs=' . $size . 'x' . $size
            . '&chld=' . $this->errorcorrectionlevel . '|' . $this->margin
            . '&chl=' . rawurlencode($qrtext);
    }
}