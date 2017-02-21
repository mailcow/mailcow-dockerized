/**
 * Roundcube common js library
 *
 * This file is part of the Roundcube Webmail client
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2005-2014, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can
 * redistribute it and/or modify it under the terms of the GNU
 * General Public License (GNU GPL) as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option)
 * any later version.  The code is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU GPL for more details.
 *
 * As additional permission under GNU GPL version 3 section 7, you
 * may distribute non-source (e.g., minimized or compacted) forms of
 * that code without the copy of the GNU GPL normally required by
 * section 4, provided you include this license notice and a URL
 * through which recipients can access the Corresponding Source.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

// Constants
var CONTROL_KEY = 1;
var SHIFT_KEY = 2;
var CONTROL_SHIFT_KEY = 3;

/**
 * Default browser check class
 * @constructor
 */
function roundcube_browser()
{
  var n = navigator;

  this.agent = n.userAgent;
  this.agent_lc = n.userAgent.toLowerCase();
  this.name = n.appName;
  this.vendor = n.vendor ? n.vendor : '';
  this.vendver = n.vendorSub ? parseFloat(n.vendorSub) : 0;
  this.product = n.product ? n.product : '';
  this.platform = String(n.platform).toLowerCase();
  this.lang = n.language ? n.language.substring(0,2) :
              n.browserLanguage ? n.browserLanguage.substring(0,2) :
              n.systemLanguage ? n.systemLanguage.substring(0,2) : 'en';

  this.win = this.platform.indexOf('win') >= 0;
  this.mac = this.platform.indexOf('mac') >= 0;
  this.linux = this.platform.indexOf('linux') >= 0;
  this.unix = this.platform.indexOf('unix') >= 0;

  this.dom = document.getElementById ? true : false;
  this.dom2 = document.addEventListener && document.removeEventListener;

  this.webkit = this.agent_lc.indexOf('applewebkit') > 0;
  this.ie = (document.all && !window.opera) || (this.win && this.agent_lc.indexOf('trident/') > 0);

  if (window.opera) {
    this.opera = true; // Opera < 15
    this.vendver = opera.version();
  }
  else if (!this.ie) {
    this.chrome = this.agent_lc.indexOf('chrome') > 0;
    this.opera = this.webkit && this.agent.indexOf(' OPR/') > 0; // Opera >= 15
    this.safari = !this.chrome && !this.opera && (this.webkit || this.agent_lc.indexOf('safari') > 0);
    this.konq = this.agent_lc.indexOf('konqueror') > 0;
    this.mz = this.dom && !this.chrome && !this.safari && !this.konq && !this.opera && this.agent.indexOf('Mozilla') >= 0;
    this.iphone = this.safari && (this.agent_lc.indexOf('iphone') > 0 || this.agent_lc.indexOf('ipod') > 0);
    this.ipad = this.safari && this.agent_lc.indexOf('ipad') > 0;
  }

  if (!this.vendver) {
    // common version strings
    this.vendver = /(opera|opr|khtml|chrome|safari|applewebkit|msie)(\s|\/)([0-9\.]+)/.test(this.agent_lc) ? parseFloat(RegExp.$3) : 0;

    // any other (Mozilla, Camino, IE>=11)
    if (!this.vendver)
      this.vendver = /rv:([0-9\.]+)/.test(this.agent) ? parseFloat(RegExp.$1) : 0;
  }

  // get real language out of safari's user agent
  if (this.safari && (/;\s+([a-z]{2})-[a-z]{2}\)/.test(this.agent_lc)))
    this.lang = RegExp.$1;

  this.tablet = /ipad|android|xoom|sch-i800|playbook|tablet|kindle/i.test(this.agent_lc);
  this.mobile = /iphone|ipod|blackberry|iemobile|opera mini|opera mobi|mobile/i.test(this.agent_lc);
  this.touch = this.mobile || this.tablet;
  this.cookies = n.cookieEnabled;

  // test for XMLHTTP support
  this.xmlhttp_test = function()
  {
    var activeX_test = new Function("try{var o=new ActiveXObject('Microsoft.XMLHTTP');return true;}catch(err){return false;}");
    this.xmlhttp = window.XMLHttpRequest || (('ActiveXObject' in window) && activeX_test());
    return this.xmlhttp;
  };

  // set class names to html tag according to the current user agent detection
  // this allows browser-specific css selectors like "html.chrome .someclass"
  this.set_html_class = function()
  {
    var classname = ' js';

    if (this.ie)
      classname += ' ie ie'+parseInt(this.vendver);
    else if (this.opera)
      classname += ' opera';
    else if (this.konq)
      classname += ' konqueror';
    else if (this.safari)
      classname += ' chrome';
    else if (this.chrome)
      classname += ' chrome';
    else if (this.mz)
      classname += ' mozilla';

    if (this.iphone)
      classname += ' iphone';
    else if (this.ipad)
      classname += ' ipad';
    else if (this.webkit)
      classname += ' webkit';

    if (this.mobile)
      classname += ' mobile';
    if (this.tablet)
      classname += ' tablet';

    if (document.documentElement)
      document.documentElement.className += classname;
  };
};


