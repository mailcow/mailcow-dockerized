---
layout: post
title: QR Codes
---

An alternative way of communicating the secret to the user is through the use of [QR Codes](http://en.wikipedia.org/wiki/QR_code) which most if not all authenticator mobile apps can scan.

This can avoid accidental typing errors and also pre-set some text values within the users app.

You can display the QR Code as a base64 encoded image using the instance as follows, supplying the users name or other public identifier as the first argument

````php
<p>Scan the following image with your app:</p>
<img src="<?php echo $tfa->getQRCodeImageAsDataUri('Bob Ross', $secret); ?>">
````

You can also specify a size as a third argument which is 200 by default.

**Note:** by default, the QR code returned by the instance is generated from a third party across the internet. If the third party is encountering problems or is not available from where you have hosted your code, your user will likely experience a delay in seeing the QR code, if it even loads at all. This can be overcome with offline providers configured when you create the instance.

## Online Providers

[QRServerProvider](qr-codes/qr-server.html) (default)

[ImageChartsQRCodeProvider](qr-codes/image-charts.html)

[QRicketProvider](qr-codes/qrickit.html)

## Offline Providers

[EndroidQrCodeProvider](qr-codes/endroid.html) and EndroidQrCodeWithLogoProvider

[BaconQRCodeProvider](qr-codes/bacon.html)

**Note:** offline providers may have additional PHP requirements in order to function, you should study what is required before trying to make use of them.

## Custom Provider

If you wish to make your own QR Code provider to reference another service or library, it must implement the [IQRCodeProvider interface](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Qr/IQRCodeProvider.php).

It is recommended to use similar constructor arguments as the included providers to avoid big shifts when trying different providers.

## Using a specific provider

If you do not want to use the default QR code provider, you can specify the one you want to use when you create your instance.

```php
use RobThree\Auth\TwoFactorAuth;

$qrCodeProvider = new YourChosenProvider();

$tfa = new TwoFactorAuth(
	null,
	6,
	30,
	'sha1',
	$qrCodeProvider
);
```

As you create a new instance of your provider, you can supply any extra configuration there.
