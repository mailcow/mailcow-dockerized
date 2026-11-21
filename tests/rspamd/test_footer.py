import base64
from email import policy
from email.parser import BytesParser
from pathlib import Path
import unittest

from lupa import LuaRuntime

ROOT = Path(__file__).resolve().parents[2]
SOURCE = (ROOT / 'data/conf/rspamd/lua/rspamd.local.lua').read_text()
START = SOURCE.index('            local out_parts = {}', SOURCE.index('-- add footer'))
END = SOURCE.index('            task:set_message(out_parts)', START)
SERIALIZE = SOURCE[START:END] + '\nreturn table.concat(out_parts)'


def serialize(chunks, newline):
    lua = LuaRuntime()
    out = lua.table_from([lua.table_from(c) if isinstance(c, tuple) else c for c in chunks])
    return lua.execute('local out, newline_s = ...\n' + SERIALIZE, out, newline)


class FooterMimeTests(unittest.TestCase):
    def test_multiple_attachments(self):
        for newline in ('\r\n', '\n'):
            for terminated in (False, True):
                with self.subTest(newline=repr(newline), terminated=terminated):
                    pdf = b'%PDF-1.4\nfirst attachment'
                    other = b'second attachment\x00\xff'
                    chunks = ['MIME-Version: 1.0', 'Content-Type: multipart/mixed; boundary="test-boundary"', newline,
                              '--test-boundary', ('Content-Type: text/plain' + newline * 2 + 'Body with footer', True)]
                    for name, payload in [('one.pdf', pdf), ('two.bin', other)]:
                        chunks += ['--test-boundary',
                                   ('Content-Type: application/octet-stream' + newline +
                                    'Content-Disposition: attachment; filename="' + name + '"' + newline +
                                    'Content-Transfer-Encoding: base64' + newline * 2 +
                                    base64.b64encode(payload).decode() + (newline if terminated else ''), False)]
                    chunks += ['--test-boundary--']
                    result = serialize(chunks, newline)
                    parsed = BytesParser(policy=policy.default).parsebytes(result.encode())
                    attachments = list(parsed.iter_attachments())
                    self.assertEqual(len(attachments), 2, result)
                    self.assertEqual([p.get_payload(decode=True) for p in attachments], [pdf, other])
                    self.assertFalse(parsed.defects)
                    self.assertIn(newline + '--test-boundary--', result)
                    self.assertNotIn(base64.b64encode(pdf).decode() + newline * 2, result)

    def test_other_fragments_unchanged(self):
        self.assertEqual(serialize([('abc', False), ('def', False)], '\r\n'), 'abcdef')

    def test_existing_prefix_cleanup(self):
        self.assertEqual(serialize([('--\r\nContent-Type: text/plain', True)], '\r\n'),
                         'Content-Type: text/plain\r\n')

    def test_empty_fragment_preserves_line_state(self):
        self.assertEqual(serialize([('data', False), ('', False), '--end--'], '\n'), 'data\n--end--\n')

    def test_already_terminated_boundary(self):
        self.assertEqual(serialize([('data\r\n', False), '--end--'], '\r\n'), 'data\r\n--end--\r\n')


if __name__ == '__main__':
    unittest.main()
