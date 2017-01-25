// Copyright 2014-2015 Google Inc. All rights reserved.
//
// Use of this source code is governed by a BSD-style
// license that can be found in the LICENSE file or at
// https://developers.google.com/open-source/licenses/bsd

/**
 * @fileoverview The U2F api.
 */

'use strict';

/** Namespace for the U2F api.
 * @type {Object}
 */
var u2f = u2f || {};

/**
 * The U2F extension id
 * @type {string}
 * @const
 */
u2f.EXTENSION_ID = 'kmendfapggjehodndflmmgagdbamhnfd';

/**
 * Message types for messsages to/from the extension
 * @const
 * @enum {string}
 */
u2f.MessageTypes = {
    'U2F_REGISTER_REQUEST': 'u2f_register_request',
    'U2F_SIGN_REQUEST': 'u2f_sign_request',
    'U2F_REGISTER_RESPONSE': 'u2f_register_response',
    'U2F_SIGN_RESPONSE': 'u2f_sign_response'
};

/**
 * Response status codes
 * @const
 * @enum {number}
 */
u2f.ErrorCodes = {
    'OK': 0,
    'OTHER_ERROR': 1,
    'BAD_REQUEST': 2,
    'CONFIGURATION_UNSUPPORTED': 3,
    'DEVICE_INELIGIBLE': 4,
    'TIMEOUT': 5
};

/**
 * A message type for registration requests
 * @typedef {{
 *   type: u2f.MessageTypes,
 *   signRequests: Array<u2f.SignRequest>,
 *   registerRequests: ?Array<u2f.RegisterRequest>,
 *   timeoutSeconds: ?number,
 *   requestId: ?number
 * }}
 */
u2f.Request;

/**
 * A message for registration responses
 * @typedef {{
 *   type: u2f.MessageTypes,
 *   responseData: (u2f.Error | u2f.RegisterResponse | u2f.SignResponse),
 *   requestId: ?number
 * }}
 */
u2f.Response;

/**
 * An error object for responses
 * @typedef {{
 *   errorCode: u2f.ErrorCodes,
 *   errorMessage: ?string
 * }}
 */
u2f.Error;

/**
 * Data object for a single sign request.
 * @typedef {{
 *   version: string,
 *   challenge: string,
 *   keyHandle: string,
 *   appId: string
 * }}
 */
u2f.SignRequest;

/**
 * Data object for a sign response.
 * @typedef {{
 *   keyHandle: string,
 *   signatureData: string,
 *   clientData: string
 * }}
 */
u2f.SignResponse;

/**
 * Data object for a registration request.
 * @typedef {{
 *   version: string,
 *   challenge: string,
 *   appId: string
 * }}
 */
u2f.RegisterRequest;

/**
 * Data object for a registration response.
 * @typedef {{
 *   registrationData: string,
 *   clientData: string
 * }}
 */
u2f.RegisterResponse;


// Low level MessagePort API support

/**
 * Sets up a MessagePort to the U2F extension using the
 * available mechanisms.
 * @param {function((MessagePort|u2f.WrappedChromeRuntimePort_))} callback
 */
u2f.getMessagePort = function(callback) {
    if (typeof chrome != 'undefined' && chrome.runtime) {
        // The actual message here does not matter, but we need to get a reply
        // for the callback to run. Thus, send an empty signature request
        // in order to get a failure response.
        var msg = {
            type: u2f.MessageTypes.U2F_SIGN_REQUEST,
            signRequests: []
        };
        chrome.runtime.sendMessage(u2f.EXTENSION_ID, msg, function() {
            if (!chrome.runtime.lastError) {
                // We are on a whitelisted origin and can talk directly
                // with the extension.
                u2f.getChromeRuntimePort_(callback);
            } else {
                // chrome.runtime was available, but we couldn't message
                // the extension directly, use iframe
                u2f.getIframePort_(callback);
            }
        });
    } else if (u2f.isAndroidChrome_()) {
        u2f.getAuthenticatorPort_(callback);
    } else {
        // chrome.runtime was not available at all, which is normal
        // when this origin doesn't have access to any extensions.
        u2f.getIframePort_(callback);
    }
};

