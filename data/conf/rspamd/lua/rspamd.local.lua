-- PATCHED lua_mime.add_text_footer that fixes boundary ordering with attachments
-- This must be at the TOP of the file, before any rspamd_config symbols

local function newline(task)
  local t = task:get_newlines_type()
  if t == 'cr' then
    return '\r'
  elseif t == 'lf' then
    return '\n'
  end
  return '\r\n'
end

local function do_append_footer(task, part, footer, is_multipart, out, state)
  local rspamd_util = require "rspamd_util"
  local rspamd_text = require "rspamd_text"
  local tp = part:get_text()
  local ct = 'text/plain'
  local cte = 'quoted-printable'
  local newline_s = state.newline_s
  
  if tp:is_html() then
    ct = 'text/html'
  end
  
  local encode_func = function(input)
    return rspamd_util.encode_qp(input, 80, task:get_newlines_type())
  end
  
  if part:get_cte() == '7bit' then
    cte = '7bit'
    encode_func = function(input)
      if type(input) == 'userdata' then
        return input
      else
        return rspamd_text.fromstring(input)
      end
    end
  end
  
  if is_multipart then
    out[#out + 1] = string.format('Content-Type: %s; charset=utf-8%s' ..
        'Content-Transfer-Encoding: %s',
        ct, newline_s, cte)
    out[#out + 1] = ''
  else
    state.new_cte = cte
  end
  
  local content = tp:get_content('raw_utf') or ''
  local double_nline = newline_s .. newline_s
  local nlen = #double_nline
  
  if content:sub(-(nlen), nlen + 1) == double_nline then
    content = content:sub(0, -(#newline_s + 1)) .. footer
    out[#out + 1] = { encode_func(content), true }
    out[#out + 1] = ''
  else
    content = content .. footer
    out[#out + 1] = { encode_func(content), true }
    out[#out + 1] = ''
  end
end

local function add_text_footer_fixed(task, html_footer, text_footer)
  local rspamd_util = require "rspamd_util"
  local newline_s = newline(task)
  local state = {
    newline_s = newline_s
  }
  local out = {}
  local text_parts = task:get_text_parts()

  if not (html_footer or text_footer) or not (text_parts and #text_parts > 0) then
    return false
  end

  if html_footer or text_footer then
    local ct = task:get_header('Content-Type')
    if ct then
      ct = rspamd_util.parse_content_type(ct, task:get_mempool())
    end

    if ct then
      if ct.type and ct.type == 'text' then
        if ct.subtype then
          if html_footer and (ct.subtype == 'html' or ct.subtype == 'htm') then
            state.need_rewrite_ct = true
          elseif text_footer and ct.subtype == 'plain' then
            state.need_rewrite_ct = true
          end
        else
          if text_footer then
            state.need_rewrite_ct = true
          end
        end
        state.new_ct = ct
      end
    else
      if text_parts then
        if #text_parts == 1 then
          state.need_rewrite_ct = true
          state.new_ct = {
            type = 'text',
            subtype = 'plain'
          }
        elseif #text_parts > 1 then
          state.new_ct = {
            type = 'multipart',
            subtype = 'mixed'
          }
        end
      end
    end
  end

  local boundaries = {}
  local cur_boundary
  local parts = task:get_parts()
  
  local part_types = {}
  for i, part in ipairs(parts) do
    part_types[i] = {
      is_text = part:is_text(),
      is_attachment = part:is_attachment(),
      is_multipart = part:is_multipart(),
      is_message = part:is_message()
    }
  end
  
  for i, part in ipairs(parts) do
    local boundary = part:get_boundary()
    local part_ct = part:get_header('Content-Type')
    if part_ct then
      part_ct = rspamd_util.parse_content_type(part_ct, task:get_mempool())
    end
    
    if part:is_multipart() then
      if cur_boundary then
        out[#out + 1] = string.format('--%s', boundaries[#boundaries].boundary)
      end

      boundaries[#boundaries + 1] = {
        boundary = boundary or '--XXX',
        ct_type = part_ct.type or '',
        ct_subtype = part_ct.subtype or '',
      }
      cur_boundary = boundary

      local rh = part:get_raw_headers()
      if #rh > 0 then
        out[#out + 1] = { rh, true }
      end
      
    elseif part:is_message() then
      if boundary then
        if cur_boundary and boundary ~= cur_boundary then
          out[#out + 1] = string.format('--%s--%s', boundaries[#boundaries].boundary, newline_s)
          table.remove(boundaries)
          cur_boundary = nil
        end
        out[#out + 1] = string.format('--%s', boundary)
      end
      out[#out + 1] = { part:get_raw_headers(), true }
      
    else
      local append_footer = false
      local skip_footer = part:is_attachment()

      local parent = part:get_parent()
      if parent then
        local t, st = parent:get_type()
        if t == 'multipart' and st == 'signed' then
          skip_footer = true
        end
      end
      
      if text_footer and part:is_text() then
        local tp = part:get_text()
        if not tp:is_html() then
          append_footer = text_footer
        end
      end

      if html_footer and part:is_text() then
        local tp = part:get_text()
        if tp:is_html() then
          append_footer = html_footer
        end
      end

      if boundary then
        if cur_boundary and boundary ~= cur_boundary then
          local has_more_parts = false
          for j = i + 1, #parts do
            if not parts[j]:is_multipart() and not parts[j]:is_message() then
              has_more_parts = true
              break
            end
          end
          
          if #boundaries > 1 or (#boundaries == 1 and not has_more_parts) then
            out[#out + 1] = string.format('--%s--%s', boundaries[#boundaries].boundary, newline_s)
            
            if #boundaries > 1 and boundaries[#boundaries].ct_type == "multipart" and 
               boundaries[#boundaries].ct_subtype == "related" then
              out[#out + 1] = string.format('--%s--%s', boundaries[#boundaries - 1].boundary, newline_s)
              table.remove(boundaries)
            end
            table.remove(boundaries)
          end
          cur_boundary = boundary
        end
        out[#out + 1] = string.format('--%s', boundary)
      end

      if append_footer and not skip_footer then
        do_append_footer(task, part, append_footer, parent and parent:is_multipart(), out, state)
      else
        out[#out + 1] = { part:get_raw_headers(), true }
        out[#out + 1] = { part:get_raw_content(), false }
      end
    end
  end

  local b = table.remove(boundaries)
  while b do
    out[#out + 1] = string.format('--%s--', b.boundary)
    if #boundaries > 0 then
      out[#out + 1] = ''
    end
    b = table.remove(boundaries)
  end

  state.out = out
  return state
end

local lua_mime = require "lua_mime"
lua_mime.add_text_footer = add_text_footer_fixed

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
      redis_params,
      hash_key,
      false,
      smtp_access_cb,
      'HMGET',
      smtp_access_table
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
            task:set_pre_result('accept', 'ip matched with forward hosts', 'keep_spam')
          end
        end
      end
    end
    table.insert(ip_check_table, 1, 'KEEP_SPAM')
    local redis_ret_user = rspamd_redis_make_request(task,
      redis_params,
      'KEEP_SPAM',
      false,
      keep_spam_cb,
      'HMGET',
      ip_check_table
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
                redis_params,
                body,
                false,
                tag_callback_subfolder,
                'HGET',
                {'RCPT_WANTS_SUBFOLDER_TAG', body}
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
            redis_params,
            body,
            false,
            tag_callback_subject,
            'HGET',
            {'RCPT_WANTS_SUBJECT_TAG', body}
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
      return
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
        return
      end
      local email_content = tostring(task:get_content())
      email_content = string.gsub(email_content, "\r\n%.", "\r\n..")
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

    local from = task:get_from('smtp')
    if from then
      for _, a in ipairs(from) do
        table.insert(from_table, a['addr'])
        table.insert(from_table, '@' .. a['domain'])
      end
    else
      return
    end

    local rcpts = task:get_recipients('smtp')
    if rcpts then
      for _, a in ipairs(rcpts) do
        table.insert(rcpt_table, a['addr'])
        table.insert(rcpt_table, '@' .. a['domain'])
      end
    else
      return
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
    local envrcpt = task:get_recipients(1) or {}
    local uname = task:get_user()
    if not envfrom or not uname then
      return false
    end

    local uname = uname:lower()

    if #envrcpt == 1 and envrcpt[1].addr:lower() == uname then
      return false
    end

    local env_from_domain = envfrom[1].domain:lower()

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
          redis_params,
          env_from_domain,
          false,
          redis_key_cb_domain,
          'HGET',
          {'RL_VALUE', env_from_domain}
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
      redis_params,
      uname,
      false,
      redis_cb_user,
      'HGET',
      {'RL_VALUE', uname}
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

    local function newline(task)
      local t = task:get_newlines_type()

      if t == 'cr' then
        return '\r'
      elseif t == 'lf' then
        return '\n'
      end

      return '\r\n'
    end

    local function footer_cb(err_message, code, data, headers)
      if err or type(data) ~= 'string' then
        rspamd_logger.infox(rspamd_config, "domain wide footer request for user %s returned invalid or empty data (\"%s\") or error (\"%s\")", uname, data, err)
      else

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

            local replacements = {
              auth_user = uname,
              from_user = envfrom[1].user,
              from_name = from_name,
              from_addr = envfrom[1].addr,
              from_domain = envfrom[1].domain:lower()
            }

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

            local out = {}
            local rewrite = lua_mime.add_text_footer(task, footer.html, footer.plain) or {}

            local seen_cte
            local newline_s = newline(task)

            local function rewrite_ct_cb(name, hdr)
              if rewrite.need_rewrite_ct then
                if name:lower() == 'content-type' then
                  local boundary_part = rewrite.new_ct.boundary and
                    string.format('; boundary="%s"', rewrite.new_ct.boundary) or ''
                  local nct = string.format('%s: %s/%s; charset=utf-8%s',
                      'Content-Type', rewrite.new_ct.type, rewrite.new_ct.subtype, boundary_part)
                  out[#out + 1] = nct
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
