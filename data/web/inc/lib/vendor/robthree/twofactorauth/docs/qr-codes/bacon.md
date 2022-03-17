---
layout: post
title: bacon/bacon-qr-code
---

## Installation

In order to use this provider, you will need to install the library at version 2 (or later) and its dependencies

```
composer require bacon/bacon-qr-code ^2.0
```

You will also need the PHP imagick extension **if** you aren't using the SVG format.

## Optional Configuration

Argument            | Default value
--------------------|---------------
`$borderWidth`      | `4`
`$backgroundColour` | `'#ffffff'`
`$foregroundColour` | `'#000000'`
`$format`           | `'png'`
