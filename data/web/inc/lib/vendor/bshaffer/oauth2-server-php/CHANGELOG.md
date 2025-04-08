CHANGELOG for 1.x
=================

This changelog references the relevant changes (bug and security fixes) done
in 1.x minor versions.

To see the files changed for a given bug, go to https://github.com/bshaffer/oauth2-server-php/issues/### where ### is the bug number
To get the diff between two versions, go to https://github.com/bshaffer/oauth2-server-php/compare/v1.0...v1.1
To get the diff for a specific change, go to https://github.com/bshaffer/oauth2-server-php/commit/XXX where XXX is the change hash

* 1.10.0 (2017-11-15)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/889

  * #795 - [feature] added protected createPayload method to allow easier customization of JWT payload
  * #807 - [refactor] simplifies UserInfoController constructor
  * #814 - [docs] Adds https to README link
  * #827 - [testing] Explicitly pulls in phpunit 4
  * #828 - [docs] PHPDoc improvements and type hinting of variables.
  * #829 - [bug] Fix CORS issue for revoking and requesting an access token
  * #869 - [testing] Remove php 5.3 from travis and use vendored phpunit
  * #834 - [feature] use random_bytes if available
  * #851 - [docs] Fix PHPDoc
  * #872 - [bug] Fix count() error on PHP 7.2
  * #873 - [testing] adds php 7.2 to travis
  * #794 - [docs] Fix typo in composer.json
  * #885 - [testing] Use PHPUnit\Framework\TestCase instead of PHPUnit_Framework_TestCase

* 1.9.0 (2017-01-06)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/788

  * bug #645 - Allow null for client_secret
  * bug #651 - Fix bug in isPublicClient of Cassandra Storage
  * bug #670 - Bug in client's scope restriction
  * bug #672 - Implemented method to override the password hashing algorithm
  * bug #698 - Fix Token Response's Content-Type to application/json
  * bug #729 - Ensures unsetAccessToken and unsetRefreshToken return a bool
  * bug #749 - Fix UserClaims for CodeIdToken
  * bug #784 - RFC6750 compatibility
  * bug #776 - Fix "redirect_uri_mismatch" for URIs with encoded characters
  * bug #759 - no access token supplied to resource controller results in empty request body
  * bug #773 - Use OpenSSL random method before attempting Mcrypt's.
  * bug #790 - Add mongo db

* 1.8.0 (2015-09-18)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/643

  * bug #594 - adds jti
  * bug #598 - fixes lifetime configurations for JWTs
  * bug #634 - fixes travis builds, upgrade to containers
  * bug #586 - support for revoking tokens
  * bug #636 - Adds FirebaseJWT bridge
  * bug #639 - Mongo HHVM compatibility

* 1.7.0 (2015-04-23)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/572

  * bug #500 - PDO fetch mode changed from FETCH_BOTH to FETCH_ASSOC
  * bug #508 - Case insensitive for Bearer token header name  ba716d4
  * bug #512 - validateRedirectUri is now public
  * bug #530 - Add PublicKeyInterface, UserClaimsInterface to Cassandra Storage
  * bug #505 - DynamoDB storage fixes
  * bug #556 - adds "code id_token" return type to openid connect
  * bug #563 - Include "issuer" config key for JwtAccessToken
  * bug #564 - Fixes JWT vulnerability
  * bug #571 - Added unset_refresh_token_after_use option

* 1.6 (2015-01-16)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/496

  * bug 437 - renames CryptoToken to JwtAccessToken / use_crypto_tokens to use_jwt_access_tokens
  * bug 447 - Adds a Couchbase storage implementation
  * bug 460 - Rename JWT claims to match spec
  * bug 470 - order does not matter for multi-valued response types
  * bug 471 - Make validateAuthorizeRequest available for POST in addition to GET
  * bug 475 - Adds JTI table definitiion
  * bug 481 - better randomness for generating access tokens
  * bug 480 - Use hash_equals() for signature verification (prevents remote timing attacks)
  * bugs 489, 491, 498 - misc other fixes

* 1.5 (2014-08-27)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/446

  * bug #399 - Add DynamoDB Support
  * bug #404 - renamed error name for malformed/expired tokens
  * bug #412 - Openid connect: fixes for claims with more than one scope / Add support for the prompt parameter ('consent' and 'none')
  * bug #411 - fixes xml output
  * bug #413 - fixes invalid format error
  * bug #401 - fixes code standards / whitespace
  * bug #354 - bundles PDO SQL with the library
  * [BC] bug #397 - refresh tokens should not be encrypted
  * bug #423 - makes "scope" optional for refresh token storage

