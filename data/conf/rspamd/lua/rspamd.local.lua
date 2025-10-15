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

-- KEEP_SPAM: IPv6 robust (RFC5952 canonical, single '::' variant, expanded)
rspamd_config:register_symbol({
  name = 'KEEP_SPAM',
  type = 'prefilter',
  priority = 1,
  callback = function(task)
    local rspamd_logger = require "rspamd_logger"
    local uname = task:get_user()
    -- Skip for authenticated mail
    if uname then return end

    local ip = task:get_from_ip()
    if not (ip and ip:is_valid()) then return end

    -- ---- IPv6 helpers -----------------------------------------------------
    local function strip_zone(s)
      -- remove "%eth0" etc.
      local p = s:find("%%", 1, true)
      if p then return s:sub(1, p-1) end
      return s
    end

    local function ipv6_to_parts(s)
      -- -> {p1..p8} as integers (0..65535), even if s contains "::"
      s = strip_zone(s)
      local left,right = s:match("^(.-)::(.-)$")
      local parts = {}
      if left then
        local lparts = {}
        if #left > 0 then for seg in left:gmatch("[^:]+") do table.insert(lparts, seg) end end
        local rparts = {}
        if #right > 0 then for seg in right:gmatch("[^:]+") do table.insert(rparts, seg) end end
        local missing = 8 - (#lparts + #rparts)
        for _,seg in ipairs(lparts) do table.insert(parts, tonumber(seg,16) or 0) end
        for _=1,missing do table.insert(parts, 0) end
        for _,seg in ipairs(rparts) do table.insert(parts, tonumber(seg,16) or 0) end
      else
        for seg in s:gmatch("[^:]+") do table.insert(parts, tonumber(seg,16) or 0) end
      end
      while #parts < 8 do table.insert(parts, 0) end
      return parts
    end

    local function parts_to_expanded(parts)
      -- 8 * "%04x" (fully expanded)
      local out = {}
      for i=1,8 do out[i] = string.format("%04x", parts[i]) end
      return table.concat(out, ":")
    end

    local function parts_to_unpadded(parts)
      -- 8 hextets, no leading zeros
      local out = {}
      for i=1,8 do
        out[i] = string.format("%x", parts[i])
      end
      return table.concat(out, ":")
    end

    local function parts_to_rfc5952(parts)
      -- RFC5952: compress the longest zero run (len >= 2) to "::",
      -- no leading zeros, no "::" for a run of length == 1
      local best_start, best_len = -1, 0
      local cur_start, cur_len = -1, 0
      for i=1,8 do
        if parts[i] == 0 then
          if cur_start == -1 then cur_start,cur_len = i,1 else cur_len = cur_len + 1 end
        else
          if cur_start ~= -1 and cur_len > best_len then
            best_start, best_len = cur_start, cur_len
          end
          cur_start,cur_len = -1,0
        end
      end
      if cur_start ~= -1 and cur_len > best_len then
        best_start, best_len = cur_start, cur_len
      end
      if best_len < 2 then
        -- no eligible "::" run -> unpadded 8-hextet form
        return parts_to_unpadded(parts)
      end
      local out = {}
      local i = 1
      while i <= 8 do
        if i == best_start then
          table.insert(out, "")
          table.insert(out, "")
          i = i + best_len
          if i == 9 then
            -- "::" at end
          elseif i == 1 then
            -- "::" at start
          end
        else
          table.insert(out, string.format("%x", parts[i]))
          if i < 8 and not (i+1 == best_start) then
            table.insert(out, ":")
          end
          i = i + 1
        end
      end
      local s = table.concat(out)
      -- fix edge cases for "::"
      s = s:gsub("^:%:", "::")
      s = s:gsub(":::+", "::")
      return s
    end

    local function make_single_zero_dc_variant(parts)
      -- Non-canonical variant: replace a SINGLE zero with "::"
      -- (e.g. 2a00:...:417:0:178:... -> 2a00:...:417::178:...)
      -- picks the FIRST single-zero position, if any
      local idx = nil
      for i=1,8 do
        if parts[i] == 0 then
          local left_zero  = (i>1)   and (parts[i-1] == 0)
          local right_zero = (i<8)   and (parts[i+1] == 0)
          if (not left_zero) and (not right_zero) then idx = i; break end
        end
      end
      if not idx then
        return nil
      end
      local out = {}
      for i=1,8 do
        if i == idx then
          table.insert(out, "")
          table.insert(out, "")
        else
          table.insert(out, string.format("%x", parts[i]))
          if i < 8 and i+1 ~= idx then table.insert(out, ":") end
        end
      end
      local s = table.concat(out)
      s = s:gsub("^:%:", "::")
      s = s:gsub(":::+", "::")
      return s
    end

    local function ipv6_notations(s)
      -- Returns: { rfc5952, singleZeroDC?, expanded, unpadded8 }
      -- unique, no duplicates
      local seen, out = {}, {}
      local function push(v)
        if v and v ~= "" and not seen[v] then seen[v]=true; table.insert(out, v) end
      end
      local parts = ipv6_to_parts(s)
      push(parts_to_rfc5952(parts))              -- canonical (e.g. ...:417:0:178:...)
      push(make_single_zero_dc_variant(parts))   -- non-canonical (e.g. ...:417::178:...)
      push(parts_to_expanded(parts))             -- expanded (0000 padding)
      push(parts_to_unpadded(parts))             -- unpadded 8-hextet (…:417:0:178:…)
      return out
    end
    -- -----------------------------------------------------------------------

    local redis_params = rspamd_parse_redis_server('keep_spam')

    local fields = {}
    local function push_unique(v)
      for _,x in ipairs(fields) do if x == v then return end end
      table.insert(fields, v)
    end

    local function add_all_notations_for(ip_obj, mask_bits)
      local s = ip_obj:to_string()
      if ip_obj:get_version() == 6 then
        for _,v in ipairs(ipv6_notations(s)) do
          if mask_bits then push_unique(string.format("%s/%d", v, mask_bits))
          else push_unique(v) end
        end
      else
        if mask_bits then push_unique(string.format("%s/%d", s, mask_bits))
        else push_unique(s) end
      end
    end

    local maxbits = (ip:get_version() == 4) and 32 or 128
    local minbits = (ip:get_version() == 4) and 8  or 32

    -- exact IP
    add_all_notations_for(ip, nil)
    -- prefix masks
    for i = maxbits, minbits, -1 do
      add_all_notations_for(ip:apply_mask(i), i)
    end

    local function keep_spam_cb(err, data)
      if err then
        rspamd_logger.infox(rspamd_config, "keep_spam redis error for %s: %s", ip, err)
        return
      end
      for idx, v in ipairs(data or {}) do
        if v == '1' then
          local hit = fields[idx] or '<unknown>'
          rspamd_logger.infox(rspamd_config, "KEEP_SPAM hit on %s", hit)
          task:insert_result('KEEP_SPAM', 0.0, hit)
          task:set_flag('no_stat')
          task:set_pre_result('accept', 'ip matched with forward hosts', 'keep_spam')
          return
        end
      end
    end

    -- HMGET: Hash "KEEP_SPAM", fields = all notations
    local args = {'KEEP_SPAM'}
    for _,f in ipairs(fields) do table.insert(args, f) end

    local ok = rspamd_redis_make_request(task,
      redis_params, 'KEEP_SPAM', false, keep_spam_cb, 'HMGET', args)

    if not ok then
      rspamd_logger.infox(rspamd_config, "cannot check keep_spam redis map")
    end
  end,
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
  type = 'idempotent',
  flags = {'empty', 'ignore_passthrough'},
  priority = 90,
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local rspamd_http = require "rspamd_http"
    local redis_params = rspamd_parse_redis_server('taghandler')

    local function remove_moo_tag()
      local h = task:get_header('X-Moo-Tag', false)
      if h then
        task:set_milter_reply({ remove_headers = {['X-Moo-Tag'] = 0} })
      end
    end

    local tagged_rcpt = task:get_symbol("TAGGED_RCPT")
    local mailcow_domain = task:get_symbol("RCPT_MAILCOW_DOMAIN")
    if not (tagged_rcpt and tagged_rcpt[1] and tagged_rcpt[1].options and mailcow_domain) then
      remove_moo_tag()
      return
    end

    local tag = tagged_rcpt[1].options[1]
    local keep_spam_hit = task:has_symbol('KEEP_SPAM')
    local action = task:get_metric_action('default')

    -- Always run when KEEP_SPAM matched; otherwise only for mild actions
    if (not keep_spam_hit) and (action ~= 'no action' and action ~= 'greylist' and action ~= 'add header' and action ~= 'rewrite subject') then
      remove_moo_tag()
      return
    end

    local rcpts = task:get_recipients('smtp')
    if not (rcpts and #rcpts == 1) then
      remove_moo_tag()
      return
    end

    local function tag_subject_or_header(addr_expanded)
      local function tag_callback_subject(err, data)
        if (not err) and type(data) == 'string' and data ~= '' then
          -- User wants Subject tag
          local sbj = task:get_header('Subject') or ''
          local new_sbj = '=?UTF-8?B?' .. tostring(util.encode_base64('[' .. tag .. '] ' .. sbj)) .. '?='
          task:set_milter_reply({
            remove_headers = { ['Subject'] = 1, ['X-Moo-Tag'] = 0 },
            add_headers    = { ['Subject'] = new_sbj }
          })
        else
          -- Fallback: Subfolder tag?
          local function tag_callback_subfolder(err2, data2)
            if (not err2) and type(data2) == 'string' and data2 ~= '' then
              task:set_milter_reply({ add_headers = {['X-Moo-Tag'] = 'YES'} })
            else
              remove_moo_tag()
            end
          end
          local ok2 = rspamd_redis_make_request(task, redis_params, addr_expanded, false,
            tag_callback_subfolder, 'HGET', {'RCPT_WANTS_SUBFOLDER_TAG', addr_expanded})
          if not ok2 then
            remove_moo_tag()
          end
        end
      end

      local ok1 = rspamd_redis_make_request(task, redis_params, addr_expanded, false,
        tag_callback_subject, 'HGET', {'RCPT_WANTS_SUBJECT_TAG', addr_expanded})
      if not ok1 then
        remove_moo_tag()
      end
    end

    local rcpt = rcpts[1]
    local rcpt_split = rspamd_str_split(rcpt['addr'], '@')
    if not (rcpt_split and #rcpt_split == 2) then
      remove_moo_tag(); return
    end
    if rcpt_split[1] == 'postmaster' then
      remove_moo_tag(); return
    end

    rspamd_http.request({
      task = task,
      url = 'http://nginx:8081/aliasexp.php',
      body = '',
      headers = {Rcpt = rcpt['addr']},
      callback = function(err_message, code, body, headers)
        if (not err_message) and code == 200 and body and body ~= "" then
          tag_subject_or_header(body)
        else
          remove_moo_tag()
        end
      end
    })

    -- IMPORTANT: no 'return true' here (avoid symbol logging)
  end,
})

rspamd_config:register_symbol({
  name = 'BCC',
  type = 'idempotent',
  flags = {'empty', 'ignore_passthrough'},
  priority = 95,
  callback = function(task)
    local rspamd_http = require "rspamd_http"
    local rspamd_logger = require "rspamd_logger"

    if task:has_symbol('ENCRYPTED_CHAT') then
      return
    end

    local from = task:get_from('smtp')
    local rcpts = task:get_recipients('smtp')
    if not (from and rcpts) then
      return
    end

    local from_table, rcpt_table = {}, {}
    for _, a in ipairs(from)  do table.insert(from_table,  a['addr']); table.insert(from_table,  '@' .. a['domain']) end
    for _, a in ipairs(rcpts) do table.insert(rcpt_table, a['addr']); table.insert(rcpt_table, '@' .. a['domain']) end

    local keep_spam_hit = task:has_symbol('KEEP_SPAM')
    local action = task:get_metric_action('default')

    local function may_send()
      return keep_spam_hit or action == 'no action' or action == 'add header' or action == 'rewrite subject'
    end

    local function send_mail(task, bcc_dest)
      if not bcc_dest or bcc_dest == '' then return end
      local lua_smtp = require "lua_smtp"
      local email_content = tostring(task:get_content() or '')
      email_content = string.gsub(email_content, "\r\n%.", "\r\n..") -- dot-stuffing
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
      }, email_content, function(ret, err)
        if not ret then
          rspamd_logger.errx(task, 'BCC SMTP ERROR: %s', err)
        else
          rspamd_logger.infox(rspamd_config, "BCC SMTP SUCCESS TO %s", bcc_dest)
        end
      end)
    end

    local function rcpt_callback(err_message, code, body, headers)
      if (not err_message) and code == 201 and body and may_send() then
        send_mail(task, body)
      end
    end
    local function from_callback(err_message, code, body, headers)
      if (not err_message) and code == 201 and body and may_send() then
        send_mail(task, body)
      end
    end

    for _, e in ipairs(rcpt_table) do
      rspamd_http.request({ task = task, url = 'http://nginx:8081/bcc.php', body = '', headers = {Rcpt = e}, callback = rcpt_callback })
    end
    for _, e in ipairs(from_table) do
      rspamd_http.request({ task = task, url = 'http://nginx:8081/bcc.php', body = '', headers = {From = e}, callback = from_callback })
    end

    -- IMPORTANT: no 'return true' here (avoid symbol logging)
  end,
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
  priority = 1
})
