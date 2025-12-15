rspamd_config.MAILCOW_AUTH = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

local monitoring_hosts = rspamd_config:add_map{
  url = "/etc/rspamd/custom/monitoring_nolog.map",
  description = "Monitoring hosts",
  type = "regexp"
}

rspamd_config:register_symbol({
  name = 'SMTP_ACCESS',
  type = 'postfilter',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local rspamd_ip = require 'rspamd_ip'
    local uname = task:get_user()
    local limited_access = task:get_symbol("SMTP_LIMITED_ACCESS")

    if not uname then
      return false
    end

    if not limited_access then
      return false
    end

    local hash_key = 'SMTP_ALLOW_NETS_' .. uname

    local redis_params = rspamd_parse_redis_server('smtp_access')
    local ip = task:get_from_ip()

    if ip == nil or not ip:is_valid() then
      return false
    end

    local from_ip_string = tostring(ip)
    smtp_access_table = {from_ip_string}

    local maxbits = 128
    local minbits = 32
    if ip:get_version() == 4 then
        maxbits = 32
        minbits = 8
    end
    for i=maxbits,minbits,-1 do
      local nip = ip:apply_mask(i):to_string() .. "/" .. i
      table.insert(smtp_access_table, nip)
    end
    local function smtp_access_cb(err, data)
      if err then
        rspamd_logger.infox(rspamd_config, "smtp_access query request for ip %s returned invalid or empty data (\"%s\") or error (\"%s\")", ip, data, err)
        return false
      else
        rspamd_logger.infox(rspamd_config, "checking ip %s for smtp_access in %s", from_ip_string, hash_key)
        for k,v in pairs(data) do
          if (v and v ~= userdata and v == '1') then
            rspamd_logger.infox(rspamd_config, "found ip in smtp_access map")
            task:insert_result(true, 'SMTP_ACCESS', 0.0, from_ip_string)
            return true
          end
        end
        rspamd_logger.infox(rspamd_config, "couldnt find ip in smtp_access map")
        task:insert_result(true, 'SMTP_ACCESS', 999.0, from_ip_string)
        return true
      end
    end
    table.insert(smtp_access_table, 1, hash_key)
    local redis_ret_user = rspamd_redis_make_request(task,
      redis_params, -- connect params
      hash_key, -- hash key
      false, -- is write
      smtp_access_cb, --callback
      'HMGET', -- command
      smtp_access_table -- arguments
    )
    if not redis_ret_user then
      rspamd_logger.infox(rspamd_config, "cannot check smtp_access redis map")
    end
  end,
  priority = 10
})

rspamd_config:register_symbol({
  name = 'POSTMASTER_HANDLER',
  type = 'prefilter',
  callback = function(task)
  local rcpts = task:get_recipients('smtp')
  local rspamd_logger = require "rspamd_logger"
  local lua_util = require "lua_util"
  local from = task:get_from(1)

  -- not applying to mails with more than one rcpt to avoid bypassing filters by addressing postmaster
  if rcpts and #rcpts == 1 then
    for _,rcpt in ipairs(rcpts) do
      local rcpt_split = rspamd_str_split(rcpt['addr'], '@')
      if #rcpt_split == 2 then
        if rcpt_split[1] == 'postmaster' then
          task:set_pre_result('accept', 'whitelisting postmaster smtp rcpt', 'postmaster')
          return
        end
      end
    end
  end

  if from then
    for _,fr in ipairs(from) do
      local fr_split = rspamd_str_split(fr['addr'], '@')
      if #fr_split == 2 then
        if fr_split[1] == 'postmaster' and task:get_user() then
          -- no whitelist, keep signatures
          task:insert_result(true, 'POSTMASTER_FROM', -2500.0)
          return
        end
      end
    end
  end

  end,
  priority = 10
})