/**
 * Detect chrome running on android based on the browser's useragent.
 * @private
 */
u2f.isAndroidChrome_ = function() {
    var userAgent = navigator.userAgent;
    return userAgent.indexOf('Chrome') != -1 &&
        userAgent.indexOf('Android') != -1;
};

/**
 * Connects directly to the extension via chrome.runtime.connect
 * @param {function(u2f.WrappedChromeRuntimePort_)} callback
 * @private
 */
u2f.getChromeRuntimePort_ = function(callback) {
    var port = chrome.runtime.connect(u2f.EXTENSION_ID,
        {'includeTlsChannelId': true});
    setTimeout(function() {
        callback(new u2f.WrappedChromeRuntimePort_(port));
    }, 0);
};

/**
 * Return a 'port' abstraction to the Authenticator app.
 * @param {function(u2f.WrappedAuthenticatorPort_)} callback
 * @private
 */
u2f.getAuthenticatorPort_ = function(callback) {
    setTimeout(function() {
        callback(new u2f.WrappedAuthenticatorPort_());
    }, 0);
};

/**
 * A wrapper for chrome.runtime.Port that is compatible with MessagePort.
 * @param {Port} port
 * @constructor
 * @private
 */
u2f.WrappedChromeRuntimePort_ = function(port) {
    this.port_ = port;
};

/**
 * Format a return a sign request.
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {number} timeoutSeconds
 * @param {number} reqId
 * @return {Object}
 */
u2f.WrappedChromeRuntimePort_.prototype.formatSignRequest_ =
    function(signRequests, timeoutSeconds, reqId) {
        return {
            type: u2f.MessageTypes.U2F_SIGN_REQUEST,
            signRequests: signRequests,
            timeoutSeconds: timeoutSeconds,
            requestId: reqId
        };
    };

/**
 * Format a return a register request.
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {Array<u2f.RegisterRequest>} signRequests
 * @param {number} timeoutSeconds
 * @param {number} reqId
 * @return {Object}
 */
u2f.WrappedChromeRuntimePort_.prototype.formatRegisterRequest_ =
    function(signRequests, registerRequests, timeoutSeconds, reqId) {
        return {
            type: u2f.MessageTypes.U2F_REGISTER_REQUEST,
            signRequests: signRequests,
            registerRequests: registerRequests,
            timeoutSeconds: timeoutSeconds,
            requestId: reqId
        };
    };

/**
 * Posts a message on the underlying channel.
 * @param {Object} message
 */
u2f.WrappedChromeRuntimePort_.prototype.postMessage = function(message) {
    this.port_.postMessage(message);
};

/**
 * Emulates the HTML 5 addEventListener interface. Works only for the
 * onmessage event, which is hooked up to the chrome.runtime.Port.onMessage.
 * @param {string} eventName
 * @param {function({data: Object})} handler
 */
u2f.WrappedChromeRuntimePort_.prototype.addEventListener =
    function(eventName, handler) {
        var name = eventName.toLowerCase();
        if (name == 'message' || name == 'onmessage') {
            this.port_.onMessage.addListener(function(message) {
                // Emulate a minimal MessageEvent object
                handler({'data': message});
            });
        } else {
            console.error('WrappedChromeRuntimePort only supports onMessage');
        }
    };

/**
 * Wrap the Authenticator app with a MessagePort interface.
 * @constructor
 * @private
 */
u2f.WrappedAuthenticatorPort_ = function() {
    this.requestId_ = -1;
    this.requestObject_ = null;
}

/**
 * Launch the Authenticator intent.
 * @param {Object} message
 */
u2f.WrappedAuthenticatorPort_.prototype.postMessage = function(message) {
    var intentLocation = /** @type {string} */ (message);
    document.location = intentLocation;
};

/**
 * Emulates the HTML 5 addEventListener interface.
 * @param {string} eventName
 * @param {function({data: Object})} handler
 */
