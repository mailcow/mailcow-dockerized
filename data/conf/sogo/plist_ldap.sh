#!/bin/bash

domain="$1"
gal_status="$2"

echo '
                <!--
                <example>
                    <key>canAuthenticate</key>
                    <string>YES</string>
                    <key>id</key>
                    <string>'"${domain}_ldap"'</string>
                    <key>isAddressBook</key>
                    <string>'"${gal_status}"'</string>
                    <key>IDFieldName</key>
                    <string>mail</string>
                    <key>UIDFieldName</key>
                    <string>uid</string>
                    <key>bindFields</key>
                    <array>
                        <string>mail</string>
                    </array>
                    <key>type</key>
                    <string>ldap</string>
                    <key>bindDN</key>
                    <string>cn=admin,dc=example,dc=local</string>
                    <key>bindPassword</key>
                    <string>password</string>
                    <key>baseDN</key>
                    <string>ou=People,dc=example,dc=local</string>
                    <key>hostname</key>
                    <string>ldap://1.2.3.4:389</string>
                </example>
                -->'
