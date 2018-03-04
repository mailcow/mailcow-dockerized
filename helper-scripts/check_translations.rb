#!/usr/bin/ruby

MASTER="en"

DIR = "#{__dir__}/.."

keys = %x[sed -r 's/.*(\\['.*'\\]\\['.*'\\]).*/\\1/g' #{DIR}/data/web/lang/lang.#{MASTER}.php | grep  '^\\\[' | sed 's/\\[/\\\\[/g' | sed 's/\\]/\\\\]/g'|sort | uniq]

not_used_in_php = []
keys.split("\n").each do |key|
  %x[git grep "#{key}" -- #{DIR}/data/web/*.php #{DIR}/data/web/inc #{DIR}/data/web/modals]
  if $?.exitstatus > 0
    not_used_in_php << key
  end
end

# \['user'\]\['username'\]
# \['user'\]\['waiting'\]
# \['warning'\]\['spam_alias_temp_error'\]

not_used = []
not_used_in_php.each do |string|
  section = string.scan(/([a-z]+)/)[0][0]
  key     = string.scan(/([a-z]+)/)[1][0]
  %x[git grep lang.#{key} -- #{DIR}/data/web/js/#{section}.js #{DIR}/data/web/js/debug.js]
  if $?.exitstatus > 0
    not_used << string
  end
end

puts "# Remove unused translation keys:"
not_used.each do |key|
  puts "sed -i \"/\\$lang#{key}.*;/d\" data/web/lang/lang.??.php"
end
