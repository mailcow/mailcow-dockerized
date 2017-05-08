/** 
 * =================================================================
 * Javascript code for OWASP CSRF Protector
 * Task it does: Fetch csrftoken from cookie, and attach it to every
 * 		POST request
 *		Allowed GET url
 *			-- XHR
 *			-- Static Forms
 *			-- URLS (GET only)
 *			-- dynamic forms
 * =================================================================
 */

var CSRFP_FIELD_TOKEN_NAME = 'csrfp_hidden_data_token';
var CSRFP_FIELD_URLS = 'csrfp_hidden_data_urls';

var CSRFP = {
	CSRFP_TOKEN: 'csrfp_token',
	/**
	 * Array of patterns of url, for which csrftoken need to be added
	 * In case of GET request also, provided from server
	 *
	 * @var string array
	 */
	checkForUrls: [],
	/**
	 * Function to check if a certain url is allowed to perform the request
	 * With or without csrf token
	 *
	 * @param: string, url
	 *
	 * @return: boolean, 	true if csrftoken is not needed
	 * 						false if csrftoken is needed
	 */
	_isValidGetRequest: function(url) {
		for (var i = 0; i < CSRFP.checkForUrls.length; i++) {
			var match = CSRFP.checkForUrls[i].exec(url);
			if (match !== null && match.length > 0) {
				return false;
			}
		}
		return true;
	},
	/** 
	 * function to get Auth key from cookie Andreturn it to requesting function
	 *
	 * @param: void
	 *
	 * @return: string, csrftoken retrieved from cookie
	 */
	_getAuthKey: function() {
		var re = new RegExp(CSRFP.CSRFP_TOKEN +"=([^;]+)(;|$)");
		var RegExpArray = re.exec(document.cookie);
		
		if (RegExpArray === null) {
			return false;
		}
		return RegExpArray[1];
	},
	/** 
	 * Function to get domain of any url
	 *
	 * @param: string, url
	 *
	 * @return: string, domain of url
	 */
	_getDomain: function(url) {
		if (url.indexOf("http://") !== 0 
			&& url.indexOf("https://") !== 0)
			return document.domain;
		return /http(s)?:\/\/([^\/]+)/.exec(url)[2];
	},
	/**
	 * Function to create and return a hidden input element
	 * For stroing the CSRFP_TOKEN
	 *
	 * @param void
	 *
	 * @return input element
	 */
	_getInputElt: function() {
		var hiddenObj = document.createElement("input");
		hiddenObj.setAttribute('name', CSRFP.CSRFP_TOKEN);
		hiddenObj.setAttribute('class', CSRFP.CSRFP_TOKEN);
		hiddenObj.type = 'hidden';
		hiddenObj.value = CSRFP._getAuthKey();
		return hiddenObj;
	},
	/**
	 * Returns absolute path for relative path
	 * 
	 * @param base, base url
	 * @param relative, relative url
	 *
	 * @return absolute path (string)
	 */
	_getAbsolutePath: function(base, relative) {
		var stack = base.split("/");
		var parts = relative.split("/");
		// remove current file name (or empty string)
		// (omit if "base" is the current folder without trailing slash)
		stack.pop(); 
			 
		for (var i = 0; i < parts.length; i++) {
			if (parts[i] == ".")
				continue;
			if (parts[i] == "..")
				stack.pop();
			else
				stack.push(parts[i]);
		}
		return stack.join("/");
	},
	/** 
	 * Remove jcsrfp-token run fun and then put them back 
	 *
	 * @param function
	 * @param reference form obj
	 *
	 * @retrun function
	 */
	_csrfpWrap: function(fun, obj) {
		return function(event) {
			// Remove CSRf token if exists
			if (typeof obj[CSRFP.CSRFP_TOKEN] !== 'undefined') {
				var target = obj[CSRFP.CSRFP_TOKEN];
				target.parentNode.removeChild(target);
			}
			
			// Trigger the functions
			var result = fun.apply(this, [event]);
			
			// Now append the csrfp_token back
			obj.appendChild(CSRFP._getInputElt());
			
			return result;
		};
	},
	/**
	 * Initialises the CSRFProtector js script
	 *
	 * @param void
	 *
	 * @return void
	 */
	_init: function() {
		CSRFP.CSRFP_TOKEN = document.getElementById(CSRFP_FIELD_TOKEN_NAME).value;
		try {
			CSRFP.checkForUrls = JSON.parse(document.getElementById(CSRFP_FIELD_URLS).value);
		} catch (err) {
			console.error(err);
			console.error('[ERROR] [CSRF Protector] unable to parse blacklisted url fields.');
		}

		//convert these rules received from php lib to regex objects
		for (var i = 0; i < CSRFP.checkForUrls.length; i++) {
			CSRFP.checkForUrls[i] = CSRFP.checkForUrls[i].replace(/\*/g, '(.*)')
								.replace(/\//g, "\\/");
			CSRFP.checkForUrls[i] = new RegExp(CSRFP.checkForUrls[i]);
		}
	
	}
	
}; 

//==========================================================
// Adding tokens, wrappers on window onload
//==========================================================

function csrfprotector_init() {
	
	// Call the init funcion
	CSRFP._init();

	// definition of basic FORM submit event handler to intercept the form request
	// and attach a CSRFP TOKEN if it's not already available
	var BasicSubmitInterceptor = function(event) {
		if (typeof event.target[CSRFP.CSRFP_TOKEN] === 'undefined') {
			event.target.appendChild(CSRFP._getInputElt());
		} else {
			//modify token to latest value
			event.target[CSRFP.CSRFP_TOKEN].value = CSRFP._getAuthKey();
		}
	}

	//==================================================================
	// Adding csrftoken to request resulting from <form> submissions
	// Add for each POST, while for mentioned GET request
	// TODO - check for method
	//==================================================================
	// run time binding
	document.querySelector('body').addEventListener('submit', function(event) {
		if (event.target.tagName.toLowerCase() === 'form') {
			BasicSubmitInterceptor(event);
		};
	});

	// intial binding
	// for(var i = 0; i < document.forms.length; i++) {
	// 	document.forms[i].addEventListener("submit", BasicSubmitInterceptor);
	// }

	//==================================================================
	// Adding csrftoken to request resulting from direct form.submit() call
	// Add for each POST, while for mentioned GET request
	// TODO - check for form method
	//==================================================================
	HTMLFormElement.prototype.submit_ = HTMLFormElement.prototype.submit;
	HTMLFormElement.prototype.submit = function() {
		// check if the FORM already contains the token element
		if (!this.getElementsByClassName(CSRFP.CSRFP_TOKEN).length)
			this.appendChild(CSRFP._getInputElt());
		this.submit_();
	}


	/**
	 * Add wrapper for HTMLFormElements addEventListener so that any further 
	 * addEventListens won't have trouble with CSRF token
	 * todo - check for method
	 */
	HTMLFormElement.prototype.addEventListener_ = HTMLFormElement.prototype.addEventListener;
	HTMLFormElement.prototype.addEventListener = function(eventType, fun, bubble) {
		if (eventType === 'submit') {
			var wrapped = CSRFP._csrfpWrap(fun, this);
			this.addEventListener_(eventType, wrapped, bubble);
		} else {
			this.addEventListener_(eventType, fun, bubble);
		}	
	}

	/**
	 * Add wrapper for IE's attachEvent
	 * todo - check for method
	 * todo - typeof is now obselete for IE 11, use some other method.
	 */
	if (typeof HTMLFormElement.prototype.attachEvent !== 'undefined') {
		HTMLFormElement.prototype.attachEvent_ = HTMLFormElement.prototype.attachEvent;
		HTMLFormElement.prototype.attachEvent = function(eventType, fun) {
			if (eventType === 'onsubmit') {
				var wrapped = CSRFP._csrfpWrap(fun, this);
				this.attachEvent_(eventType, wrapped);
			} else {
				this.attachEvent_(eventType, fun);
			}
		}
	}


	//==================================================================
	// Wrapper for XMLHttpRequest & ActiveXObject (for IE 6 & below)
	// Set X-No-CSRF to true before sending if request method is 
	//==================================================================

	/** 
	 * Wrapper to XHR open method
	 * Add a property method to XMLHttpRequst class
	 * @param: all parameters to XHR open method
	 * @return: object returned by default, XHR open method
	 */
	function new_open(method, url, async, username, password) {
		this.method = method;
		var isAbsolute = (url.indexOf("./") === -1) ? true : false;
		if (!isAbsolute) {
			var base = location.protocol +'//' +location.host 
							+ location.pathname;
			url = CSRFP._getAbsolutePath(base, url);
		}
		if (method.toLowerCase() === 'get' 
			&& !CSRFP._isValidGetRequest(url)) {
			//modify the url
			if (url.indexOf('?') === -1) {
				url += "?" +CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey();
			} else {
				url += "&" +CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey();
			}
		}

		return this.old_open(method, url, async, username, password);
	}

	/** 
	 * Wrapper to XHR send method
	 * Add query paramter to XHR object
	 *
	 * @param: all parameters to XHR send method
	 *
	 * @return: object returned by default, XHR send method
	 */
	function new_send(data) {
		if (this.method.toLowerCase() === 'post') {
			if (data !== null && typeof data === 'object') {
				data.append(CSRFP.CSRFP_TOKEN, CSRFP._getAuthKey());
			} else {
				if (typeof data != "undefined") {
					data += "&";
				} else {
					data = "";
				}
				data += CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey();
			}
		}
		return this.old_send(data);
	}

	if (window.XMLHttpRequest) {
		// Wrapping
		XMLHttpRequest.prototype.old_send = XMLHttpRequest.prototype.send;
		XMLHttpRequest.prototype.old_open = XMLHttpRequest.prototype.open;
		XMLHttpRequest.prototype.open = new_open;
		XMLHttpRequest.prototype.send = new_send;
	}
	if (typeof ActiveXObject !== 'undefined') {
		ActiveXObject.prototype.old_send = ActiveXObject.prototype.send;
		ActiveXObject.prototype.old_open = ActiveXObject.prototype.open;
		ActiveXObject.prototype.open = new_open;
		ActiveXObject.prototype.send = new_send;	
	}
	//==================================================================
	// Rewrite existing urls ( Attach CSRF token )
	// Rules:
	// Rewrite those urls which matches the regex sent by Server
	// Ignore cross origin urls & internal links (one with hashtags)
	// Append the token to those url already containig GET query parameter(s)
	// Add the token to those which does not contain GET query parameter(s)
	//==================================================================

	for (var i = 0; i < document.links.length; i++) {
		document.links[i].addEventListener("mousedown", function(event) {
			var href = event.target.href;
			if(typeof href === "string")
			{
				var urlDisect = href.split('#');
				var url = urlDisect[0];
				var hash = urlDisect[1];

				if(CSRFP._getDomain(url).indexOf(document.domain) === -1
					|| CSRFP._isValidGetRequest(url)) {
					//cross origin or not to be protected by rules -- ignore
					return;
				}

				if (url.indexOf('?') !== -1) {
					if(url.indexOf(CSRFP.CSRFP_TOKEN) === -1) {
						url += "&" +CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey();
					} else {
						url = url.replace(new RegExp(CSRFP.CSRFP_TOKEN +"=.*?(&|$)", 'g'),
							CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey() + "$1");
					}
				} else {
					url += "?" +CSRFP.CSRFP_TOKEN +"=" +CSRFP._getAuthKey();
				}

				event.target.href = url;
				if (typeof hash !== 'undefined') {
					event.target.href += '#' +hash;
				}
			}
		});
	}

}

window.addEventListener("DOMContentLoaded", function() {
	csrfprotector_init();
}, false);
