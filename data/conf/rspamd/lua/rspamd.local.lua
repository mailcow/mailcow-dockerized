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
          task:set_pre_result('accept', 'whitelisting postmaster smtp rcpt')
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

    local from_ip_string = tostring(ip)
    ip_check_table = {from_ip_string}

    local maxbits = 128
    local minbits = 32
    if ip:get_version() == 4 then
        maxbits = 32
        minbits = 8
    end
    for i=maxbits,minbits,-1 do
      local nip = ip:apply_mask(i):to_string() .. "/" .. i
      table.insert(ip_check_table, nip)
    end
    local function keep_spam_cb(err, data)
      if err then
        rspamd_logger.infox(rspamd_config, "keep_spam query request for ip %s returned invalid or empty data (\"%s\") or error (\"%s\")", ip, data, err)
        return false
      else
        for k,v in pairs(data) do
          if (v and v ~= userdata and v == '1') then
            rspamd_logger.infox(rspamd_config, "found ip in keep_spam map, setting pre-result")
            task:set_pre_result('accept', 'ip matched with forward hosts')
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
  callback = function(task)
    local util = require("rspamd_util")
    local rspamd_logger = require "rspamd_logger"
    local redis_params = rspamd_parse_redis_server('taghandler')
    local rspamd_http = require "rspamd_http"
    local rcpts = task:get_recipients('smtp')
    local lua_util = require "lua_util"

    local tagged_rcpt = task:get_symbol("TAGGED_RCPT")
    local mailcow_domain = task:get_symbol("RCPT_MAILCOW_DOMAIN")

    local function remove_moo_tag()
      local moo_tag_header = task:get_header('X-Moo-Tag', false)
      if moo_tag_header then
        task:set_milter_reply({
          remove_headers = {['X-Moo-Tag'] = 0},
        })
      end
      return true
    end

    if tagged_rcpt and tagged_rcpt[1].options and mailcow_domain then
      local tag = tagged_rcpt[1].options[1]
      rspamd_logger.infox("found tag: %s", tag)
      local action = task:get_metric_action('default')
      rspamd_logger.infox("metric action now: %s", action)

      if action ~= 'no action' and action ~= 'greylist' then
        rspamd_logger.infox("skipping tag handler for action: %s", action)
        remove_moo_tag()
        return true
      end

      local function http_callback(err_message, code, body, headers)
        if body ~= nil and body ~= "" then
          rspamd_logger.infox(rspamd_config, "expanding rcpt to \"%s\"", body)

          local function tag_callback_subject(err, data)
            if err or type(data) ~= 'string' then
              rspamd_logger.infox(rspamd_config, "subject tag handler rcpt %s returned invalid or empty data (\"%s\") or error (\"%s\") - trying subfolder tag handler...", body, data, err)

              local function tag_callback_subfolder(err, data)
                if err or type(data) ~= 'string' then
                  rspamd_logger.infox(rspamd_config, "subfolder tag handler for rcpt %s returned invalid or empty data (\"%s\") or error (\"%s\")", body, data, err)
                  remove_moo_tag()
                else
                  rspamd_logger.infox("Add X-Moo-Tag header")
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
                rspamd_logger.infox(rspamd_config, "cannot make request to load tag handler for rcpt")
                remove_moo_tag()
              end

            else
              rspamd_logger.infox("user wants subject modified for tagged mail")
              local sbj = task:get_header('Subject')
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
            rspamd_logger.infox(rspamd_config, "cannot make request to load tag handler for rcpt")
            remove_moo_tag()
          end

        end
      end

      if rcpts and #rcpts == 1 then
        for _,rcpt in ipairs(rcpts) do
          local rcpt_split = rspamd_str_split(rcpt['addr'], '@')
          if #rcpt_split == 2 then
            if rcpt_split[1] == 'postmaster' then
              rspamd_logger.infox(rspamd_config, "not expanding postmaster alias")
              remove_moo_tag()
            else
              rspamd_http.request({
                task=task,
                url='http://nginx:8081/aliasexp.php',
                body='',
                callback=http_callback,
                headers={Rcpt=rcpt['addr']},
              })
            end
          end
        end
      end
    else
      remove_moo_tag()
    end
  end,
  priority = 19
})

