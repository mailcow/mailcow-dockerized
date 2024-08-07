# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

## 2.4.4 - 2023-03-09

### Changed

- Removed the need for `AllowDynamicProperties` in `LazyOpenStream`

## 2.4.3 - 2022-10-26

### Changed

- Replaced `sha1(uniqid())` by `bin2hex(random_bytes(20))`

## 2.4.2 - 2022-10-25

### Fixed

- Fixed erroneous behaviour when combining host and relative path

## 2.4.1 - 2022-08-28

### Fixed

- Rewind body before reading in `Message::bodySummary`

## 2.4.0 - 2022-06-20

### Added

- Added provisional PHP 8.2 support
- Added `UriComparator::isCrossOrigin` method

## 2.3.0 - 2022-06-09

### Fixed

- Added `Header::splitList` method
- Added `Utils::tryGetContents` method
- Improved `Stream::getContents` method
- Updated mimetype mappings

## 2.2.2 - 2022-06-08

### Fixed

- Fix `Message::parseRequestUri` for numeric headers
- Re-wrap exceptions thrown in `fread` into runtime exceptions
- Throw an exception when multipart options is misformatted

## 2.2.1 - 2022-03-20

### Fixed

- Correct header value validation

## 2.2.0 - 2022-03-20

### Added

- A more compressive list of mime types
- Add JsonSerializable to Uri
- Missing return types

### Fixed

- Bug MultipartStream no `uri` metadata
- Bug MultipartStream with filename for `data://` streams
- Fixed new line handling in MultipartStream
- Reduced RAM usage when copying streams
- Updated parsing in `Header::normalize()`

## 2.1.1 - 2022-03-20

### Fixed

- Validate header values properly

## 2.1.0 - 2021-10-06

### Changed

- Attempting to create a `Uri` object from a malformed URI will no longer throw a generic
  `InvalidArgumentException`, but rather a `MalformedUriException`, which inherits from the former
  for backwards compatibility. Callers relying on the exception being thrown to detect invalid
  URIs should catch the new exception.

### Fixed

- Return `null` in caching stream size if remote size is `null`

## 2.0.0 - 2021-06-30

Identical to the RC release.

## 2.0.0@RC-1 - 2021-04-29

### Fixed

- Handle possibly unset `url` in `stream_get_meta_data`

## 2.0.0@beta-1 - 2021-03-21

### Added

- PSR-17 factories
- Made classes final
- PHP7 type hints

### Changed

- When building a query string, booleans are represented as 1 and 0.

### Removed

- PHP < 7.2 support
- All functions in the `GuzzleHttp\Psr7` namespace

## 1.8.1 - 2021-03-21

### Fixed

- Issue parsing IPv6 URLs
- Issue modifying ServerRequest lost all its attributes

## 1.8.0 - 2021-03-21

### Added

- Locale independent URL parsing
- Most classes got a `@final` annotation to prepare for 2.0

### Fixed

- Issue when creating stream from `php://input` and curl-ext is not installed
- Broken `Utils::tryFopen()` on PHP 8

## 1.7.0 - 2020-09-30

### Added

- Replaced functions by static methods

### Fixed

- Converting a non-seekable stream to a string
- Handle multiple Set-Cookie correctly
- Ignore array keys in header values when merging
- Allow multibyte characters to be parsed in `Message:bodySummary()`

### Changed

- Restored partial HHVM 3 support


## [1.6.1] - 2019-07-02

### Fixed

- Accept null and bool header values again


## [1.6.0] - 2019-06-30

### Added

