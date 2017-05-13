CSRF Protector
==========================
[![Todo Status](http://todofy.org/b/mebjas/CSRF-Protector-PHP)](http://todofy.org/r/mebjas/CSRF-Protector-PHP) [![Build Status](https://travis-ci.org/mebjas/CSRF-Protector-PHP.svg?branch=master)](https://travis-ci.org/mebjas/CSRF-Protector-PHP)  [![codecov](https://codecov.io/gh/mebjas/CSRF-Protector-PHP/branch/master/graph/badge.svg)](https://codecov.io/gh/mebjas/CSRF-Protector-PHP)
<br>CSRF protector php, a standalone php library for csrf mitigation in web applications. Easy to integrate in any php web app. 

Add to your project using packagist
==========
 Add a `composer.json` file to your project directory
 ```json
 {
    "require": {
        "owasp/csrf-protector-php": "dev-master"
    }
}
```
Then open terminal (or command prompt), move to project directory and run
```shell
composer install
```
OR
```
php composer.phar install
```
This will add CSRFP (library will be downloaded at ./vendor/owasp/csrf-protector-php) to your project directory. View [packagist.org](https://packagist.org/) for more help with composer!

Configuration
==========
For composer installations: Copy the config.sample.php file into your root folder at config/csrf_config.php
For non-composer installations: Copy the libs/csrf/config.sample.php file into libs/csrc/config.php
Edit config accordingly. See Detailed Information link below.

How to use
==========
```php
<?php
include_once __DIR__ .'/vendor/owasp/csrf-protector-php/libs/csrf/csrfprotector.php';

//Initialise CSRFGuard library
csrfProtector::init();
```
simply include the library and call the `init()` function!

### Detailed information @[Project wiki on github](https://github.com/mebjas/CSRF-Protector-PHP/wiki)

### More information @[OWASP wiki](https://www.owasp.org/index.php/CSRFProtector_Project)

### Contribute

* Fork the repo
* Create your branch
* Commit your changes
* Create a pull request

### Note
This version (`master`) requires the clients to have Javascript enabled. However if your application can work without javascript & you require a nojs version of this library, check our [nojs version](https://github.com/mebjas/CSRF-Protector-PHP/tree/nojs-support)

## Discussion
Join Discussions on the [mailing list](https://lists.owasp.org/mailman/listinfo/owasp-csrfprotector)

For any other queries contact me at: **minhaz@owasp.org**

### FAQ:
1. What happens if token expires? - https://github.com/mebjas/CSRF-Protector-PHP/wiki/what-if-token-expires
2. Secure flag in cookie? - https://github.com/mebjas/CSRF-Protector-PHP/issues/54
3. NoJS support? - https://github.com/mebjas/CSRF-Protector-PHP/tree/nojs-support
