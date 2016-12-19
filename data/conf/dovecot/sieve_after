require "fileinto";
require "mailbox";
require "variables";
require "subaddress";
require "envelope";

if header :contains "X-Spam-Flag" "YES" {
  fileinto "Junk";
}
if envelope :detail :matches "to" "*" {
  fileinto :create "INBOX/${1}";
}