// static functions for DOM event handling
var rcube_event = {

/**
 * returns the event target element
 */
get_target: function(e)
{
  e = e || window.event;
  return e && e.target ? e.target : e.srcElement;
},

/**
 * returns the event key code
 */
get_keycode: function(e)
{
  e = e || window.event;
  return e && e.keyCode ? e.keyCode : (e && e.which ? e.which : 0);
},

/**
 * returns the event key code
 */
get_button: function(e)
{
  e = e || window.event;
  return e && e.button !== undefined ? e.button : (e && e.which ? e.which : 0);
},

/**
 * returns modifier key (constants defined at top of file)
 */
get_modifier: function(e)
{
  var opcode = 0;
  e = e || window.event;

  if (bw.mac && e)
    opcode += (e.metaKey && CONTROL_KEY) + (e.shiftKey && SHIFT_KEY);
  else if (e)
    opcode += (e.ctrlKey && CONTROL_KEY) + (e.shiftKey && SHIFT_KEY);

  return opcode;
},

/**
 * Return absolute mouse position of an event
 */
get_mouse_pos: function(e)
{
  if (!e) e = window.event;
  var mX = (e.pageX) ? e.pageX : e.clientX,
    mY = (e.pageY) ? e.pageY : e.clientY;

  if (document.body && document.all) {
    mX += document.body.scrollLeft;
    mY += document.body.scrollTop;
  }

  if (e._offset) {
    mX += e._offset.left;
    mY += e._offset.top;
  }

  return { x:mX, y:mY };
},

/**
 * Add an object method as event listener to a certain element
 */
add_listener: function(p)
{
  if (!p.object || !p.method)  // not enough arguments
    return;
  if (!p.element)
    p.element = document;

  if (!p.object._rc_events)
    p.object._rc_events = {};

  var key = p.event + '*' + p.method;
  if (!p.object._rc_events[key])
    p.object._rc_events[key] = function(e){ return p.object[p.method](e); };

  if (p.element.addEventListener)
    p.element.addEventListener(p.event, p.object._rc_events[key], false);
  else if (p.element.attachEvent) {
    // IE allows multiple events with the same function to be applied to the same object
    // forcibly detach the event, then attach
    p.element.detachEvent('on'+p.event, p.object._rc_events[key]);
    p.element.attachEvent('on'+p.event, p.object._rc_events[key]);
  }
  else
    p.element['on'+p.event] = p.object._rc_events[key];
},

/**
 * Remove event listener
 */
remove_listener: function(p)
{
  if (!p.element)
    p.element = document;

  var key = p.event + '*' + p.method;
  if (p.object && p.object._rc_events && p.object._rc_events[key]) {
    if (p.element.removeEventListener)
      p.element.removeEventListener(p.event, p.object._rc_events[key], false);
    else if (p.element.detachEvent)
      p.element.detachEvent('on'+p.event, p.object._rc_events[key]);
    else
      p.element['on'+p.event] = null;
  }
},

/**
 * Prevent event propagation and bubbling
 */
cancel: function(evt)
{
  var e = evt ? evt : window.event;

  if (e.preventDefault)
    e.preventDefault();
  else
    e.returnValue = false;

  if (e.stopPropagation)
    e.stopPropagation();

  e.cancelBubble = true;

  return false;
},

/**
 * Determine whether the given event was trigered from keyboard
 */
is_keyboard: function(e)
{
  return e && (
      (e.type && String(e.type).match(/^key/)) // DOM3-compatible
      || (!e.pageX && (e.pageY || 0) <= 0 && !e.clientX && (e.clientY || 0) <= 0) // others
    );
},

/**
 * Accept event if triggered from keyboard action (e.g. <Enter>)
 */
keyboard_only: function(e)
{
  return rcube_event.is_keyboard(e) ? true : rcube_event.cancel(e);
},

touchevent: function(e)
{
  return { pageX:e.pageX, pageY:e.pageY, offsetX:e.pageX - e.target.offsetLeft, offsetY:e.pageY - e.target.offsetTop, target:e.target, istouch:true };
}

};