* 1.4 (2014-06-12)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/392

  * bug #189 Storage\PDO - allows DSN string in constructor
  * bug #233 Bearer Tokens - allows token in request body for PUT requests
  * bug #346 Fixes open_basedir warning
  * bug #351 Adds OpenID Connect support
  * bug #355 Adds php 5.6 and HHVM to travis.ci testing
  * [BC] bug #358 Adds `getQueryStringIdentifier()` to the GrantType interface
  * bug #363 Encryption\JWT - Allows for subclassing JWT Headers
  * bug #349 Bearer Tokens - adds requestHasToken method for when access tokens are optional
  * bug #301 Encryption\JWT - fixes urlSafeB64Encode(): ensures newlines are replaced as expected
  * bug #323 ResourceController - client_id is no longer required to be returned when calling getAccessToken
  * bug #367 Storage\PDO - adds Postgres support
  * bug #368 Access Tokens - use mcrypt_create_iv or openssl_random_pseudo_bytes to create token string
  * bug #376 Request - allows case insensitive headers
  * bug #384 Storage\PDO - can pass in PDO options in constructor of PDO storage
  * misc fixes #361, #292, #373, #374, #379, #396
* 1.3 (2014-02-27)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/325

  * bug #311 adds cassandra storage
  * bug #298 fixes response code for user credentials grant type
  * bug #318 adds 'use_crypto_tokens' config to Server class for better DX
  * [BC] bug #320 pass client_id to getDefaultScope
  * bug #324 better feedback when running tests
  * bug #335 adds support for non-expiring refresh tokens
  * bug #333 fixes Pdo storage for getClientKey
  * bug #336 fixes Redis storage for expireAuthorizationCode

* 1.3 (2014-02-27)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/325

  * bug #311 adds cassandra storage
  * bug #298 fixes response code for user credentials grant type
  * bug #318 adds 'use_crypto_tokens' config to Server class for better DX
  * bug #320 pass client_id to getDefaultScope
  * bug #324 better feedback when running tests
  * bug #335 adds support for non-expiring refresh tokens
  * bug #333 fixes Pdo storage for getClientKey
  * bug #336 fixes Redis storage for expireAuthorizationCode

* 1.2 (2014-01-03)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/288

  * bug #285 changed response header from 200 to 401 when empty token received
  * bug #286 adds documentation and links to spec for not including error messages when no token is supplied
  * bug #280 ensures PHP warnings do not get thrown as a result of an invalid argument to $jwt->decode()
  * bug #279 predis wrong number of arguments
  * bug #277 Securing JS WebApp client secret w/ password grant type

* 1.1 (2013-12-17)

  PR: https://github.com/bshaffer/oauth2-server-php/pull/276

  * bug #278 adds refresh token configuration to Server class
  * bug #274 Supplying a null client_id and client_secret grants API access
  * bug #244 [MongoStorage] More detailed implementation info
  * bug #268 Implement jti for JWT Bearer tokens to prevent replay attacks.
  * bug #266 Removing unused argument to getAccessTokenData
  * bug #247 Make Bearer token type consistent
  * bug #253 Fixing CryptoToken refresh token lifetime
  * bug #246 refactors public key logic to be more intuitive
  * bug #245 adds support for JSON crypto tokens
  * bug #230 Remove unused columns in oauth_clients
  * bug #215 makes Redis Scope Storage obey the same paradigm as PDO
  * bug #228 removes scope group
  * bug #227 squelches open basedir restriction error
  * bug #223 Updated docblocks for RefreshTokenInterface.php
  * bug #224 Adds protected properties
  * bug #217 Implement ScopeInterface for PDO, Redis

* 1.0 (2013-08-12)

  * bug #203 Add redirect\_status_code config param for AuthorizeController
  * bug #205 ensures unnecessary ? is not set when  ** bug
  * bug #204 Fixed call to LogicException
  * bug #202 Add explode to checkRestrictedGrant in PDO Storage
  * bug #197 adds support for 'false' default scope  ** bug
  * bug #192 reference errors and adds tests
  * bug #194 makes some appropriate properties  ** bug
  * bug #191 passes config to HttpBasic
  * bug #190 validates client credentials before  ** bug
  * bug #171 Fix wrong redirect following authorization step
  * bug #187 client_id is now passed to getDefaultScope().
  * bug #176 Require refresh_token in getRefreshToken response
  * bug #174 make user\_id not required for refresh_token grant
  * bug #173 Duplication in JwtBearer Grant
  * bug #168 user\_id not required for authorization_code grant
  * bug #133 hardens default security for user object
  * bug #163 allows redirect\_uri on authorization_code to be NULL in docs example
  * bug #162 adds getToken on ResourceController for convenience
  * bug #161 fixes fatal error
  * bug #163 Invalid redirect_uri handling
  * bug #156 user\_id in OAuth2\_Storage_AuthorizationCodeInterface::getAuthorizationCode() response
  * bug #157 Fix for extending access and refresh tokens
  * bug #154 ResponseInterface: getParameter method is used in the library but not defined in the interface
  * bug #148 Add more detail to examples in Readme.md
