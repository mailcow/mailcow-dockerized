# CHANGELOG

## 1.5.2 - 2022-08-07

### Changed

- Officially support PHP 8.2

## 1.5.1 - 2021-10-22

### Fixed

- Revert "Call handler when waiting on fulfilled/rejected Promise"
- Fix pool memory leak when empty array of promises provided

## 1.5.0 - 2021-10-07

### Changed

- Call handler when waiting on fulfilled/rejected Promise
- Officially support PHP 8.1

### Fixed

- Fix manually settle promises generated with `Utils::task`

## 1.4.1 - 2021-02-18

### Fixed

- Fixed `each_limit` skipping promises and failing

## 1.4.0 - 2020-09-30

### Added

- Support for PHP 8
- Optional `$recursive` flag to `all`
- Replaced functions by static methods

### Fixed

- Fix empty `each` processing
- Fix promise handling for Iterators of non-unique keys
- Fixed `method_exists` crashes on PHP 8
- Memory leak on exceptions


## 1.3.1 - 2016-12-20

### Fixed

- `wait()` foreign promise compatibility


## 1.3.0 - 2016-11-18

### Added

- Adds support for custom task queues.

### Fixed

- Fixed coroutine promise memory leak.


## 1.2.0 - 2016-05-18

### Changed

- Update to now catch `\Throwable` on PHP 7+


## 1.1.0 - 2016-03-07

### Changed

- Update EachPromise to prevent recurring on a iterator when advancing, as this
  could trigger fatal generator errors.
- Update Promise to allow recursive waiting without unwrapping exceptions.


## 1.0.3 - 2015-10-15

### Changed

- Update EachPromise to immediately resolve when the underlying promise iterator
  is empty. Previously, such a promise would throw an exception when its `wait`
  function was called.


## 1.0.2 - 2015-05-15

### Changed

- Conditionally require functions.php.


## 1.0.1 - 2015-06-24

### Changed

- Updating EachPromise to call next on the underlying promise iterator as late
  as possible to ensure that generators that generate new requests based on
  callbacks are not iterated until after callbacks are invoked.


## 1.0.0 - 2015-05-12

- Initial release