u2f.WrappedAuthenticatorPort_.prototype.addEventListener =
    function(eventName, handler) {
        var name = eventName.toLowerCase();
        if (name == 'message') {
            var self = this;
            /* Register a callback to that executes when
             * chrome injects the response. */
            window.addEventListener(
                'message', self.onRequestUpdate_.bind(self, handler), false);
        } else {
            console.error('WrappedAuthenticatorPort only supports message');
        }
    };

/**
 * Callback invoked  when a response is received from the Authenticator.
 * @param function({data: Object}) callback
 * @param {Object} message message Object
 */
u2f.WrappedAuthenticatorPort_.prototype.onRequestUpdate_ =
    function(callback, message) {
        var messageObject = JSON.parse(message.data);
        var intentUrl = messageObject['intentURL'];

        var errorCode = messageObject['errorCode'];
        var responseObject = null;
        if (messageObject.hasOwnProperty('data')) {
            responseObject = /** @type {Object} */ (
                JSON.parse(messageObject['data']));
            responseObject['requestId'] = this.requestId_;
        }

        /* Sign responses from the authenticator do not conform to U2F,
         * convert to U2F here. */
        responseObject = this.doResponseFixups_(responseObject);
        callback({'data': responseObject});
    };

/**
 * Fixup the response provided by the Authenticator to conform with
 * the U2F spec.
 * @param {Object} responseData
 * @return {Object} the U2F compliant response object
 */
u2f.WrappedAuthenticatorPort_.prototype.doResponseFixups_ =
    function(responseObject) {
        if (responseObject.hasOwnProperty('responseData')) {
            return responseObject;
        } else if (this.requestObject_['type'] != u2f.MessageTypes.U2F_SIGN_REQUEST) {
            // Only sign responses require fixups.  If this is not a response
            // to a sign request, then an internal error has occurred.
            return {
                'type': u2f.MessageTypes.U2F_REGISTER_RESPONSE,
                'responseData': {
                    'errorCode': u2f.ErrorCodes.OTHER_ERROR,
                    'errorMessage': 'Internal error: invalid response from Authenticator'
                }
            };
        }

        /* Non-conformant sign response, do fixups. */
        var encodedChallengeObject = responseObject['challenge'];
        if (typeof encodedChallengeObject !== 'undefined') {
            var challengeObject = JSON.parse(atob(encodedChallengeObject));
            var serverChallenge = challengeObject['challenge'];
            var challengesList = this.requestObject_['signData'];
            var requestChallengeObject = null;
            for (var i = 0; i < challengesList.length; i++) {
                var challengeObject = challengesList[i];
                if (challengeObject['keyHandle'] == responseObject['keyHandle']) {
                    requestChallengeObject = challengeObject;
                    break;
                }
            }
        }
        var responseData = {
            'errorCode': responseObject['resultCode'],
            'keyHandle': responseObject['keyHandle'],
            'signatureData': responseObject['signature'],
            'clientData': encodedChallengeObject
        };
        return {
            'type': u2f.MessageTypes.U2F_SIGN_RESPONSE,
            'responseData': responseData,
            'requestId': responseObject['requestId']
        }
    };

/**
 * Base URL for intents to Authenticator.
 * @const
 * @private
 */
u2f.WrappedAuthenticatorPort_.INTENT_URL_BASE_ =
    'intent:#Intent;action=com.google.android.apps.authenticator.AUTHENTICATE';

/**
 * Format a return a sign request.
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {number} timeoutSeconds (ignored for now)
 * @param {number} reqId
 * @return {string}
 */
u2f.WrappedAuthenticatorPort_.prototype.formatSignRequest_ =
    function(signRequests, timeoutSeconds, reqId) {
        if (!signRequests || signRequests.length == 0) {
            return null;
        }
        /* TODO(fixme): stash away requestId, as the authenticator app does
         * not return it for sign responses. */
        this.requestId_ = reqId;
        /* TODO(fixme): stash away the signRequests, to deal with the legacy
         * response format returned by the Authenticator app. */
        this.requestObject_ = {
            'type': u2f.MessageTypes.U2F_SIGN_REQUEST,
            'signData': signRequests,
            'requestId': reqId,
            'timeout': timeoutSeconds
        };

        var appId = signRequests[0]['appId'];
        var intentUrl =
            u2f.WrappedAuthenticatorPort_.INTENT_URL_BASE_ +
            ';S.appId=' + encodeURIComponent(appId) +
            ';S.eventId=' + reqId +
            ';S.challenges=' +
            encodeURIComponent(
                JSON.stringify(this.getBrowserDataList_(signRequests))) + ';end';
        return intentUrl;
    };