/**
 * rcmail objects event interface
 */
function rcube_event_engine()
{
  this._events = {};
};

rcube_event_engine.prototype = {

/**
 * Setter for object event handlers
 *
 * @param {String}   Event name
 * @param {Function} Handler function
 */
addEventListener: function(evt, func, obj)
{
  if (!this._events)
    this._events = {};
  if (!this._events[evt])
    this._events[evt] = [];

  this._events[evt].push({func:func, obj:obj ? obj : window});

  return this; // chainable
},

/**
 * Removes a specific event listener
 *
 * @param {String} Event name
 * @param {Int}    Listener ID to remove
 */
removeEventListener: function(evt, func, obj)
{
  if (obj === undefined)
    obj = window;

  for (var h,i=0; this._events && this._events[evt] && i < this._events[evt].length; i++)
    if ((h = this._events[evt][i]) && h.func == func && h.obj == obj)
      this._events[evt][i] = null;
},

/**
 * This will execute all registered event handlers
 *
 * @param {String} Event to trigger
 * @param {Object} Event object/arguments
 */
triggerEvent: function(evt, e)
{
  var ret, h;

  if (e === undefined)
    e = this;
  else if (typeof e === 'object')
    e.event = evt;

  if (!this._event_exec)
    this._event_exec = {};

  if (this._events && this._events[evt] && !this._event_exec[evt]) {
    this._event_exec[evt] = true;
    for (var i=0; i < this._events[evt].length; i++) {
      if ((h = this._events[evt][i])) {
        if (typeof h.func === 'function')
          ret = h.func.call ? h.func.call(h.obj, e) : h.func(e);
        else if (typeof h.obj[h.func] === 'function')
          ret = h.obj[h.func](e);

        // cancel event execution
        if (ret !== undefined && !ret)
          break;
      }
    }
    if (ret && ret.event) {
      try {
        delete ret.event;
      } catch (err) {
        // IE6-7 doesn't support deleting HTMLFormElement attributes (#1488017)
        $(ret).removeAttr('event');
      }
    }
  }

  delete this._event_exec[evt];

  if (e.event) {
    try {
      delete e.event;
    } catch (err) {
      // IE6-7 doesn't support deleting HTMLFormElement attributes (#1488017)
      $(e).removeAttr('event');
    }
  }

  return ret;
}

};  // end rcube_event_engine.prototype


