---
layout: post
title: endroid/qr-code
---

## Installation

In order to use this provider, you will need to install the library at version 3 and its dependencies

```
composer require endroid/qr-code ^3.0
```

You will also need the PHP gd extension installing.

## Optional Configuration

Argument                | Default value
------------------------|---------------
`$bgcolor`              | `'ffffff'`
`$color`                | `'000000'`
`$margin`               | `0`
`$errorcorrectionlevel` | `'H'`

## Logo

If you make use of `EndroidQrCodeWithLogoProvider` then you have access to the `setLogo` function on the provider so you may add a logo to the centre of your QR code.

```php
use RobThree\Auth\TwoFactorAuth\Providers\Qr\EndroidQrCodeWithLogoProvider;

$qrCodeProvider = new EndroidQrCodeWithLogoProvider();

$qrCodeProvider->setLogo('/path/to/your/image');
```

You can see how to also set the size of the logo in the [source code](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Qr/EndroidQrCodeWithLogoProvider.php).