rspamd_config:register_symbol({
  name = 'BCC',
  type = 'postfilter',
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
      lua_smtp.sendmail({
        task = task,
        host = os.getenv("IPV4_NETWORK") .. '.253',
        port = 591,
        from = task:get_from(stp)[1].addr,
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
    rspamd_logger.infox("metric action now: %s", action)

    local function rcpt_callback(err_message, code, body, headers)
      if err_message == nil and code == 201 and body ~= nil then
        if action == 'no action' or action == 'add header' or action == 'rewrite subject' then
          send_mail(task, body)
        end
      end
    end

    local function from_callback(err_message, code, body, headers)
      if err_message == nil and code == 201 and body ~= nil then
        if action == 'no action' or action == 'add header' or action == 'rewrite subject' then
          send_mail(task, body)
        end
      end
    end

    if rcpt_table then
      for _,e in ipairs(rcpt_table) do
        rspamd_logger.infox(rspamd_config, "checking bcc for rcpt address %s", e)
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
        rspamd_logger.infox(rspamd_config, "checking bcc for from address %s", e)
        rspamd_http.request({
          task=task,
          url='http://nginx:8081/bcc.php',
          body='',
          callback=from_callback,
          headers={From=e}
        })
      end
    end

    return true
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
    local uname = task:get_user()
    if not envfrom or not uname then
      return false
    end
    local uname = uname:lower()

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
    local lua_mime = require "lua_mime"
    local lua_util = require "lua_util"
    local rspamd_logger = require "rspamd_logger"
    local rspamd_redis = require "rspamd_redis"
    local ucl = require "ucl"
    local redis_params = rspamd_parse_redis_server('footer')
    local envfrom = task:get_from(1)
    local uname = task:get_user()
    if not envfrom or not uname then
      return false
    end
    local uname = uname:lower()
    local env_from_domain = envfrom[1].domain:lower() -- get smtp from domain in lower case

    local function newline(task)
      local t = task:get_newlines_type()
    
      if t == 'cr' then
        return '\r'
      elseif t == 'lf' then
        return '\n'
      end
    
      return '\r\n'
    end
    local function redis_cb_footer(err, data)
      if err or type(data) ~= 'string' then
        rspamd_logger.infox(rspamd_config, "domain wide footer request for user %s returned invalid or empty data (\"%s\") or error (\"%s\")", uname, data, err)
      else
        -- parse json string
        local parser = ucl.parser()
        local res,err = parser:parse_string(data)
        if not res then
          rspamd_logger.infox(rspamd_config, "parsing domain wide footer for user %s returned invalid or empty data (\"%s\") or error (\"%s\")", uname, data, err)
        else
          local footer = parser:get_object()

          if footer and type(footer) == "table" and (footer.html or footer.plain) then
            rspamd_logger.infox(rspamd_config, "found domain wide footer for user %s: html=%s, plain=%s", uname, footer.html, footer.plain)

            local envfrom_mime = task:get_from(2)
            local from_name = ""
            if envfrom_mime and envfrom_mime[1].name then
              from_name = envfrom_mime[1].name
            elseif envfrom and envfrom[1].name then
              from_name = envfrom[1].name
            end

            local replacements = {
              auth_user = uname,
              from_user = envfrom[1].user,
              from_name = from_name,
              from_addr = envfrom[1].addr,
              from_domain = envfrom[1].domain:lower()
            }
            if footer.html then
              footer.html = lua_util.jinja_template(footer.html, replacements, true)
            end
            if footer.plain then
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
                  local nct = string.format('%s: %s/%s; charset=utf-8',
                      'Content-Type', rewrite.new_ct.type, rewrite.new_ct.subtype)
                  out[#out + 1] = nct
                  return
                elseif name:lower() == 'content-transfer-encoding' then
                  out[#out + 1] = string.format('%s: %s',
                      'Content-Transfer-Encoding', 'quoted-printable')
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

    local redis_ret_footer = rspamd_redis_make_request(task,
      redis_params, -- connect params
      env_from_domain, -- hash key
      false, -- is write
      redis_cb_footer, --callback
      'HGET', -- command
      {"DOMAIN_WIDE_FOOTER", env_from_domain} -- arguments
    )
    if not redis_ret_footer then
      rspamd_logger.infox(rspamd_config, "cannot make request to load footer for domain")
    end

    return true
  end,
  priority = 1
})
