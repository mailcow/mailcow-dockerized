---
layout: post
title: QR Server
---

## Optional Configuration

Argument                | Default value
------------------------|---------------
`$verifyssl`            | `false`
`$errorcorrectionlevel` | `'L'`
`$margin`               | `4`
`$qzone`                | `1`
`$bgcolor`              | `'ffffff'`
`$color`                | `'000000'`
`$format`               | `'png'`

`$verifyssl` is used internally to help guarantee the security of the connection. It is possible that where you are running the code from will have problems verifying an SSL connection so if you know this is not the case, you can supply `true`.

The other parameters are passed to [goqr.me](http://goqr.me/api/doc/create-qr-code/) so you can refer to them for more detail on how the values are used.
