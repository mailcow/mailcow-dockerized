<?php
namespace RobThree\Auth\Providers\Qr;

use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Writer\PngWriter;

class EndroidQrCodeWithLogoProvider extends EndroidQrCodeProvider
{
    protected $logoPath;
    protected $logoSize;

    /**
     * Adds an image to the middle of the QR Code.
     * @param string $path Path to an image file
     * @param array|int $size Just the width, or [width, height]
     */
    public function setLogo($path, $size = null)
    {
        $this->logoPath = $path;
        $this->logoSize = (array)$size;
    }

    public function getQRCodeImage($qrtext, $size)
    {
        if (!$this->endroid4) {
            return $this->qrCodeInstance($qrtext, $size)->writeString();
        }

        $logo = null;
        if ($this->logoPath) {
            $logo = Logo::create($this->logoPath);
            if ($this->logoSize) {
                $logo->setResizeToWidth($this->logoSize[0]);
                if (isset($this->logoSize[1])) {
                    $logo->setResizeToHeight($this->logoSize[1]);
                }
            }
        }
        $writer = new PngWriter();
        return $writer->write($this->qrCodeInstance($qrtext, $size), $logo)->getString();
    }

    protected function qrCodeInstance($qrtext, $size) {
        $qrCode = parent::qrCodeInstance($qrtext, $size);

        if (!$this->endroid4 && $this->logoPath) {
            $qrCode->setLogoPath($this->logoPath);
            if ($this->logoSize) {
                $qrCode->setLogoSize($this->logoSize[0], isset($this->logoSize[1]) ? $this->logoSize[1] : null);
            }
        }

        return $qrCode;
    }
}
