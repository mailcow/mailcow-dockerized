#!/usr/bin/ruby

require 'syslog/logger'
require 'time'
require 'mysql2'
require 'uri'
require 'yaml'

class AliasPolicyServer
  ALIAS_POLICY_ATTRIBUTES = ['sender', 'recipient', 'protocol_state'].freeze

  def initialize(config_file)
    @config = YAML.load_file(config_file)
    @log = Syslog::Logger.new("postfix/policy")
    @db_client = Mysql2::Client.new(
      host: @config['dbhost'],
      username: @config['dbuser'],
      password: @config['dbpass'],
      database: @config['dbname'],
      reconnect: true, # Reconnect if the connection is lost
      read_timeout: 5  # Avoid long-running queries
    )
    @log.info "Alias policy server started. Listening on port 10777 through Postfix."
  end

  def start
    while true; process_request end
  end

  private

  def db_fetch(query, params = [])
    stmt = @db_client.prepare(query)
    results = stmt.execute(*params).to_a
  rescue Mysql2::Error => e
    @log.error "Database error: #{e.message}"
    []
  ensure
    stmt&.close
  end

  def alias_policy(session_attributes)
    query = <<~SQL
      SELECT a.address, a.goto, a.private_comment AS policy, a.public_comment AS moderators
      FROM alias a
      INNER JOIN domain d ON a.domain = d.domain AND d.active = 1
      WHERE a.active = 1 AND islist = 1 AND address = ? LIMIT 1
    SQL
    results = db_fetch(query, [session_attributes['recipient']])
    return "DUNNO No alias record for #{session_attributes['recipient']} found." if results.empty?

    @policy = results[0]['policy'].downcase
    sender = session_attributes['sender']
    sender_domain = sender.split('@').last
    recipient = session_attributes['recipient']
    recipient_domain = recipient.split('@').last
    goto = results[0]['goto'].split(',')
    moderators = results[0]['moderators'].split(',').map(&:strip)

    case @policy
      when 'no_reply' "REJECT,INFO" 
      when 'domain' then sender_domain == recipient_domain ? "OK" : "REJECT"
      when 'members_only' then goto.include?(sender) ? "OK" : "REJECT"
      when 'moderators_only' then moderators.include?(sender) ? "OK" : "REJECT"
      when 'moderators_and_members_only' then (goto + moderators).include?(sender) ? "OK" : "REJECT"
      else "DUNNO Unknown policy, default to OK"
    end
  end

  def process_request
    session_attributes = {}
    # Read input lines until we encounter a blank line
    while (line = STDIN.gets&.chomp)
      session_attributes['start_time'] = Time.now if session_attributes.empty?
      key, value = line.split('=', 2) if line.include?('=')
      session_attributes[key] = value.downcase if ALIAS_POLICY_ATTRIBUTES.include?(key) && !value.empty?
      break if line.empty?
    end
    action = session_attributes['sender'] == 'watchdog@invalid' ? "OK,INFO Watchdog always allowed" : alias_policy(session_attributes)
    time_spent = format("[%.4fs]", Time.now - session_attributes['start_time'])
    @log.info("Alias access policy decision #{action}: #{policy.split('_').collect(&:capitalize).join(" ")} - #{session_attributes['protocol_state']} #{session_attributes['sender']} -> #{session_attributes['recipient']} time: #{time_spent}.")
    puts "action=#{action}\n\n"
    STDOUT.flush  # Ensure response is sent immediately
  end
end

# Start the server
config_file = '/opt/postfix/conf/policyserver_config.yml'
AliasPolicyServer.new(config_file).start