// check if input is a valid email address
// By Cal Henderson <cal@iamcal.com>
// http://code.iamcal.com/php/rfc822/
function rcube_check_email(input, inline, count)
{
  if (!input)
    return count ? 0 : false;

  if (count) inline = true;

  var qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]',
      dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]',
      atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+',
      quoted_pair = '\\x5c[\\x00-\\x7f]',
      quoted_string = '\\x22('+qtext+'|'+quoted_pair+')*\\x22',
      ipv4 = '\\[(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}\\]',
      ipv6 = '\\[IPv6:[0-9a-f:.]+\\]',
      ip_addr = '(' + ipv4 + ')|(' + ipv6 + ')',
      // Use simplified domain matching, because we need to allow Unicode characters here
      // So, e-mail address should be validated also on server side after idn_to_ascii() use
      //domain_literal = '\\x5b('+dtext+'|'+quoted_pair+')*\\x5d',
      //sub_domain = '('+atom+'|'+domain_literal+')',
      // allow punycode/unicode top-level domain
      domain = '(('+ip_addr+')|(([^@\\x2e]+\\x2e)+([^\\x00-\\x40\\x5b-\\x60\\x7b-\\x7f]{2,}|xn--[a-z0-9]{2,})))',
      // ICANN e-mail test (http://idn.icann.org/E-mail_test)
      icann_domains = [
        '\\u0645\\u062b\\u0627\\u0644\\x2e\\u0625\\u062e\\u062a\\u0628\\u0627\\u0631',
        '\\u4f8b\\u5b50\\x2e\\u6d4b\\u8bd5',
        '\\u4f8b\\u5b50\\x2e\\u6e2c\\u8a66',
        '\\u03c0\\u03b1\\u03c1\\u03ac\\u03b4\\u03b5\\u03b9\\u03b3\\u03bc\\u03b1\\x2e\\u03b4\\u03bf\\u03ba\\u03b9\\u03bc\\u03ae',
        '\\u0909\\u0926\\u093e\\u0939\\u0930\\u0923\\x2e\\u092a\\u0930\\u0940\\u0915\\u094d\\u0937\\u093e',
        '\\u4f8b\\u3048\\x2e\\u30c6\\u30b9\\u30c8',
        '\\uc2e4\\ub840\\x2e\\ud14c\\uc2a4\\ud2b8',
        '\\u0645\\u062b\\u0627\\u0644\\x2e\\u0622\\u0632\\u0645\\u0627\\u06cc\\u0634\u06cc',
        '\\u043f\\u0440\\u0438\\u043c\\u0435\\u0440\\x2e\\u0438\\u0441\\u043f\\u044b\\u0442\\u0430\\u043d\\u0438\\u0435',
        '\\u0b89\\u0ba4\\u0bbe\\u0bb0\\u0ba3\\u0bae\\u0bcd\\x2e\\u0baa\\u0bb0\\u0bbf\\u0b9f\\u0bcd\\u0b9a\\u0bc8',
        '\\u05d1\\u05f2\\u05b7\\u05e9\\u05e4\\u05bc\\u05d9\\u05dc\\x2e\\u05d8\\u05e2\\u05e1\\u05d8'
      ],
      icann_addr = 'mailtest\\x40('+icann_domains.join('|')+')',
      word = '('+atom+'|'+quoted_string+')',
      delim = '[,;\\s\\n]',
      local_part = word+'(\\x2e'+word+')*',
      addr_spec = '(('+local_part+'\\x40'+domain+')|('+icann_addr+'))',
      rx_flag = count ? 'ig' : 'i',
      rx = inline ? new RegExp('(^|<|'+delim+')'+addr_spec+'($|>|'+delim+')', rx_flag) : new RegExp('^'+addr_spec+'$', 'i');

  if (count)
    return input.match(rx).length;

  return rx.test(input);
};

// recursively copy an object
function rcube_clone_object(obj)
{
  var out = {};

  for (var key in obj) {
    if (obj[key] && typeof obj[key] === 'object')
      out[key] = rcube_clone_object(obj[key]);
    else
      out[key] = obj[key];
  }

  return out;
};

// make a string URL safe (and compatible with PHP's rawurlencode())
function urlencode(str)
{
  if (window.encodeURIComponent)
    return encodeURIComponent(str).replace('*', '%2A');

  return escape(str)
    .replace('+', '%2B')
    .replace('*', '%2A')
    .replace('/', '%2F')
    .replace('@', '%40');
};


