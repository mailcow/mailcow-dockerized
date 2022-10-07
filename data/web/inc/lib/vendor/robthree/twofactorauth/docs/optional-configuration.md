---
layout: post
title: Optional Configuration
---

## Instance Configuration

The instance (`new TwoFactorAuth()`) can only be configured by the constructor with the following optional arguments

Argument          | Default value | Use
------------------|---------------|-----
`$issuer`         | `null`        | Will be displayed in the users app as the default issuer name when using QR code to import the secret
`$digits`         | `6`           | The number of digits the resulting codes will be
`$period`         | `30`          | The number of seconds a code will be valid
`$algorithm`      | `'sha1'`      | The algorithm used (one of `sha1`, `sha256`, `sha512`, `md5`)
`$qrcodeprovider` | `null`        | QR-code provider
`$rngprovider`    | `null`        | Random Number Generator provider
`$timeprovider`   | `null`        | Time provider

**Note:** the default values for `$digits`, `$period`, and `$algorithm` provide the widest variety of support amongst common authenticator apps such as Google Authenticator. If you choose to use different values for these arguments you will likely have to instruct your users to use a specific app which supports your chosen configuration.

### RNG providers

This library also comes with some [Random Number Generator (RNG)](https://en.wikipedia.org/wiki/Random_number_generation) providers. The RNG provider generates a number of random bytes and returns these bytes as a string. These values are then used to create the secret. By default (no RNG provider specified) TwoFactorAuth will try to determine the best available RNG provider to use in this order.

1. [CSRNGProvider](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Rng/CSRNGProvider.php) for PHP7+
2. [MCryptRNGProvider](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Rng/MCryptRNGProvider.php) where mcrypt is available
3. [OpenSSLRNGProvider](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Rng/OpenSSLRNGProvider.php) where openssl is available
4. [HashRNGProvider](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Rng/HashRNGProvider.php) **non-cryptographically secure** fallback

Each of these RNG providers have some constructor arguments that allow you to tweak some of the settings to use when creating the random bytes.

You can also implement your own by implementing the [`IRNGProvider` interface](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Rng/IRNGProvider.php).

### Time providers

These allow the TwoFactorAuth library to ensure the servers time is correct (or at least within a margin).

You can use the `ensureCorrectTime()` method to ensure the hosts time is correct. By default this method will compare the hosts time (returned by calling `time()` on the `LocalMachineTimeProvider`) to the default `NTPTimeProvider` and `HttpTimeProvider`.

**Note:** the `NTPTimeProvider` requires your PHP to have the ability to create sockets. If you do not have that ability and wish to use this function, you should pass an array with only an instance of `HttpTimeProvider`.

Alternatively, you can pass an array of classes that implement the [`ITimeProvider` interface](https://github.com/RobThree/TwoFactorAuth/blob/master/lib/Providers/Time/ITimeProvider.php) to change this and specify the second argument, leniency in seconds (default: 5). An exception will be thrown if the time difference is greater than the leniency.

Ordinarily, you should not need to monitor that the time on the server is correct in this way however if you choose to, we advise to call this method sparingly when relying on 3rd parties (which both the `HttpTimeProvider` and `NTPTimeProvider` do) or, if you need to ensure time is correct on a (very) regular basis to implement an `ITimeProvider` that is more efficient than the built-in ones (making use of a GPS signal for example).

## Secret Configuration

Secrets can be optionally configured with the following optional arguments

Argument               | Default value | Use
-----------------------|---------------|-----
`$bits`                | `80`          | The number of bits (related to the length of the secret)
`$requirecryptosecure` | `true`        | Whether you want to require a cryptographically secure source of random numbers

**Note:** as above, these values provide the widest variety of support amongst common authenticator apps however you may choose to increase the value of `$bits` (160 or higher is recommended, see [RFC 4226 - Algorithm Requirements](https://tools.ietf.org/html/rfc4226#section-4)) as long as it is set to a multiple of 8.
