---
layout: post
title: Getting Started
---

## 1. Installation

The best way of making use of this project is by installing it with [composer](https://getcomposer.org/doc/01-basic-usage.md).

```
php composer.phar require robthree/twofactorauth
```

or if you have composer installed globally

```
composer require robthree/twofactorauth
```

## 2. Create an instance

Now you can create an instance for use with your code

```php
use RobThree\Auth\TwoFactorAuth;

$tfa = new TwoFactorAuth();
```

**Note:** if you are not using a framework that uses composer, you should [include the composer loader yourself](https://getcomposer.org/doc/01-basic-usage.md#autoloading)

## 3. Shared secrets

When your user is setting up two-factor, or multi-factor, authentication in your project, you can create a secret from the instance.

```php
$secret = $tfa->createSecret();
```

Once you have a secret, it can be communicated to the user however you wish.

```php
<p>Please enter the following code in your app: '<?php echo $secret; ?>'</p>
```

**Note:** until you have verified the user is able to use the secret properly, you should store the secret as part of the current session and not save the secret against your user record.

## 4. Verifying

Having provided the user with the secret, the best practice is to verify their authenticator app can create the appropriate code.

```php
$result = $tfa->verifyCode($secret, $_POST['verification']);
```

If `$result` is `true` then your user has been able to successfully record the `$secret` in their authenticator app and it has generated an appropriate code.

You can now save the `$secret` to your user record and use the same `verifyCode` method each time they log in.