/**
 * Get the browser data objects from the challenge list
 * @param {Array} challenges list of challenges
 * @return {Array} list of browser data objects
 * @private
 */
u2f.WrappedAuthenticatorPort_
    .prototype.getBrowserDataList_ = function(challenges) {
    return challenges
        .map(function(challenge) {
            var browserData = {
                'typ': 'navigator.id.getAssertion',
                'challenge': challenge['challenge']
            };
            var challengeObject = {
                'challenge' : browserData,
                'keyHandle' : challenge['keyHandle']
            };
            return challengeObject;
        });
};

/**
 * Format a return a register request.
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {Array<u2f.RegisterRequest>} enrollChallenges
 * @param {number} timeoutSeconds (ignored for now)
 * @param {number} reqId
 * @return {Object}
 */
u2f.WrappedAuthenticatorPort_.prototype.formatRegisterRequest_ =
    function(signRequests, enrollChallenges, timeoutSeconds, reqId) {
        if (!enrollChallenges || enrollChallenges.length == 0) {
            return null;
        }
        // Assume the appId is the same for all enroll challenges.
        var appId = enrollChallenges[0]['appId'];
        var registerRequests = [];
        for (var i = 0; i < enrollChallenges.length; i++) {
            var registerRequest = {
                'challenge': enrollChallenges[i]['challenge'],
                'version': enrollChallenges[i]['version']
            };
            if (enrollChallenges[i]['appId'] != appId) {
                // Only include the appId when it differs from the first appId.
                registerRequest['appId'] = enrollChallenges[i]['appId'];
            }
            registerRequests.push(registerRequest);
        }
        var registeredKeys = [];
        if (signRequests) {
            for (i = 0; i < signRequests.length; i++) {
                var key = {
                    'keyHandle': signRequests[i]['keyHandle'],
                    'version': signRequests[i]['version']
                };
                // Only include the appId when it differs from the appId that's
                // being registered now.
                if (signRequests[i]['appId'] != appId) {
                    key['appId'] = signRequests[i]['appId'];
                }
                registeredKeys.push(key);
            }
        }
        var request = {
            'type': u2f.MessageTypes.U2F_REGISTER_REQUEST,
            'appId': appId,
            'registerRequests': registerRequests,
            'registeredKeys': registeredKeys,
            'requestId': reqId,
            'timeoutSeconds': timeoutSeconds
        };
        var intentUrl =
            u2f.WrappedAuthenticatorPort_.INTENT_URL_BASE_ +
            ';S.request=' + encodeURIComponent(JSON.stringify(request)) +
            ';end';
        /* TODO(fixme): stash away requestId, this is is not necessary for
         * register requests, but here to keep parity with sign.
         */
        this.requestId_ = reqId;
        return intentUrl;
    };


/**
 * Sets up an embedded trampoline iframe, sourced from the extension.
 * @param {function(MessagePort)} callback
 * @private
 */
u2f.getIframePort_ = function(callback) {
    // Create the iframe
    var iframeOrigin = 'chrome-extension://' + u2f.EXTENSION_ID;
    var iframe = document.createElement('iframe');
    iframe.src = iframeOrigin + '/u2f-comms.html';
    iframe.setAttribute('style', 'display:none');
    document.body.appendChild(iframe);

    var channel = new MessageChannel();
    var ready = function(message) {
        if (message.data == 'ready') {
            channel.port1.removeEventListener('message', ready);
            callback(channel.port1);
        } else {
            console.error('First event on iframe port was not "ready"');
        }
    };
    channel.port1.addEventListener('message', ready);
    channel.port1.start();

    iframe.addEventListener('load', function() {
        // Deliver the port to the iframe and initialize
        iframe.contentWindow.postMessage('init', iframeOrigin, [channel.port2]);
    });
};


