# Changelog

## [7.1.1](https://github.com/googleapis/php-jwt/compare/v7.1.0...v7.1.1) (2026-09-02)


### Bug Fixes

* Limit the number of segments a JWT can be exploded into ([#639](https://github.com/googleapis/php-jwt/issues/639)) ([93d248c](https://github.com/googleapis/php-jwt/commit/93d248c650aea615d74a5ffa20c91c7a818e9b8d))

## [7.1.0](https://github.com/googleapis/php-jwt/compare/v7.0.5...v7.1.0) (2026-06-11)


### Features

* Add support for PS256 ([#637](https://github.com/googleapis/php-jwt/issues/637)) ([aa0b447](https://github.com/googleapis/php-jwt/commit/aa0b4472c137255545b177a1840891304eac55f1))


### Bug Fixes

* Skip parsing valid unsupported curves ([#638](https://github.com/googleapis/php-jwt/issues/638)) ([8a6c2a7](https://github.com/googleapis/php-jwt/commit/8a6c2a75d7f45e781c93d618939f8a045fe69f53))
* Update homepage URL in composer.json ([#635](https://github.com/googleapis/php-jwt/issues/635)) ([f7ae571](https://github.com/googleapis/php-jwt/commit/f7ae571efe255aed89c8f8d0f2cb7f71c5835664))
* Validate numeric type of iat, nbf and exp claims in encode ([#634](https://github.com/googleapis/php-jwt/issues/634)) ([958e422](https://github.com/googleapis/php-jwt/commit/958e422961e874843a7fcc339e55a0101a66201d))

## [7.0.5](https://github.com/firebase/php-jwt/compare/v7.0.4...v7.0.5) (2026-03-31)


### Bug Fixes

* RSA from JWK sometimes returns empty Instance ([#628](https://github.com/firebase/php-jwt/issues/628)) ([b4c78aa](https://github.com/firebase/php-jwt/commit/b4c78aa731664122198ad36c0033aa29e807397a))

## [7.0.4](https://github.com/firebase/php-jwt/compare/v7.0.3...v7.0.4) (2026-03-27)


### Bug Fixes

* readme examples, add tests for all examples ([#626](https://github.com/firebase/php-jwt/issues/626)) ([510a00c](https://github.com/firebase/php-jwt/commit/510a00c0e6353bc7d68412fab67e57a13954cb46))
* use urlsafeB64Decode everywhere ([#627](https://github.com/firebase/php-jwt/issues/627)) ([b889495](https://github.com/firebase/php-jwt/commit/b889495c83ddc3f3885ca3f0b65b41b1cb37a3b1))

## [7.0.3](https://github.com/firebase/php-jwt/compare/v7.0.2...v7.0.3) (2026-02-18)


### Miscellaneous Chores

* add environment for Release Please job ([#619](https://github.com/firebase/php-jwt/issues/619)) ([300fd02](https://github.com/firebase/php-jwt/commit/300fd02c883f096c9067df652dbd23f62cb5e2a7))

## [7.0.2](https://github.com/firebase/php-jwt/compare/v7.0.1...v7.0.2) (2025-12-16)


### Bug Fixes

* add key length validation for ec keys ([#615](https://github.com/firebase/php-jwt/issues/615)) ([7044f9a](https://github.com/firebase/php-jwt/commit/7044f9ae7e7d175d28cca71714feb236f1c0e252))

## [7.0.0](https://github.com/firebase/php-jwt/compare/v6.11.1...v7.0.0) (2025-12-15)


### ⚠️ ⚠️ ⚠️  Security Fixes ⚠️ ⚠️ ⚠️ 
 * add key size validation ([#613](https://github.com/firebase/php-jwt/issues/613)) ([6b80341](https://github.com/firebase/php-jwt/commit/6b80341bf57838ea2d011487917337901cd71576))
 **NOTE**: This fix will cause keys with a size below the minimally allowed size to break. 

### Features

* add SensitiveParameter attribute to security-critical parameters ([#603](https://github.com/firebase/php-jwt/issues/603)) ([4dbfac0](https://github.com/firebase/php-jwt/commit/4dbfac0260eeb0e9e643063c99998e3219cc539b))
* store timestamp in `ExpiredException` ([#604](https://github.com/firebase/php-jwt/issues/604)) ([f174826](https://github.com/firebase/php-jwt/commit/f1748260d218a856b6a0c23715ac7fae1d7ca95b))


### Bug Fixes

* validate iat and nbf on payload ([#568](https://github.com/firebase/php-jwt/issues/568)) ([953b2c8](https://github.com/firebase/php-jwt/commit/953b2c88bb445b7e3bb82a5141928f13d7343afd))

## [6.11.1](https://github.com/firebase/php-jwt/compare/v6.11.0...v6.11.1) (2025-04-09)


### Bug Fixes

* update error text for consistency ([#528](https://github.com/firebase/php-jwt/issues/528)) ([c11113a](https://github.com/firebase/php-jwt/commit/c11113afa13265e016a669e75494b9203b8a7775))

## [6.11.0](https://github.com/firebase/php-jwt/compare/v6.10.2...v6.11.0) (2025-01-23)


### Features

* support octet typed JWK ([#587](https://github.com/firebase/php-jwt/issues/587)) ([7cb8a26](https://github.com/firebase/php-jwt/commit/7cb8a265fa81edf2fa6ef8098f5bc5ae573c33ad))


### Bug Fixes

* refactor constructor Key to use PHP 8.0 syntax ([#577](https://github.com/firebase/php-jwt/issues/577)) ([29fa2ce](https://github.com/firebase/php-jwt/commit/29fa2ce9e0582cd397711eec1e80c05ce20fabca))

## [6.10.2](https://github.com/firebase/php-jwt/compare/v6.10.1...v6.10.2) (2024-11-24)


### Bug Fixes

* Mitigate PHP8.4 deprecation warnings ([#570](https://github.com/firebase/php-jwt/issues/570)) ([76808fa](https://github.com/firebase/php-jwt/commit/76808fa227f3811aa5cdb3bf81233714b799a5b5))
* support php 8.4 ([#583](https://github.com/firebase/php-jwt/issues/583)) ([e3d68b0](https://github.com/firebase/php-jwt/commit/e3d68b044421339443c74199edd020e03fb1887e))

## [6.10.1](https://github.com/firebase/php-jwt/compare/v6.10.0...v6.10.1) (2024-05-18)


### Bug Fixes

* ensure ratelimit expiry is set every time ([#556](https://github.com/firebase/php-jwt/issues/556)) ([09cb208](https://github.com/firebase/php-jwt/commit/09cb2081c2c3bc0f61e2f2a5fbea5741f7498648))
* ratelimit cache expiration ([#550](https://github.com/firebase/php-jwt/issues/550)) ([dda7250](https://github.com/firebase/php-jwt/commit/dda725033585ece30ff8cae8937320d7e9f18bae))

## [6.10.0](https://github.com/firebase/php-jwt/compare/v6.9.0...v6.10.0) (2023-11-28)


### Features

* allow typ header override ([#546](https://github.com/firebase/php-jwt/issues/546)) ([79cb30b](https://github.com/firebase/php-jwt/commit/79cb30b729a22931b2fbd6b53f20629a83031ba9))

## [6.9.0](https://github.com/firebase/php-jwt/compare/v6.8.1...v6.9.0) (2023-10-04)


### Features

* add payload to jwt exception ([#521](https://github.com/firebase/php-jwt/issues/521)) ([175edf9](https://github.com/firebase/php-jwt/commit/175edf958bb61922ec135b2333acf5622f2238a2))

## [6.8.1](https://github.com/firebase/php-jwt/compare/v6.8.0...v6.8.1) (2023-07-14)


### Bug Fixes

* accept float claims but round down to ignore them ([#492](https://github.com/firebase/php-jwt/issues/492)) ([3936842](https://github.com/firebase/php-jwt/commit/39368423beeaacb3002afa7dcb75baebf204fe7e))
* different BeforeValidException messages for nbf and iat ([#526](https://github.com/firebase/php-jwt/issues/526)) ([0a53cf2](https://github.com/firebase/php-jwt/commit/0a53cf2986e45c2bcbf1a269f313ebf56a154ee4))

## [6.8.0](https://github.com/firebase/php-jwt/compare/v6.7.0...v6.8.0) (2023-06-14)


### Features

* add support for P-384 curve ([#515](https://github.com/firebase/php-jwt/issues/515)) ([5de4323](https://github.com/firebase/php-jwt/commit/5de4323f4baf4d70bca8663bd87682a69c656c3d))


### Bug Fixes

* handle invalid http responses ([#508](https://github.com/firebase/php-jwt/issues/508)) ([91c39c7](https://github.com/firebase/php-jwt/commit/91c39c72b22fc3e1191e574089552c1f2041c718))

## [6.7.0](https://github.com/firebase/php-jwt/compare/v6.6.0...v6.7.0) (2023-06-14)


### Features

* add ed25519 support to JWK (public keys) ([#452](https://github.com/firebase/php-jwt/issues/452)) ([e53979a](https://github.com/firebase/php-jwt/commit/e53979abae927de916a75b9d239cfda8ce32be2a))

## [6.6.0](https://github.com/firebase/php-jwt/compare/v6.5.0...v6.6.0) (2023-06-13)


### Features

* allow get headers when decoding token ([#442](https://github.com/firebase/php-jwt/issues/442)) ([fb85f47](https://github.com/firebase/php-jwt/commit/fb85f47cfaeffdd94faf8defdf07164abcdad6c3))


### Bug Fixes

* only check iat if nbf is not used ([#493](https://github.com/firebase/php-jwt/issues/493)) ([398ccd2](https://github.com/firebase/php-jwt/commit/398ccd25ea12fa84b9e4f1085d5ff448c21ec797))

## [6.5.0](https://github.com/firebase/php-jwt/compare/v6.4.0...v6.5.0) (2023-05-12)


### Bug Fixes

* allow KID of '0' ([#505](https://github.com/firebase/php-jwt/issues/505)) ([9dc46a9](https://github.com/firebase/php-jwt/commit/9dc46a9c3e5801294249cfd2554c5363c9f9326a))


### Miscellaneous Chores

* drop support for PHP 7.3 ([#495](https://github.com/firebase/php-jwt/issues/495))

## [6.4.0](https://github.com/firebase/php-jwt/compare/v6.3.2...v6.4.0) (2023-02-08)


### Features

* add support for W3C ES256K ([#462](https://github.com/firebase/php-jwt/issues/462)) ([213924f](https://github.com/firebase/php-jwt/commit/213924f51936291fbbca99158b11bd4ae56c2c95))
* improve caching by only decoding jwks when necessary ([#486](https://github.com/firebase/php-jwt/issues/486)) ([78d3ed1](https://github.com/firebase/php-jwt/commit/78d3ed1073553f7d0bbffa6c2010009a0d483d5c))

## [6.3.2](https://github.com/firebase/php-jwt/compare/v6.3.1...v6.3.2) (2022-11-01)


### Bug Fixes

* check kid before using as array index ([bad1b04](https://github.com/firebase/php-jwt/commit/bad1b040d0c736bbf86814c6b5ae614f517cf7bd))

## [6.3.1](https://github.com/firebase/php-jwt/compare/v6.3.0...v6.3.1) (2022-11-01)


### Bug Fixes

* casing of GET for PSR compat ([#451](https://github.com/firebase/php-jwt/issues/451)) ([60b52b7](https://github.com/firebase/php-jwt/commit/60b52b71978790eafcf3b95cfbd83db0439e8d22))
* string interpolation format for php 8.2 ([#446](https://github.com/firebase/php-jwt/issues/446)) ([2e07d8a](https://github.com/firebase/php-jwt/commit/2e07d8a1524d12b69b110ad649f17461d068b8f2))

## 6.3.0 / 2022-07-15

 - Added ES256 support to JWK parsing ([#399](https://github.com/firebase/php-jwt/pull/399))
 - Fixed potential caching error in `CachedKeySet` by caching jwks as strings ([#435](https://github.com/firebase/php-jwt/pull/435))

## 6.2.0 / 2022-05-14

 - Added `CachedKeySet` ([#397](https://github.com/firebase/php-jwt/pull/397))
 - Added `$defaultAlg` parameter to `JWT::parseKey` and `JWT::parseKeySet` ([#426](https://github.com/firebase/php-jwt/pull/426)).

## 6.1.0 / 2022-03-23

 - Drop support for PHP 5.3, 5.4, 5.5, 5.6, and 7.0
 - Add parameter typing and return types where possible

## 6.0.0 / 2022-01-24

 - **Backwards-Compatibility Breaking Changes**: See the [Release Notes](https://github.com/firebase/php-jwt/releases/tag/v6.0.0) for more information.
 - New Key object to prevent key/algorithm type confusion (#365)
 - Add JWK support (#273)
 - Add ES256 support (#256)
 - Add ES384 support (#324)
 - Add Ed25519 support (#343)

## 5.0.0 / 2017-06-26
- Support RS384 and RS512.
  See [#117](https://github.com/firebase/php-jwt/pull/117). Thanks [@joostfaassen](https://github.com/joostfaassen)!
- Add an example for RS256 openssl.
  See [#125](https://github.com/firebase/php-jwt/pull/125). Thanks [@akeeman](https://github.com/akeeman)!
- Detect invalid Base64 encoding in signature.
  See [#162](https://github.com/firebase/php-jwt/pull/162). Thanks [@psignoret](https://github.com/psignoret)!
- Update `JWT::verify` to handle OpenSSL errors.
  See [#159](https://github.com/firebase/php-jwt/pull/159). Thanks [@bshaffer](https://github.com/bshaffer)!
- Add `array` type hinting to `decode` method
  See [#101](https://github.com/firebase/php-jwt/pull/101). Thanks [@hywak](https://github.com/hywak)!
- Add all JSON error types.
  See [#110](https://github.com/firebase/php-jwt/pull/110). Thanks [@gbalduzzi](https://github.com/gbalduzzi)!
- Bugfix 'kid' not in given key list.
  See [#129](https://github.com/firebase/php-jwt/pull/129). Thanks [@stampycode](https://github.com/stampycode)!
- Miscellaneous cleanup, documentation and test fixes.
  See [#107](https://github.com/firebase/php-jwt/pull/107), [#115](https://github.com/firebase/php-jwt/pull/115),
  [#160](https://github.com/firebase/php-jwt/pull/160), [#161](https://github.com/firebase/php-jwt/pull/161), and
  [#165](https://github.com/firebase/php-jwt/pull/165). Thanks [@akeeman](https://github.com/akeeman),
  [@chinedufn](https://github.com/chinedufn), and [@bshaffer](https://github.com/bshaffer)!

## 4.0.0 / 2016-07-17
- Add support for late static binding. See [#88](https://github.com/firebase/php-jwt/pull/88) for details. Thanks to [@chappy84](https://github.com/chappy84)!
- Use static `$timestamp` instead of `time()` to improve unit testing. See [#93](https://github.com/firebase/php-jwt/pull/93) for details. Thanks to [@josephmcdermott](https://github.com/josephmcdermott)!
- Fixes to exceptions classes. See [#81](https://github.com/firebase/php-jwt/pull/81) for details. Thanks to [@Maks3w](https://github.com/Maks3w)!
- Fixes to PHPDoc. See [#76](https://github.com/firebase/php-jwt/pull/76) for details. Thanks to [@akeeman](https://github.com/akeeman)!

## 3.0.0 / 2015-07-22
- Minimum PHP version updated from `5.2.0` to `5.3.0`.
- Add `\Firebase\JWT` namespace. See
[#59](https://github.com/firebase/php-jwt/pull/59) for details. Thanks to
[@Dashron](https://github.com/Dashron)!
- Require a non-empty key to decode and verify a JWT. See
[#60](https://github.com/firebase/php-jwt/pull/60) for details. Thanks to
[@sjones608](https://github.com/sjones608)!
- Cleaner documentation blocks in the code. See
[#62](https://github.com/firebase/php-jwt/pull/62) for details. Thanks to
[@johanderuijter](https://github.com/johanderuijter)!

## 2.2.0 / 2015-06-22
- Add support for adding custom, optional JWT headers to `JWT::encode()`. See
[#53](https://github.com/firebase/php-jwt/pull/53/files) for details. Thanks to
[@mcocaro](https://github.com/mcocaro)!

## 2.1.0 / 2015-05-20
- Add support for adding a leeway to `JWT:decode()` that accounts for clock skew
between signing and verifying entities. Thanks to [@lcabral](https://github.com/lcabral)!
- Add support for passing an object implementing the `ArrayAccess` interface for
`$keys` argument in `JWT::decode()`. Thanks to [@aztech-dev](https://github.com/aztech-dev)!

## 2.0.0 / 2015-04-01
- **Note**: It is strongly recommended that you update to > v2.0.0 to address
  known security vulnerabilities in prior versions when both symmetric and
  asymmetric keys are used together.
- Update signature for `JWT::decode(...)` to require an array of supported
  algorithms to use when verifying token signatures.