// get any type of html objects by id/name
function rcube_find_object(id, d)
{
  var n, f, obj, e;

  if (!d) d = document;

  if (d.getElementById)
    if (obj = d.getElementById(id))
      return obj;

  if (!obj && d.getElementsByName && (e = d.getElementsByName(id)))
    obj = e[0];

  if (!obj && d.all)
    obj = d.all[id];

  if (!obj && d.images.length)
    obj = d.images[id];

  if (!obj && d.forms.length) {
    for (f=0; f<d.forms.length; f++) {
      if (d.forms[f].name == id)
        obj = d.forms[f];
      else if(d.forms[f].elements[id])
        obj = d.forms[f].elements[id];
    }
  }

  if (!obj && d.layers) {
    if (d.layers[id])
      obj = d.layers[id];
    for (n=0; !obj && n<d.layers.length; n++)
      obj = rcube_find_object(id, d.layers[n].document);
  }

  return obj;
};

// determine whether the mouse is over the given object or not
function rcube_mouse_is_over(ev, obj)
{
  var mouse = rcube_event.get_mouse_pos(ev),
    pos = $(obj).offset();

  return (mouse.x >= pos.left) && (mouse.x < (pos.left + obj.offsetWidth)) &&
    (mouse.y >= pos.top) && (mouse.y < (pos.top + obj.offsetHeight));
};


// cookie functions by GoogieSpell
function setCookie(name, value, expires, path, domain, secure)
{
  var curCookie = name + "=" + escape(value) +
      (expires ? "; expires=" + expires.toGMTString() : "") +
      (path ? "; path=" + path : "") +
      (domain ? "; domain=" + domain : "") +
      (secure ? "; secure" : "");

  document.cookie = curCookie;
};

function getCookie(name)
{
  var dc = document.cookie,
    prefix = name + "=",
    begin = dc.indexOf("; " + prefix);

  if (begin == -1) {
    begin = dc.indexOf(prefix);
    if (begin != 0)
      return null;
  }
  else {
    begin += 2;
  }

  var end = dc.indexOf(";", begin);
  if (end == -1)
    end = dc.length;

  return unescape(dc.substring(begin + prefix.length, end));
};

// deprecated aliases, to be removed, use rcmail.set_cookie/rcmail.get_cookie
roundcube_browser.prototype.set_cookie = setCookie;
roundcube_browser.prototype.get_cookie = getCookie;

var bw = new roundcube_browser();
bw.set_html_class();


// Add escape() method to RegExp object
// http://dev.rubyonrails.org/changeset/7271
RegExp.escape = function(str)
{
  return String(str).replace(/([.*+?^=!:${}()|[\]\/\\])/g, '\\$1');
};

// Extend Date prototype to detect Standard timezone without DST
// from http://www.michaelapproved.com/articles/timezone-detect-and-ignore-daylight-saving-time-dst/
Date.prototype.getStdTimezoneOffset = function()
{
  var m = 12,
    d = new Date(null, m, 1),
    tzo = d.getTimezoneOffset();

    while (--m) {
      d.setUTCMonth(m);
      if (tzo != d.getTimezoneOffset()) {
        return Math.max(tzo, d.getTimezoneOffset());
    }
  }

  return tzo;
}

// define String's startsWith() method for old browsers
if (!String.prototype.startsWith) {
  String.prototype.startsWith = function(search, position) {
    position = position || 0;
    return this.slice(position, search.length) === search;
  };
}

// array utility function
jQuery.last = function(arr) {
  return arr && arr.length ? arr[arr.length-1] : undefined;
}

// jQuery plugin to set HTML5 placeholder and title attributes on input elements
jQuery.fn.placeholder = function(text) {
  return this.each(function() {
    $(this).prop({title: text, placeholder: text});
  });
};

// function to parse query string into an object
var rcube_parse_query = function(query)
{
  if (!query)
    return {};

  var params = {}, e, k, v,
    re = /([^&=]+)=?([^&]*)/g,
    decodeRE = /\+/g, // Regex for replacing addition symbol with a space
    decode = function (str) { return decodeURIComponent(str.replace(decodeRE, ' ')); };

  query = query.replace(/\?/, '');

  while (e = re.exec(query)) {
    k = decode(e[1]);
    v = decode(e[2]);

    if (k.substring(k.length - 2) === '[]') {
      k = k.substring(0, k.length - 2);
      (params[k] || (params[k] = [])).push(v);
    }
    else
      params[k] = v;
  }

  return params;
};


