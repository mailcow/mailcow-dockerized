#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys
import os
import logging
import email
import email.mime.multipart
import email.mime.application
import email.encoders
import smtplib
import copy
from io import BytesIO

try:
    from configparser import SafeConfigParser  # Python 3
except ImportError:
    from ConfigParser import SafeConfigParser  # Python 2

import gpgme

# Boiler plate to avoid dependency on six
# BBB: Python 2.7 support
PY3K = sys.version_info > (3, 0)


def message_from_binary(message):
    if PY3K:
        return email.message_from_bytes(message)
    else:
        return email.message_from_string(message)


def as_binary_string(email):
    if PY3K:
        return email.as_bytes()
    else:
        return email.as_string()


def encode_string(string):
    if isinstance(string, bytes):
        return string
    else:
        return string.encode('utf-8')


__title__ = 'Zeyple'
__version__ = '1.2.0'
__author__ = 'Cédric Félizard'
__license__ = 'AGPLv3+'
__copyright__ = 'Copyright 2012-2016 Cédric Félizard'


class Zeyple:
    """Zeyple Encrypts Your Precious Log Emails"""

    def __init__(self, config_fname='zeyple.conf'):
        self.config = self.load_configuration(config_fname)

        log_file = self.config.get('zeyple', 'log_file')
        logging.basicConfig(
            filename=log_file, level=logging.DEBUG,
            format='%(asctime)s %(process)s %(levelname)s %(message)s'
        )
        logging.info("Zeyple ready to encrypt outgoing emails")

    def load_configuration(self, filename):
        """Reads and parses the config file"""

        config = SafeConfigParser()
        config.read([
            os.path.join('/etc/', filename),
            filename,
        ])
        if not config.sections():
            raise IOError('Cannot open config file.')
        return config

    @property
    def gpg(self):
        protocol = gpgme.PROTOCOL_OpenPGP

        if self.config.has_option('gpg', 'executable'):
            executable = self.config.get('gpg', 'executable')
        else:
            executable = None  # Default value

        home_dir = self.config.get('gpg', 'home')

        ctx = gpgme.Context()
        ctx.set_engine_info(protocol, executable, home_dir)
        ctx.armor = True

        return ctx

    def process_message(self, message_data, recipients):
        """Encrypts the message with recipient keys"""
        message_data = encode_string(message_data)

        in_message = message_from_binary(message_data)
        logging.info(
            "Processing outgoing message %s", in_message['Message-id'])

        if not recipients:
            logging.warn("Cannot find any recipients, ignoring")

        sent_messages = []
        for recipient in recipients:
            logging.info("Recipient: %s", recipient)

            key_id = self._user_key(recipient)
            logging.info("Key ID: %s", key_id)

            if key_id:
                out_message = self._encrypt_message(in_message, key_id)

                # Delete Content-Transfer-Encoding if present to default to
                # "7bit" otherwise Thunderbird seems to hang in some cases.
                del out_message["Content-Transfer-Encoding"]
            else:
                logging.warn("No keys found, message will be sent unencrypted")
                out_message = copy.copy(in_message)

            self._add_zeyple_header(out_message)
            self._send_message(out_message, recipient)
            sent_messages.append(out_message)

        return sent_messages

    def _get_version_part(self):
        ret = email.mime.application.MIMEApplication(
            'Version: 1\n',
            'pgp-encrypted',
            email.encoders.encode_noop,
        )
        ret.add_header(
            'Content-Description',
            "PGP/MIME version identification",
        )
        return ret

    def _get_encrypted_part(self, payload):
        ret = email.mime.application.MIMEApplication(
            payload,
            'octet-stream',
            email.encoders.encode_noop,
            name="encrypted.asc",
        )
        ret.add_header('Content-Description', "OpenPGP encrypted message")
        ret.add_header(
            'Content-Disposition',
            'inline',
            filename='encrypted.asc',
        )
        return ret

    def _encrypt_message(self, in_message, key_id):
        if in_message.is_multipart():
            # get the body (after the first \n\n)
            payload = in_message.as_string().split("\n\n", 1)[1].strip()

            # prepend the Content-Type including the boundary
            content_type = "Content-Type: " + in_message["Content-Type"]
            payload = content_type + "\n\n" + payload

            message = email.message.Message()
            message.set_payload(payload)

            payload = message.get_payload()

        else:
            payload = in_message.get_payload()
            payload = encode_string(payload)

            quoted_printable = email.charset.Charset('ascii')
            quoted_printable.body_encoding = email.charset.QP

            message = email.mime.nonmultipart.MIMENonMultipart(
                'text', 'plain', charset='utf-8'
            )
            message.set_payload(payload, charset=quoted_printable)

            mixed = email.mime.multipart.MIMEMultipart(
                'mixed',
                None,
                [message],
            )

            # remove superfluous header
            del mixed['MIME-Version']

            payload = as_binary_string(mixed)

        encrypted_payload = self._encrypt_payload(payload, [key_id])

        version = self._get_version_part()
        encrypted = self._get_encrypted_part(encrypted_payload)

        out_message = copy.copy(in_message)
        out_message.preamble = "This is an OpenPGP/MIME encrypted " \
                               "message (RFC 4880 and 3156)"

        if 'Content-Type' not in out_message:
            out_message['Content-Type'] = 'multipart/encrypted'
        else:
            out_message.replace_header(
                'Content-Type',
                'multipart/encrypted',
            )

        out_message.set_param('protocol', 'application/pgp-encrypted')
        out_message.set_payload([version, encrypted])

        return out_message

    def _encrypt_payload(self, payload, key_ids):
        """Encrypts the payload with the given keys"""
        payload = encode_string(payload)

        plaintext = BytesIO(payload)
        ciphertext = BytesIO()

        self.gpg.armor = True

        recipient = [self.gpg.get_key(key_id) for key_id in key_ids]

        self.gpg.encrypt(recipient, gpgme.ENCRYPT_ALWAYS_TRUST,
                         plaintext, ciphertext)

        return ciphertext.getvalue()

    def _user_key(self, email):
        """Returns the GPG key for the given email address"""
        logging.info("Trying to encrypt for %s", email)
        keys = [key for key in self.gpg.keylist(email)]

        if keys:
            key = keys.pop()  # NOTE: looks like keys[0] is the master key
            key_id = key.subkeys[0].keyid
            return key_id

        return None

    def _add_zeyple_header(self, message):
        if self.config.has_option('zeyple', 'add_header') and \
           self.config.getboolean('zeyple', 'add_header'):
            message.add_header(
                'X-Zeyple',
                "processed by {0} v{1}".format(__title__, __version__)
            )

    def _send_message(self, message, recipient):
        """Sends the given message through the SMTP relay"""
        logging.info("Sending message %s", message['Message-id'])

        smtp = smtplib.SMTP(self.config.get('relay', 'host'),
                            self.config.get('relay', 'port'))

        smtp.sendmail(message['From'], recipient, message.as_string())
        smtp.quit()

        logging.info("Message %s sent", message['Message-id'])


if __name__ == '__main__':
    recipients = sys.argv[1:]

    # BBB: Python 2.7 support
    binary_stdin = sys.stdin.buffer if PY3K else sys.stdin
    message = binary_stdin.read()

    zeyple = Zeyple()
    zeyple.process_message(message, recipients)