// High-level JS API

/**
 * Default extension response timeout in seconds.
 * @const
 */
u2f.EXTENSION_TIMEOUT_SEC = 30;

/**
 * A singleton instance for a MessagePort to the extension.
 * @type {MessagePort|u2f.WrappedChromeRuntimePort_}
 * @private
 */
u2f.port_ = null;

/**
 * Callbacks waiting for a port
 * @type {Array<function((MessagePort|u2f.WrappedChromeRuntimePort_))>}
 * @private
 */
u2f.waitingForPort_ = [];

/**
 * A counter for requestIds.
 * @type {number}
 * @private
 */
u2f.reqCounter_ = 0;

/**
 * A map from requestIds to client callbacks
 * @type {Object.<number,(function((u2f.Error|u2f.RegisterResponse))
 *                       |function((u2f.Error|u2f.SignResponse)))>}
 * @private
 */
u2f.callbackMap_ = {};

/**
 * Creates or retrieves the MessagePort singleton to use.
 * @param {function((MessagePort|u2f.WrappedChromeRuntimePort_))} callback
 * @private
 */
u2f.getPortSingleton_ = function(callback) {
    if (u2f.port_) {
        callback(u2f.port_);
    } else {
        if (u2f.waitingForPort_.length == 0) {
            u2f.getMessagePort(function(port) {
                u2f.port_ = port;
                u2f.port_.addEventListener('message',
                    /** @type {function(Event)} */ (u2f.responseHandler_));

                // Careful, here be async callbacks. Maybe.
                while (u2f.waitingForPort_.length)
                    u2f.waitingForPort_.shift()(u2f.port_);
            });
        }
        u2f.waitingForPort_.push(callback);
    }
};

/**
 * Handles response messages from the extension.
 * @param {MessageEvent.<u2f.Response>} message
 * @private
 */
u2f.responseHandler_ = function(message) {
    var response = message.data;
    var reqId = response['requestId'];
    if (!reqId || !u2f.callbackMap_[reqId]) {
        console.error('Unknown or missing requestId in response.');
        return;
    }
    var cb = u2f.callbackMap_[reqId];
    delete u2f.callbackMap_[reqId];
    cb(response['responseData']);
};

/**
 * Dispatches an array of sign requests to available U2F tokens.
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {function((u2f.Error|u2f.SignResponse))} callback
 * @param {number=} opt_timeoutSeconds
 */
u2f.sign = function(signRequests, callback, opt_timeoutSeconds) {
    u2f.getPortSingleton_(function(port) {
        var reqId = ++u2f.reqCounter_;
        u2f.callbackMap_[reqId] = callback;
        var timeoutSeconds = (typeof opt_timeoutSeconds !== 'undefined' ?
            opt_timeoutSeconds : u2f.EXTENSION_TIMEOUT_SEC);
        var req = port.formatSignRequest_(signRequests, timeoutSeconds, reqId);
        port.postMessage(req);
    });
};

/**
 * Dispatches register requests to available U2F tokens. An array of sign
 * requests identifies already registered tokens.
 * @param {Array<u2f.RegisterRequest>} registerRequests
 * @param {Array<u2f.SignRequest>} signRequests
 * @param {function((u2f.Error|u2f.RegisterResponse))} callback
 * @param {number=} opt_timeoutSeconds
 */
u2f.register = function(registerRequests, signRequests,
                        callback, opt_timeoutSeconds) {
    u2f.getPortSingleton_(function(port) {
        var reqId = ++u2f.reqCounter_;
        u2f.callbackMap_[reqId] = callback;
        var timeoutSeconds = (typeof opt_timeoutSeconds !== 'undefined' ?
            opt_timeoutSeconds : u2f.EXTENSION_TIMEOUT_SEC);
        var req = port.formatRegisterRequest_(
            signRequests, registerRequests, timeoutSeconds, reqId);
        port.postMessage(req);
    });
};