- Allowed version `^3.0` of `ralouphie/getallheaders` dependency (#244)
- Added MIME type for WEBP image format (#246)
- Added more validation of values according to PSR-7 and RFC standards, e.g. status code range (#250, #272)

### Changed

- Tests don't pass with HHVM 4.0, so HHVM support got dropped. Other libraries like composer have done the same. (#262)
- Accept port number 0 to be valid (#270)

### Fixed

- Fixed subsequent reads from `php://input` in ServerRequest (#247)
- Fixed readable/writable detection for certain stream modes (#248)
- Fixed encoding of special characters in the `userInfo` component of an URI (#253)


## [1.5.2] - 2018-12-04

### Fixed

- Check body size when getting the message summary


## [1.5.1] - 2018-12-04

### Fixed

- Get the summary of a body only if it is readable


## [1.5.0] - 2018-12-03

### Added

- Response first-line to response string exception (fixes #145)
- A test for #129 behavior
- `get_message_body_summary` function in order to get the message summary
- `3gp` and `mkv` mime types

### Changed

- Clarify exception message when stream is detached

### Deprecated

- Deprecated parsing folded header lines as per RFC 7230

### Fixed

- Fix `AppendStream::detach` to not close streams
- `InflateStream` preserves `isSeekable` attribute of the underlying stream
- `ServerRequest::getUriFromGlobals` to support URLs in query parameters


Several other fixes and improvements.


## [1.4.2] - 2017-03-20

### Fixed

- Reverted BC break to `Uri::resolve` and `Uri::removeDotSegments` by removing
  calls to `trigger_error` when deprecated methods are invoked.


## [1.4.1] - 2017-02-27

### Added

- Rriggering of silenced deprecation warnings.

### Fixed

- Reverted BC break by reintroducing behavior to automagically fix a URI with a
  relative path and an authority by adding a leading slash to the path. It's only
  deprecated now.


## [1.4.0] - 2017-02-21

### Added

- Added common URI utility methods based on RFC 3986 (see documentation in the readme):
  - `Uri::isDefaultPort`
  - `Uri::isAbsolute`
  - `Uri::isNetworkPathReference`
  - `Uri::isAbsolutePathReference`
  - `Uri::isRelativePathReference`
  - `Uri::isSameDocumentReference`
  - `Uri::composeComponents`
  - `UriNormalizer::normalize`
  - `UriNormalizer::isEquivalent`
  - `UriResolver::relativize`

### Changed

- Ensure `ServerRequest::getUriFromGlobals` returns a URI in absolute form.
- Allow `parse_response` to parse a response without delimiting space and reason.
- Ensure each URI modification results in a valid URI according to PSR-7 discussions.
  Invalid modifications will throw an exception instead of returning a wrong URI or
  doing some magic.
  - `(new Uri)->withPath('foo')->withHost('example.com')` will throw an exception
    because the path of a URI with an authority must start with a slash "/" or be empty
  - `(new Uri())->withScheme('http')` will return `'http://localhost'`

### Deprecated

- `Uri::resolve` in favor of `UriResolver::resolve`
- `Uri::removeDotSegments` in favor of `UriResolver::removeDotSegments`

### Fixed

- `Stream::read` when length parameter <= 0.
- `copy_to_stream` reads bytes in chunks instead of `maxLen` into memory.
- `ServerRequest::getUriFromGlobals` when `Host` header contains port.
- Compatibility of URIs with `file` scheme and empty host.


## [1.3.1] - 2016-06-25

### Fixed

- `Uri::__toString` for network path references, e.g. `//example.org`.
- Missing lowercase normalization for host.
- Handling of URI components in case they are `'0'` in a lot of places,
  e.g. as a user info password.
- `Uri::withAddedHeader` to correctly merge headers with different case.
- Trimming of header values in `Uri::withAddedHeader`. Header values may
  be surrounded by whitespace which should be ignored according to RFC 7230
  Section 3.2.4. This does not apply to header names.
- `Uri::withAddedHeader` with an array of header values.
- `Uri::resolve` when base path has no slash and handling of fragment.
- Handling of encoding in `Uri::with(out)QueryValue` so one can pass the
  key/value both in encoded as well as decoded form to those methods. This is
  consistent with withPath, withQuery etc.
- `ServerRequest::withoutAttribute` when attribute value is null.


## [1.3.0] - 2016-04-13

### Added

- Remaining interfaces needed for full PSR7 compatibility
  (ServerRequestInterface, UploadedFileInterface, etc.).
- Support for stream_for from scalars.

### Changed

- Can now extend Uri.

### Fixed
- A bug in validating request methods by making it more permissive.


## [1.2.3] - 2016-02-18

### Fixed

- Support in `GuzzleHttp\Psr7\CachingStream` for seeking forward on remote
  streams, which can sometimes return fewer bytes than requested with `fread`.
- Handling of gzipped responses with FNAME headers.


## [1.2.2] - 2016-01-22

### Added

- Support for URIs without any authority.
- Support for HTTP 451 'Unavailable For Legal Reasons.'
- Support for using '0' as a filename.
- Support for including non-standard ports in Host headers.


## [1.2.1] - 2015-11-02

### Changes

- Now supporting negative offsets when seeking to SEEK_END.


## [1.2.0] - 2015-08-15

### Changed

- Body as `"0"` is now properly added to a response.
- Now allowing forward seeking in CachingStream.
- Now properly parsing HTTP requests that contain proxy targets in
  `parse_request`.
- functions.php is now conditionally required.
- user-info is no longer dropped when resolving URIs.


## [1.1.0] - 2015-06-24

### Changed

- URIs can now be relative.
- `multipart/form-data` headers are now overridden case-insensitively.
- URI paths no longer encode the following characters because they are allowed
  in URIs: "(", ")", "*", "!", "'"
- A port is no longer added to a URI when the scheme is missing and no port is
  present.


## 1.0.0 - 2015-05-19

Initial release.

Currently unsupported:

- `Psr\Http\Message\ServerRequestInterface`
- `Psr\Http\Message\UploadedFileInterface`



[1.6.0]: https://github.com/guzzle/psr7/compare/1.5.2...1.6.0
[1.5.2]: https://github.com/guzzle/psr7/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/guzzle/psr7/compare/1.5.0...1.5.1
[1.5.0]: https://github.com/guzzle/psr7/compare/1.4.2...1.5.0
[1.4.2]: https://github.com/guzzle/psr7/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/guzzle/psr7/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/guzzle/psr7/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/guzzle/psr7/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/guzzle/psr7/compare/1.2.3...1.3.0
[1.2.3]: https://github.com/guzzle/psr7/compare/1.2.2...1.2.3
[1.2.2]: https://github.com/guzzle/psr7/compare/1.2.1...1.2.2
[1.2.1]: https://github.com/guzzle/psr7/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/guzzle/psr7/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/guzzle/psr7/compare/1.0.0...1.1.0