// Base64 code from Tyler Akins -- http://rumkin.com
var Base64 = (function () {
  var keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

  // private method for UTF-8 encoding
  var utf8_encode = function(string) {
    string = string.replace(/\r\n/g, "\n");
    var utftext = '';

    for (var n = 0; n < string.length; n++) {
      var c = string.charCodeAt(n);

      if (c < 128) {
        utftext += String.fromCharCode(c);
      }
      else if(c > 127 && c < 2048) {
        utftext += String.fromCharCode((c >> 6) | 192);
        utftext += String.fromCharCode((c & 63) | 128);
      }
      else {
        utftext += String.fromCharCode((c >> 12) | 224);
        utftext += String.fromCharCode(((c >> 6) & 63) | 128);
        utftext += String.fromCharCode((c & 63) | 128);
      }
    }

    return utftext;
  };

  // private method for UTF-8 decoding
  var utf8_decode = function (utftext) {
    var i = 0, string = '', c = 0, c2 = 0, c3 = 0;

    while (i < utftext.length) {
      c = utftext.charCodeAt(i);
      if (c < 128) {
        string += String.fromCharCode(c);
        i++;
      }
      else if (c > 191 && c < 224) {
        c2 = utftext.charCodeAt(i + 1);
        string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
        i += 2;
      }
      else {
        c2 = utftext.charCodeAt(i + 1);
        c3 = utftext.charCodeAt(i + 2);
        string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
        i += 3;
      }
    }

    return string;
  };

  var obj = {
    /**
     * Encodes a string in base64
     * @param {String} input The string to encode in base64.
     */
    encode: function (input) {
      // encode UTF8 as btoa() may fail on some characters
      input = utf8_encode(input);

      if (typeof(window.btoa) === 'function') {
        try {
          return btoa(input);
        }
        catch (e) {};
      }

      var chr1, chr2, chr3, enc1, enc2, enc3, enc4, i = 0, output = '', len = input.length;

      while (i < len) {
        chr1 = input.charCodeAt(i++);
        chr2 = input.charCodeAt(i++);
        chr3 = input.charCodeAt(i++);

        enc1 = chr1 >> 2;
        enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
        enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
        enc4 = chr3 & 63;

        if (isNaN(chr2))
          enc3 = enc4 = 64;
        else if (isNaN(chr3))
          enc4 = 64;

        output = output
          + keyStr.charAt(enc1) + keyStr.charAt(enc2)
          + keyStr.charAt(enc3) + keyStr.charAt(enc4);
      }

      return output;
    },

    /**
     * Decodes a base64 string.
     * @param {String} input The string to decode.
     */
    decode: function (input) {
      if (typeof(window.atob) === 'function') {
        try {
          return utf8_decode(atob(input));
        }
        catch (e) {};
      }

      var chr1, chr2, chr3, enc1, enc2, enc3, enc4, len, i = 0, output = '';

      // remove all characters that are not A-Z, a-z, 0-9, +, /, or =
      input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
      len = input.length;

      while (i < len) {
        enc1 = keyStr.indexOf(input.charAt(i++));
        enc2 = keyStr.indexOf(input.charAt(i++));
        enc3 = keyStr.indexOf(input.charAt(i++));
        enc4 = keyStr.indexOf(input.charAt(i++));

        chr1 = (enc1 << 2) | (enc2 >> 4);
        chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
        chr3 = ((enc3 & 3) << 6) | enc4;

        output = output + String.fromCharCode(chr1);

        if (enc3 != 64)
          output = output + String.fromCharCode(chr2);
        if (enc4 != 64)
          output = output + String.fromCharCode(chr3);
      }

      return utf8_decode(output);
    }
  };

  return obj;
})();
