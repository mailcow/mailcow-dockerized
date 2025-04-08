---
layout: post
title: Improved Code Verification
---

When verifying codes that a user has entered, there are other optional arguments which can improve verification of the code.

```php
$result = $tfa->verifyCode($secret, $_POST['verification'], $discrepancy, $time, &$timeslice);
```

## Discrepancy (default 1)

As the codes that are generated and accepted are consistent within a certain time window (i.e. a timeslice, 30 seconds long by default), it is very important that the server (and the users authenticator app) have the correct time (and date).

The value of `$discrepancy` is the number of timeslices checked in **both** directions of the current one. So when the current time is `14:34:21`, the 'current timeslice' is `14:34:00` to `14:34:30`. If the default is left unchanged, we also verify the code against the timeslice of `14:33:30` to `14:34:00` and for `14:34:30` to `14:35:00`.

This should be sufficient for most cases however you can increase it if you wish. It would be unwise for this to be too high as it could allow a code to be valid for long enough that it could be used fraudulently.

## Time (default null)

The second, `$time`, allows you to check a code for a specific point in time. This argument has no real practical use but can be handy for unit testing. The default value, `null`, means: use the current time.

## Timeslice

`$timeslice` returns a value by reference. The value returned is the timeslice that matched the code (if any) or `0`.

You can store a timeslice alongside the secret and verify that any new timeslice is greater than the existing one.

i.e. if `verifyCode` returns true _and_ the returned timeslice is greater than the last used timeslice for this user/secret then this is the first time the code has been used and you should now store the higher timeslice to verify that the user.

This is an effective defense against a [replay attack](https://en.wikipedia.org/wiki/Replay_attack).
