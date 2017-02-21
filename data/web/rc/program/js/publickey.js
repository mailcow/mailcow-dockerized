/**
 * PublicKey.js - v0e011cb
 *
 * @source https://github.com/diafygi/publickeyjs/blob/master/publickey.js
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2015 Daniel Roesler
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
"use strict";

(function(context){
    /*
        Default keyservers (HTTPS and CORS enabled)
    */
    var DEFAULT_KEYSERVERS = [
        "https://keys.fedoraproject.org/",
        "https://keybase.io/",
    ];

    /*
        Initialization to create an PublicKey object.

        Arguments:

        * keyservers - Array of keyserver domains, default is:
            ["https://keys.fedoraproject.org/", "https://keybase.io/"]

        Examples:

        //Initialize with the default keyservers
        var hkp = new PublicKey();

        //Initialize only with a specific keyserver
        var hkp = new PublicKey(["https://key.ip6.li/"]);
    */
    var PublicKey = function(keyservers){
        this.keyservers = keyservers || DEFAULT_KEYSERVERS;
    };

    /*
        Get a public key from any keyserver based on keyId.

        Arguments:

        * keyId - String key id of the public key (this is usually a fingerprint)

        * callback - Function that is called when finished. Two arguments are
                passed to the callback: publicKey and errorCode. publicKey is
                an ASCII armored OpenPGP public key. errorCode is the error code
                (either HTTP status code or keybase error code) returned by the
                last keyserver that was tried. If a publicKey was found,
                errorCode is null. If no publicKey was found, publicKey is null
                and errorCode is not null.

        Examples:

        //Get a valid public key
        var hkp = new PublicKey();
        hkp.get("F75BE4E6EF6E9DD203679E94E7F6FAD172EFEE3D", function(publicKey, errorCode){
            errorCode !== null ? console.log(errorCode) : console.log(publicKey);
        });

        //Try to get an invalid public key
        var hkp = new PublicKey();
        hkp.get("bogus_id", function(publicKey, errorCode){
            errorCode !== null ? console.log(errorCode) : console.log(publicKey);
        });
    */
    PublicKey.prototype.get = function(keyId, callback, keyserverIndex, err){
        //default starting point is at the first keyserver
        if(keyserverIndex === undefined){
            keyserverIndex = 0;
        }

        //no more keyservers to check, so no key found
        if(keyserverIndex >= this.keyservers.length){
            return callback(null, err || 404);
        }

        //set the keyserver to try next
        var ks = this.keyservers[keyserverIndex];
        var _this = this;

        //special case for keybase
        if(ks.indexOf("https://keybase.io/") === 0){

            //don't need 0x prefix for keybase searches
            if(keyId.indexOf("0x") === 0){
                keyId = keyId.substr(2);
            }

            //request the public key from keybase
            var xhr = new XMLHttpRequest();
            xhr.open("get", "https://keybase.io/_/api/1.0/user/lookup.json" +
                "?fields=public_keys&key_fingerprint=" + keyId);
            xhr.onload = function(){
                if(xhr.status === 200){
                    var result = JSON.parse(xhr.responseText);

                    //keybase error returns HTTP 200 status, which is silly
                    if(result['status']['code'] !== 0){
                        return _this.get(keyId, callback, keyserverIndex + 1, result['status']['code']);
                    }

                    //no public key found
                    if(result['them'].length === 0){
                        return _this.get(keyId, callback, keyserverIndex + 1, 404);
                    }

                    //found the public key
                    var publicKey = result['them'][0]['public_keys']['primary']['bundle'];
                    return callback(publicKey, null);
                }
                else{
                    return _this.get(keyId, callback, keyserverIndex + 1, xhr.status);
                }
            };
            xhr.send();
        }

        //normal HKP keyserver
        else{
            //add the 0x prefix if absent
            if(keyId.indexOf("0x") !== 0){
                keyId = "0x" + keyId;
            }

            //request the public key from the hkp server
            var xhr = new XMLHttpRequest();
            xhr.open("get", ks + "pks/lookup?op=get&options=mr&search=" + keyId);
            xhr.onload = function(){
                if(xhr.status === 200){
                    return callback(xhr.responseText, null);
                }
                else{
                    return _this.get(keyId, callback, keyserverIndex + 1, xhr.status);
                }
            };
            xhr.send();
        }
    };

    /*
        Search for a public key in the keyservers.

        Arguments:

        * query - String to search for (usually an email, name, or username).

        * callback - Function that is called when finished. Two arguments are
                passed to the callback: results and errorCode. results is an
                Array of users that were returned by the search. errorCode is
                the error code (either HTTP status code or keybase error code)
                returned by the last keyserver that was tried. If any results
                were found, errorCode is null. If no results are found, results
                is null and errorCode is not null.

        Examples:

        //Search for diafygi's key id
        var hkp = new PublicKey();
        hkp.search("diafygi", function(results, errorCode){
            errorCode !== null ? console.log(errorCode) : console.log(results);
        });

        //Search for a nonexistent key id
        var hkp = new PublicKey();
        hkp.search("doesntexist123", function(results, errorCode){
            errorCode !== null ? console.log(errorCode) : console.log(results);
        });
    */
    PublicKey.prototype.search = function(query, callback, keyserverIndex, results, err){
        //default starting point is at the first keyserver
        if(keyserverIndex === undefined){
            keyserverIndex = 0;
        }

        //initialize the results array
        if(results === undefined){
            results = [];
        }

        //no more keyservers to check
        if(keyserverIndex >= this.keyservers.length){

            //return error if no results
            if(results.length === 0){
                return callback(null, err || 404);
            }

            //return results
            else{

                //merge duplicates
                var merged = {};
                for(var i = 0; i < results.length; i++){
                    var k = results[i];

                    //see if there's duplicate key ids to merge
                    if(merged[k['keyid']] !== undefined){

                        for(var u = 0; u < k['uids'].length; u++){
                            var has_this_uid = false;

                            for(var m = 0; m < merged[k['keyid']]['uids'].length; m++){
                                if(merged[k['keyid']]['uids'][m]['uid'] === k['uids'][u]){
                                    has_this_uid = true;
                                    break;
                                }
                            }

                            if(!has_this_uid){
                                merged[k['keyid']]['uids'].push(k['uids'][u])
                            }
                        }
                    }

                    //no duplicate found, so add it to the dict
                    else{
                        merged[k['keyid']] = k;
                    }
                }

                //return a list of the merged results in the same order
                var merged_list = [];
                for(var i = 0; i < results.length; i++){
                    var k = results[i];
                    if(merged[k['keyid']] !== undefined){
                        merged_list.push(merged[k['keyid']]);
                        delete(merged[k['keyid']]);
                    }
                }
                return callback(merged_list, null);
            }
        }

        //set the keyserver to try next
        var ks = this.keyservers[keyserverIndex];
        var _this = this;

        //special case for keybase
        if(ks.indexOf("https://keybase.io/") === 0){

            //request a list of users from keybase
            var xhr = new XMLHttpRequest();
            xhr.open("get", "https://keybase.io/_/api/1.0/user/autocomplete.json?q=" + encodeURIComponent(query));
            xhr.onload = function(){
                if(xhr.status === 200){
                    var kb_json = JSON.parse(xhr.responseText);

                    //keybase error returns HTTP 200 status, which is silly
                    if(kb_json['status']['code'] !== 0){
                        return _this.search(query, callback, keyserverIndex + 1, results, kb_json['status']['code']);
                    }

                    //no public key found
                    if(kb_json['completions'].length === 0){
                        return _this.search(query, callback, keyserverIndex + 1, results, 404);
                    }

                    //compose keybase user results
                    var kb_results = [];
                    for(var i = 0; i < kb_json['completions'].length; i++){
                        var user = kb_json['completions'][i]['components'];

                        //skip if no public key fingerprint
                        if(user['key_fingerprint'] === undefined){
                            continue;
                        }

                        //build keybase user result
                        var kb_result = {
                            "keyid": user['key_fingerprint']['val'].toUpperCase(),
                            "href": "https://keybase.io/" + user['username']['val'] + "/key.asc",
                            "info": "https://keybase.io/" + user['username']['val'],
                            "algo": user['key_fingerprint']['algo'],
                            "keylen": user['key_fingerprint']['nbits'],
                            "creationdate": null,
                            "expirationdate": null,
                            "revoked": false,
                            "disabled": false,
                            "expired": false,
                            "uids": [{
                                "uid": user['username']['val'] +
                                    " on Keybase <https://keybase.io/" +
                                    user['username']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            }]
                        };

                        //add full name
                        if(user['full_name'] !== undefined){
                            kb_result['uids'].push({
                                "uid": "Full Name: " + user['full_name']['val'],
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add twitter
                        if(user['twitter'] !== undefined){
                            kb_result['uids'].push({
                                "uid": user['twitter']['val'] +
                                    " on Twitter <https://twitter.com/" +
                                    user['twitter']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add github
                        if(user['github'] !== undefined){
                            kb_result['uids'].push({
                                "uid": user['github']['val'] +
                                    " on Github <https://github.com/" +
                                    user['github']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add reddit
                        if(user['reddit'] !== undefined){
                            kb_result['uids'].push({
                                "uid": user['reddit']['val'] +
                                    " on Github <https://reddit.com/u/" +
                                    user['reddit']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add hackernews
                        if(user['hackernews'] !== undefined){
                            kb_result['uids'].push({
                                "uid": user['hackernews']['val'] +
                                    " on Hacker News <https://news.ycombinator.com/user?id=" +
                                    user['hackernews']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add coinbase
                        if(user['coinbase'] !== undefined){
                            kb_result['uids'].push({
                                "uid": user['coinbase']['val'] +
                                    " on Coinbase <https://www.coinbase.com/" +
                                    user['coinbase']['val'] + ">",
                                "creationdate": null,
                                "expirationdate": null,
                                "revoked": false,
                                "disabled": false,
                                "expired": false,
                            });
                        }

                        //add websites
                        if(user['websites'] !== undefined){
                            for(var w = 0; w < user['websites'].length; w++){
                                kb_result['uids'].push({
                                    "uid": "Owns " + user['websites'][w]['val'],
                                    "creationdate": null,
                                    "expirationdate": null,
                                    "revoked": false,
                                    "disabled": false,
                                    "expired": false,
                                });
                            }
                        }

                        kb_results.push(kb_result);
                    }

                    results = results.concat(kb_results);
                    return _this.search(query, callback, keyserverIndex + 1, results, null);
                }
                else{
                    return _this.search(query, callback, keyserverIndex + 1, results, xhr.status);
                }
            };
            xhr.send();
        }

        //normal HKP keyserver
        else{
            var xhr = new XMLHttpRequest();
            xhr.open("get", ks + "pks/lookup?op=index&options=mr&fingerprint=on&search=" + encodeURIComponent(query));
            xhr.onload = function(){
                if(xhr.status === 200){
                    var ks_results = [];
                    var raw = xhr.responseText.split("\n");
                    var curKey = undefined;
                    for(var i = 0; i < raw.length; i++){
                        var line = raw[i].trim();

                        //pub:<keyid>:<algo>:<keylen>:<creationdate>:<expirationdate>:<flags>
                        if(line.indexOf("pub:") == 0){
                            if(curKey !== undefined){
                                ks_results.push(curKey);
                            }
                            var vals = line.split(":");
                            curKey = {
                                "keyid": vals[1],
                                "href": ks + "pks/lookup?op=get&options=mr&search=0x" + vals[1],
                                "info": ks + "pks/lookup?op=vindex&search=0x" + vals[1],
                                "algo": vals[2] === "" ? null : parseInt(vals[2]),
                                "keylen": vals[3] === "" ? null : parseInt(vals[3]),
                                "creationdate": vals[4] === "" ? null : parseInt(vals[4]),
                                "expirationdate": vals[5] === "" ? null : parseInt(vals[5]),
                                "revoked": vals[6].indexOf("r") !== -1,
                                "disabled": vals[6].indexOf("d") !== -1,
                                "expired": vals[6].indexOf("e") !== -1,
                                "uids": [],
                            }
                        }

                        //uid:<escaped uid string>:<creationdate>:<expirationdate>:<flags>
                        if(line.indexOf("uid:") == 0){
                            var vals = line.split(":");
                            curKey['uids'].push({
                                "uid": decodeURIComponent(vals[1]),
                                "creationdate": vals[2] === "" ? null : parseInt(vals[2]),
                                "expirationdate": vals[3] === "" ? null : parseInt(vals[3]),
                                "revoked": vals[4].indexOf("r") !== -1,
                                "disabled": vals[4].indexOf("d") !== -1,
                                "expired": vals[4].indexOf("e") !== -1,
                            });
                        }
                    }
                    ks_results.push(curKey);

                    results = results.concat(ks_results);
                    return _this.search(query, callback, keyserverIndex + 1, results, null);
                }
                else{
                    return _this.search(query, callback, keyserverIndex + 1, results, xhr.status);
                }
            };
            xhr.send();
        }
    };

    context.PublicKey = PublicKey;
})(typeof exports === "undefined" ? this : exports);