rspamd_config:register_symbol({
  name = 'KEEP_SPAM',
  type = 'prefilter',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local rspamd_ip = require 'rspamd_ip'
    local uname = task:get_user()

    if uname then
      return false
    end

    local redis_params = rspamd_parse_redis_server('keep_spam')
    local ip = task:get_from_ip()

    if ip == nil or not ip:is_valid() then
      return false
    end

    -- Helper function to parse IPv6 into 8 segments
    local function ipv6_to_segments(ip_str)
      -- Remove zone identifier if present (e.g., %eth0)
      ip_str = ip_str:gsub("%%.*$", "")
      
      local segments = {}
      
      -- Handle :: compression
      if ip_str:find('::') then
        local before, after = ip_str:match('^(.*)::(.*)$')
        before = before or ''
        after = after or ''
        
        local before_parts = {}
        local after_parts = {}
        
        if before ~= '' then
          for seg in before:gmatch('[^:]+') do
            table.insert(before_parts, tonumber(seg, 16) or 0)
          end
        end
        
        if after ~= '' then
          for seg in after:gmatch('[^:]+') do
            table.insert(after_parts, tonumber(seg, 16) or 0)
          end
        end
        
        -- Add before segments
        for _, seg in ipairs(before_parts) do
          table.insert(segments, seg)
        end
        
        -- Add compressed zeros
        local zeros_needed = 8 - #before_parts - #after_parts
        for i = 1, zeros_needed do
          table.insert(segments, 0)
        end
        
        -- Add after segments
        for _, seg in ipairs(after_parts) do
          table.insert(segments, seg)
        end
      else
        -- No compression
        for seg in ip_str:gmatch('[^:]+') do
          table.insert(segments, tonumber(seg, 16) or 0)
        end
      end
      
      -- Ensure we have exactly 8 segments
      while #segments < 8 do
        table.insert(segments, 0)
      end
      
      return segments
    end

    -- Generate all common IPv6 notations
    local function get_ipv6_variants(ip_str)
      local variants = {}
      local seen = {}
      
      local function add_variant(v)
        if v and not seen[v] then
          table.insert(variants, v)
          seen[v] = true
        end
      end
      
      -- For IPv4, just return the original
      if not ip_str:find(':') then
        add_variant(ip_str)
        return variants
      end
      
      local segments = ipv6_to_segments(ip_str)
      
      -- 1. Fully expanded form (all zeros shown as 0000)
      local expanded_parts = {}
      for _, seg in ipairs(segments) do
        table.insert(expanded_parts, string.format('%04x', seg))
      end
      add_variant(table.concat(expanded_parts, ':'))
      
      -- 2. Standard form (no leading zeros, but all segments present)
      local standard_parts = {}
      for _, seg in ipairs(segments) do
        table.insert(standard_parts, string.format('%x', seg))
      end
      add_variant(table.concat(standard_parts, ':'))
      
      -- 3. Find all possible :: compressions
      -- RFC 5952: compress the longest run of consecutive zeros
      -- But we need to check all possibilities since Redis might have any form
      
      -- Find all zero runs
      local zero_runs = {}
      local in_run = false
      local run_start = 0
      local run_length = 0
      
      for i = 1, 8 do
        if segments[i] == 0 then
          if not in_run then
            in_run = true
            run_start = i
            run_length = 1
          else
            run_length = run_length + 1
          end
        else
          if in_run then
            if run_length >= 1 then  -- Allow single zero compression too
              table.insert(zero_runs, {start = run_start, length = run_length})
            end
            in_run = false
          end
        end
      end
      
      -- Don't forget the last run
      if in_run and run_length >= 1 then
        table.insert(zero_runs, {start = run_start, length = run_length})
      end
      
      -- Generate variant for each zero run compression
      for _, run in ipairs(zero_runs) do
        local parts = {}
        
        -- Before compression
        for i = 1, run.start - 1 do
          table.insert(parts, string.format('%x', segments[i]))
        end
        
        -- The compression
        if run.start == 1 then
          table.insert(parts, '')
          table.insert(parts, '')
        elseif run.start + run.length - 1 == 8 then
          table.insert(parts, '')
          table.insert(parts, '')
        else
          table.insert(parts, '')
        end
        
        -- After compression
        for i = run.start + run.length, 8 do
          table.insert(parts, string.format('%x', segments[i]))
        end
        
        local compressed = table.concat(parts, ':'):gsub('::+', '::')
        add_variant(compressed)
      end
      
      return variants
    end

    local from_ip_string = tostring(ip)
    local ip_check_table = {}
    
    -- Add all variants of the exact IP
    for _, variant in ipairs(get_ipv6_variants(from_ip_string)) do
      table.insert(ip_check_table, variant)
    end

    local maxbits = 128
    local minbits = 32
    if ip:get_version() == 4 then
        maxbits = 32
        minbits = 8
    end
    
    -- Add all CIDR notations with variants
    for i=maxbits,minbits,-1 do
      local masked_ip = ip:apply_mask(i)
      local cidr_base = masked_ip:to_string()
      
      for _, variant in ipairs(get_ipv6_variants(cidr_base)) do
        local cidr = variant .. "/" .. i
        table.insert(ip_check_table, cidr)
      end
    end
    
    local function keep_spam_cb(err, data)
      if err then
        rspamd_logger.infox(rspamd_config, "keep_spam query request for ip %s returned invalid or empty data (\"%s\") or error (\"%s\")", ip, data, err)
        return false
      else
        for k,v in pairs(data) do
          if (v and v ~= userdata and v == '1') then
            rspamd_logger.infox(rspamd_config, "found ip %s (checked as: %s) in keep_spam map, setting pre-result accept", from_ip_string, ip_check_table[k])
            task:set_pre_result('accept', 'ip matched with forward hosts', 'keep_spam')
            task:set_flag('no_stat')
            return
          end
        end
      end
    end
    
    table.insert(ip_check_table, 1, 'KEEP_SPAM')
    local redis_ret_user = rspamd_redis_make_request(task,
      redis_params, -- connect params
      'KEEP_SPAM', -- hash key
      false, -- is write
      keep_spam_cb, --callback
      'HMGET', -- command
      ip_check_table -- arguments
    )
    if not redis_ret_user then
      rspamd_logger.infox(rspamd_config, "cannot check keep_spam redis map")
    end
  end,
  priority = 19
})

