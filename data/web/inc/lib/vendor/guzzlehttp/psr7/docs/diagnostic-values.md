# Diagnostic Values

## `GuzzleHttp\Psr7\DiagnosticValue::escape`

`public static function escape(string $value): string`

Escapes C0, DEL, and C1 controls as uppercase `\xNN` sequences.

ASCII bytes from 0x20 through 0x7E and valid UTF-8 characters outside those
control ranges remain unchanged. If the input is malformed UTF-8 or PCRE cannot
process it, every byte outside printable ASCII is escaped. Valid C1 characters
are rendered as `\xNN` using their Unicode code points. During bytewise
fallback, each original byte outside printable ASCII is rendered in the same
form. The result is diagnostic text, not a reversible encoding.

This does not encode values for HTML, JSON, shells, terminals, URLs, or protocol
fields.

Use this helper when including an untrusted value in diagnostic text:

```php
use GuzzleHttp\Psr7\DiagnosticValue;

$value = "bad\nvalue";
$message = sprintf('Invalid value: %s', DiagnosticValue::escape($value));
// Invalid value: bad\x0Avalue
```

Applications must still encode the completed diagnostic for its final output
context, such as HTML or JSON.