rspamd_config:register_symbol({
  name = 'TLS_HEADER',
  type = 'postfilter',
  callback = function(task)
    local rspamd_logger = require "rspamd_logger"
    local tls_tag = task:get_request_header('TLS-Version')
    if type(tls_tag) == 'nil' then
      task:set_milter_reply({
        add_headers = {['X-Last-TLS-Session-Version'] = 'None'}
      })
    else
      task:set_milter_reply({
        add_headers = {['X-Last-TLS-Session-Version'] = tostring(tls_tag)}
      })
    end
  end,
  priority = 12
})

rspamd_config:register_symbol({
  name = 'TAG_MOO',
  type = 'postfilter',
  flags = 'ignore_passthrough',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local redis_params = rspamd_parse_redis_server('taghandler')
    local rspamd_http = require "rspamd_http"
    local rcpts = task:get_recipients('smtp')
    local lua_util = require "lua_util"

    local function remove_moo_tag()
      local moo_tag_header = task:get_header('X-Moo-Tag', false)
      if moo_tag_header then
        task:set_milter_reply({
          remove_headers = {['X-Moo-Tag'] = 0},
        })
      end
      return true
    end

    -- Check if we have exactly one recipient
    if not (rcpts and #rcpts == 1) then
      rspamd_logger.infox("TAG_MOO: not exactly one rcpt (%s), removing moo tag", rcpts and #rcpts or 0)
      remove_moo_tag()
      return
    end

    local rcpt_addr = rcpts[1]['addr']
    local rcpt_user = rcpts[1]['user']
    local rcpt_domain = rcpts[1]['domain']

    -- Check if recipient has a tag (contains '+')
    local tag = nil
    if rcpt_user:find('%+') then
      local base_user, tag_part = rcpt_user:match('^(.-)%+(.+)$')
      if base_user and tag_part then
        tag = tag_part
        rspamd_logger.infox("TAG_MOO: found tag in recipient: %s (base: %s, tag: %s)", rcpt_addr, base_user, tag)
      end
    end

    if not tag then
      rspamd_logger.infox("TAG_MOO: no tag found in recipient %s, removing moo tag", rcpt_addr)
      remove_moo_tag()
      return
    end

    -- Optional: Check if domain is a mailcow domain
    -- When KEEP_SPAM is active, RCPT_MAILCOW_DOMAIN might not be set
    -- If the mail is being delivered, we can assume it's valid
    local mailcow_domain = task:get_symbol("RCPT_MAILCOW_DOMAIN")
    if not mailcow_domain then
      rspamd_logger.infox("TAG_MOO: RCPT_MAILCOW_DOMAIN not set (possibly due to pre-result), proceeding anyway for domain %s", rcpt_domain)
    end

    local action = task:get_metric_action('default')
    rspamd_logger.infox("TAG_MOO: metric action: %s", action)

    -- Check if we have a pre-result (e.g., from KEEP_SPAM or POSTMASTER_HANDLER)
    local allow_processing = false
    
    if task.has_pre_result then
      local has_pre, pre_action = task:has_pre_result()
      if has_pre then
        rspamd_logger.infox("TAG_MOO: pre-result detected: %s", tostring(pre_action))
        if pre_action == 'accept' then
          allow_processing = true
          rspamd_logger.infox("TAG_MOO: pre-result is accept, will process")
        end
      end
    end

    -- Allow processing for mild actions or when we have pre-result accept
    if not allow_processing and action ~= 'no action' and action ~= 'greylist' then
      rspamd_logger.infox("TAG_MOO: skipping tag handler for action: %s", action)
      remove_moo_tag()
      return true
    end

    rspamd_logger.infox("TAG_MOO: processing allowed")

    local function http_callback(err_message, code, body, headers)
      if body ~= nil and body ~= "" then
        rspamd_logger.infox(rspamd_config, "TAG_MOO: expanding rcpt to \"%s\"", body)

        local function tag_callback_subject(err, data)
          if err or type(data) ~= 'string' or data == '' then
            rspamd_logger.infox(rspamd_config, "TAG_MOO: subject tag handler rcpt %s returned invalid or empty data (\"%s\") or error (\"%s\") - trying subfolder tag handler...", body, data, err)

            local function tag_callback_subfolder(err, data)
              if err or type(data) ~= 'string' or data == '' then
                rspamd_logger.infox(rspamd_config, "TAG_MOO: subfolder tag handler for rcpt %s returned invalid or empty data (\"%s\") or error (\"%s\")", body, data, err)
                remove_moo_tag()
              else
                rspamd_logger.infox("TAG_MOO: User wants subfolder tag, adding X-Moo-Tag header")
                task:set_milter_reply({
                  add_headers = {['X-Moo-Tag'] = 'YES'}
                })
              end
            end

            local redis_ret_subfolder = rspamd_redis_make_request(task,
              redis_params, -- connect params
              body, -- hash key
              false, -- is write
              tag_callback_subfolder, --callback
              'HGET', -- command
              {'RCPT_WANTS_SUBFOLDER_TAG', body} -- arguments
            )
            if not redis_ret_subfolder then
              rspamd_logger.infox(rspamd_config, "TAG_MOO: cannot make request to load tag handler for rcpt")
              remove_moo_tag()
            end

          else
            rspamd_logger.infox("TAG_MOO: user wants subject modified for tagged mail")
            local sbj = task:get_header('Subject') or ''
            new_sbj = '=?UTF-8?B?' .. tostring(util.encode_base64('[' .. tag .. '] ' .. sbj)) .. '?='
            task:set_milter_reply({
              remove_headers = {
                ['Subject'] = 1,
                ['X-Moo-Tag'] = 0
              },
              add_headers = {['Subject'] = new_sbj}
            })
          end
        end

        local redis_ret_subject = rspamd_redis_make_request(task,
          redis_params, -- connect params
          body, -- hash key
          false, -- is write
          tag_callback_subject, --callback
          'HGET', -- command
          {'RCPT_WANTS_SUBJECT_TAG', body} -- arguments
        )
        if not redis_ret_subject then
          rspamd_logger.infox(rspamd_config, "TAG_MOO: cannot make request to load tag handler for rcpt")
          remove_moo_tag()
        end
      else
        rspamd_logger.infox("TAG_MOO: alias expansion returned empty body")
        remove_moo_tag()
      end
    end

    local rcpt_split = rspamd_str_split(rcpt_addr, '@')
    if #rcpt_split == 2 then
      if rcpt_split[1]:match('^postmaster') then
        rspamd_logger.infox(rspamd_config, "TAG_MOO: not expanding postmaster alias")
        remove_moo_tag()
      else
        rspamd_logger.infox("TAG_MOO: requesting alias expansion for %s", rcpt_addr)
        rspamd_http.request({
          task=task,
          url='http://nginx:8081/aliasexp.php',
          body='',
          callback=http_callback,
          headers={Rcpt=rcpt_addr},
        })
      end
    else
      rspamd_logger.infox("TAG_MOO: invalid rcpt format")
      remove_moo_tag()
    end
  end,
  priority = 19
})

rspamd_config:register_symbol({
  name = 'BCC',
  type = 'postfilter',
  flags = 'ignore_passthrough',
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_http = require "rspamd_http"
    local rspamd_logger = require "rspamd_logger"

    local from_table = {}
    local rcpt_table = {}

    if task:has_symbol('ENCRYPTED_CHAT') then
      return -- stop
    end

    local send_mail = function(task, bcc_dest)
      local lua_smtp = require "lua_smtp"
      local function sendmail_cb(ret, err)
        if not ret then
          rspamd_logger.errx(task, 'BCC SMTP ERROR: %s', err)
        else
          rspamd_logger.infox(rspamd_config, "BCC SMTP SUCCESS TO %s", bcc_dest)
        end
      end
      if not bcc_dest then
        return -- stop
      end
      -- dot stuff content before sending
      local email_content = tostring(task:get_content())
      email_content = string.gsub(email_content, "\r\n%.", "\r\n..")
      -- send mail
      local from_smtp = task:get_from('smtp')
      local from_addr = (from_smtp and from_smtp[1] and from_smtp[1].addr) or 'mailer-daemon@localhost'
      lua_smtp.sendmail({
        task = task,
        host = os.getenv("IPV4_NETWORK") .. '.253',
        port = 591,
        from = from_addr,
        recipients = bcc_dest,
        helo = 'bcc',
        timeout = 20,
      }, email_content, sendmail_cb)
    end

    -- determine from
    local from = task:get_from('smtp')
    if from then
      for _, a in ipairs(from) do
        table.insert(from_table, a['addr']) -- add this rcpt to table
        table.insert(from_table, '@' .. a['domain']) -- add this rcpts domain to table
      end
    else
      return -- stop
    end

    -- determine rcpts
    local rcpts = task:get_recipients('smtp')
    if rcpts then
      for _, a in ipairs(rcpts) do
        table.insert(rcpt_table, a['addr']) -- add this rcpt to table
        table.insert(rcpt_table, '@' .. a['domain']) -- add this rcpts domain to table
      end
    else
      return -- stop
    end

    local action = task:get_metric_action('default')
    rspamd_logger.infox("BCC: metric action: %s", action)

    -- Check for pre-result accept (e.g., from KEEP_SPAM)
    local allow_bcc = false
    if task.has_pre_result then
      local has_pre, pre_action = task:has_pre_result()
      if has_pre and pre_action == 'accept' then
        allow_bcc = true
        rspamd_logger.infox("BCC: pre-result accept detected, will send BCC")
      end
    end

    -- Allow BCC for mild actions or when we have pre-result accept
    if not allow_bcc and action ~= 'no action' and action ~= 'add header' and action ~= 'rewrite subject' then
      rspamd_logger.infox("BCC: skipping for action: %s", action)
      return
    end

    local function rcpt_callback(err_message, code, body, headers)
      if err_message == nil and code == 201 and body ~= nil then
        rspamd_logger.infox("BCC: sending BCC to %s for rcpt match", body)
        send_mail(task, body)
      end
    end

    local function from_callback(err_message, code, body, headers)
      if err_message == nil and code == 201 and body ~= nil then
        rspamd_logger.infox("BCC: sending BCC to %s for from match", body)
        send_mail(task, body)
      end
    end

    if rcpt_table then
      for _,e in ipairs(rcpt_table) do
        rspamd_logger.infox(rspamd_config, "BCC: checking bcc for rcpt address %s", e)
        rspamd_http.request({
          task=task,
          url='http://nginx:8081/bcc.php',
          body='',
          callback=rcpt_callback,
          headers={Rcpt=e}
        })
      end
    end

    if from_table then
      for _,e in ipairs(from_table) do
        rspamd_logger.infox(rspamd_config, "BCC: checking bcc for from address %s", e)
        rspamd_http.request({
          task=task,
          url='http://nginx:8081/bcc.php',
          body='',
          callback=from_callback,
          headers={From=e}
        })
      end
    end

    -- Don't return true to avoid symbol being logged
  end,
  priority = 20
})

rspamd_config:register_symbol({
  name = 'DYN_RL_CHECK',
  type = 'prefilter',
  callback = function(task)
    local util = require("rspamd_util")
    local redis_params = rspamd_parse_redis_server('dyn_rl')
    local rspamd_logger = require "rspamd_logger"
    local envfrom = task:get_from(1)
    local envrcpt = task:get_recipients(1) or {}
    local uname = task:get_user()
    if not envfrom or not uname then
      return false
    end

    local uname = uname:lower()

    if #envrcpt == 1 and envrcpt[1].addr:lower() == uname then
      return false
    end

    local env_from_domain = envfrom[1].domain:lower() -- get smtp from domain in lower case

    local function redis_cb_user(err, data)

      if err or type(data) ~= 'string' then
        rspamd_logger.infox(rspamd_config, "dynamic ratelimit request for user %s returned invalid or empty data (\"%s\") or error (\"%s\") - trying dynamic ratelimit for domain...", uname, data, err)

        local function redis_key_cb_domain(err, data)
          if err or type(data) ~= 'string' then
            rspamd_logger.infox(rspamd_config, "dynamic ratelimit request for domain %s returned invalid or empty data (\"%s\") or error (\"%s\")", env_from_domain, data, err)
          else
            rspamd_logger.infox(rspamd_config, "found dynamic ratelimit in redis for domain %s with value %s", env_from_domain, data)
            task:insert_result('DYN_RL', 0.0, data, env_from_domain)
          end
        end

        local redis_ret_domain = rspamd_redis_make_request(task,
          redis_params, -- connect params
          env_from_domain, -- hash key
          false, -- is write
          redis_key_cb_domain, --callback
          'HGET', -- command
          {'RL_VALUE', env_from_domain} -- arguments
        )
        if not redis_ret_domain then
          rspamd_logger.infox(rspamd_config, "cannot make request to load ratelimit for domain")
        end
      else
        rspamd_logger.infox(rspamd_config, "found dynamic ratelimit in redis for user %s with value %s", uname, data)
        task:insert_result('DYN_RL', 0.0, data, uname)
      end

    end

    local redis_ret_user = rspamd_redis_make_request(task,
      redis_params, -- connect params
      uname, -- hash key
      false, -- is write
      redis_cb_user, --callback
      'HGET', -- command
      {'RL_VALUE', uname} -- arguments
    )
    if not redis_ret_user then
      rspamd_logger.infox(rspamd_config, "cannot make request to load ratelimit for user")
    end
    return true
  end,
  flags = 'empty',
  priority = 20
})

rspamd_config:register_symbol({
  name = 'NO_LOG_STAT',
  type = 'postfilter',
  callback = function(task)
    local from = task:get_header('From')
    if from and (monitoring_hosts:get_key(from) or from == "watchdog@localhost") then
      task:set_flag('no_log')
      task:set_flag('no_stat')
    end
  end
})

rspamd_config:register_symbol({
  name = 'MOO_FOOTER',
  type = 'prefilter',
  callback = function(task)
    local cjson = require "cjson"
    local lua_mime = require "lua_mime"
    local lua_util = require "lua_util"
    local rspamd_logger = require "rspamd_logger"
    local rspamd_http = require "rspamd_http"
    local envfrom = task:get_from(1)
    local uname = task:get_user()
    if not envfrom or not uname then
      return false
    end
    local uname = uname:lower()
    local env_from_domain = envfrom[1].domain:lower()
    local env_from_addr = envfrom[1].addr:lower()

    -- determine newline type
    local function newline(task)
      local t = task:get_newlines_type()

      if t == 'cr' then
        return '\r'
      elseif t == 'lf' then
        return '\n'
      end

      return '\r\n'
    end
    -- retrieve footer
    local function footer_cb(err_message, code, data, headers)
      if err or type(data) ~= 'string' then
        rspamd_logger.infox(rspamd_config, "domain wide footer request for user %s returned invalid or empty data (\"%s\") or error (\"%s\")", uname, data, err)
      else

        -- parse json string
        local footer = cjson.decode(data)
        if not footer then
          rspamd_logger.infox(rspamd_config, "parsing domain wide footer for user %s returned invalid or empty data (\"%s\") or error (\"%s\")", uname, data, err)
        else
          if footer and type(footer) == "table" and (footer.html and footer.html ~= "" or footer.plain and footer.plain ~= "")  then
            rspamd_logger.infox(rspamd_config, "found domain wide footer for user %s: html=%s, plain=%s, vars=%s", uname, footer.html, footer.plain, footer.vars)

            if footer.skip_replies ~= 0 then
              in_reply_to = task:get_header_raw('in-reply-to')
              if in_reply_to then
                rspamd_logger.infox(rspamd_config, "mail is a reply - skip footer")
                return
              end
            end

            local envfrom_mime = task:get_from(2)
            local from_name = ""
            if envfrom_mime and envfrom_mime[1].name then
              from_name = envfrom_mime[1].name
            elseif envfrom and envfrom[1].name then
              from_name = envfrom[1].name
            end

            -- default replacements
            local replacements = {
              auth_user = uname,
              from_user = envfrom[1].user,
              from_name = from_name,
              from_addr = envfrom[1].addr,
              from_domain = envfrom[1].domain:lower()
            }
            -- add custom mailbox attributes
            if footer.vars and type(footer.vars) == "string" then
              local footer_vars = cjson.decode(footer.vars)

              if type(footer_vars) == "table" then
                for key, value in pairs(footer_vars) do
                  replacements[key] = value
                end
              end
            end
            if footer.html and footer.html ~= "" then
              footer.html = lua_util.jinja_template(footer.html, replacements, true)
            end
            if footer.plain and footer.plain ~= "" then
              footer.plain = lua_util.jinja_template(footer.plain, replacements, true)
            end

            -- add footer
            local out = {}
            local rewrite = lua_mime.add_text_footer(task, footer.html, footer.plain) or {}

            local seen_cte
            local newline_s = newline(task)

            local function rewrite_ct_cb(name, hdr)
              if rewrite.need_rewrite_ct then
                if name:lower() == 'content-type' then
                  -- include boundary if present
                  local boundary_part = rewrite.new_ct.boundary and
                    string.format('; boundary="%s"', rewrite.new_ct.boundary) or ''
                  local nct = string.format('%s: %s/%s; charset=utf-8%s',
                      'Content-Type', rewrite.new_ct.type, rewrite.new_ct.subtype, boundary_part)
                  out[#out + 1] = nct
                  -- update Content-Type header (include boundary if present)
                  task:set_milter_reply({
                    remove_headers = {['Content-Type'] = 0},
                  })
                  task:set_milter_reply({
                    add_headers = {['Content-Type'] = string.format('%s/%s; charset=utf-8%s',
                      rewrite.new_ct.type, rewrite.new_ct.subtype, boundary_part)}
                  })
                  return
                elseif name:lower() == 'content-transfer-encoding' then
                  out[#out + 1] = string.format('%s: %s',
                      'Content-Transfer-Encoding', 'quoted-printable')
                  -- update Content-Transfer-Encoding header
                  task:set_milter_reply({
                    remove_headers = {['Content-Transfer-Encoding'] = 0},
                  })
                  task:set_milter_reply({
                    add_headers = {['Content-Transfer-Encoding'] = 'quoted-printable'}
                  })
                  seen_cte = true
                  return
                end
              end
              out[#out + 1] = hdr.raw:gsub('\r?\n?$', '')
            end

            task:headers_foreach(rewrite_ct_cb, {full = true})

            if not seen_cte and rewrite.need_rewrite_ct then
              out[#out + 1] = string.format('%s: %s', 'Content-Transfer-Encoding', 'quoted-printable')
            end

            -- End of headers
            out[#out + 1] = newline_s

            if rewrite.out then
              for _,o in ipairs(rewrite.out) do
                out[#out + 1] = o
              end
            else
              out[#out + 1] = task:get_rawbody()
            end
            local out_parts = {}
            for _,o in ipairs(out) do
              if type(o) ~= 'table' then
                out_parts[#out_parts + 1] = o
                out_parts[#out_parts + 1] = newline_s
              else
                local removePrefix = "--\x0D\x0AContent-Type"
                if string.lower(string.sub(tostring(o[1]), 1, string.len(removePrefix))) == string.lower(removePrefix) then
                  o[1] = string.sub(tostring(o[1]), string.len("--\x0D\x0A") + 1)
                end
                out_parts[#out_parts + 1] = o[1]
                if o[2] then
                  out_parts[#out_parts + 1] = newline_s
                end
              end
            end
            task:set_message(out_parts)
          else
            rspamd_logger.infox(rspamd_config, "domain wide footer request for user %s returned invalid or empty data (\"%s\")", uname, data)
          end
        end
      end
    end

    -- fetch footer
    rspamd_http.request({
      task=task,
      url='http://nginx:8081/footer.php',
      body='',
      callback=footer_cb,
      headers={Domain=env_from_domain,Username=uname,From=env_from_addr},
    })

    return true
  end,
  priority = 1
})