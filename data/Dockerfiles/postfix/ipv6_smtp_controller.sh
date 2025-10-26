#!/usr/bin/env bash
# ipv6_smtp_controller.sh
# IPv6 SMTP sending eligibility controller for mailcow Postfix
# This script determines whether IPv6 should be disabled for outgoing SMTP connections
# based on configuration parameters, rDNS validation, and Spamhaus blocklist checks.

# Color codes for logging
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
LIGHT_GREEN='\033[1;32m'
LIGHT_RED='\033[1;31m'
NC='\033[0m' # No Color

# Logging function
log_info() {
  echo -e "${YELLOW}[IPv6 SMTP Controller]${NC} $1"
}

log_success() {
  echo -e "${GREEN}[IPv6 SMTP Controller]${NC} $1"
}

log_error() {
  echo -e "${RED}[IPv6 SMTP Controller]${NC} $1"
}

log_warning() {
  echo -e "${LIGHT_RED}[IPv6 SMTP Controller]${NC} $1"
}

# Extract IPv6 addresses from the system
# Returns global scope IPv6 addresses suitable for SMTP sending
# This function integrates with the existing IPv6 detection logic
get_ipv6_addresses() {
  local ipv6_addresses=()
  
  # Check if IPv6 is available at all
  if [[ ! -f /proc/net/if_inet6 ]] || grep -qs '^1' /proc/sys/net/ipv6/conf/all/disable_ipv6 2>/dev/null; then
    log_info "IPv6 is not available or administratively disabled on the system"
    return 1
  fi
  
  # Get all global IPv6 addresses (excluding link-local and loopback)
  local all_addresses
  all_addresses=$(ip -6 addr show scope global 2>/dev/null | grep 'inet6' | awk '{print $2}' | cut -d'/' -f1)
  
  if [[ -z "$all_addresses" ]]; then
    log_info "No global IPv6 addresses found on the system"
    return 1
  fi
  
  # Filter out any remaining non-global addresses
  while IFS= read -r ipv6_addr; do
    # Skip empty lines
    [[ -z "$ipv6_addr" ]] && continue
    
    # Skip link-local (fe80::/10) and loopback (::1)
    if [[ "$ipv6_addr" =~ ^fe80: ]] || [[ "$ipv6_addr" =~ ^::1$ ]]; then
      continue
    fi
    
    # Skip ULA (Unique Local Addresses - fc00::/7) if desired
    # Uncomment the following line to skip ULA addresses:
    # [[ "$ipv6_addr" =~ ^f[cd] ]] && continue
    
    ipv6_addresses+=("$ipv6_addr")
  done <<< "$all_addresses"
  
  if [[ ${#ipv6_addresses[@]} -eq 0 ]]; then
    log_info "No suitable global IPv6 addresses found for SMTP sending"
    return 1
  fi
  
  # Export addresses for use by calling functions
  printf '%s\n' "${ipv6_addresses[@]}"
  return 0
}

# Check if IPv6 is supported and available for SMTP sending
# This function reuses logic from the existing ipv6_controller.sh
# Returns 0 if IPv6 is available, 1 otherwise
check_ipv6_availability() {
  log_info "Checking IPv6 availability on the system..."
  
  # Check 1: IPv6 kernel support and administrative status
  if [[ ! -f /proc/net/if_inet6 ]] || grep -qs '^1' /proc/sys/net/ipv6/conf/all/disable_ipv6 2>/dev/null; then
    log_info "IPv6 is not available or administratively disabled on the system"
    return 1
  fi
  
  # Check 2: Global IPv6 addresses
  if ! ip -6 addr show scope global 2>/dev/null | grep -q 'inet6'; then
    log_info "No global IPv6 addresses found on the system"
    return 1
  fi
  
  # Check 3: IPv6 default route (optional but recommended)
  if ! ip -6 route show default 2>/dev/null | grep -qE '^default'; then
    log_warning "No default IPv6 route found - IPv6 connectivity may be limited"
    # Don't fail here - we may still have working IPv6 without a default route
  fi
  
  log_success "IPv6 is available on the system"
  return 0
}

# Check rDNS resolution for IPv6 addresses
# Validates that IPv6 addresses resolve back to MAILCOW_HOSTNAME
# Returns 0 if rDNS is valid, 1 if invalid or check fails
check_rdns_resolution() {
  local ipv6_address="$1"
  local expected_hostname="${MAILCOW_HOSTNAME}"
  
  # Validate input parameters
  if [[ -z "$ipv6_address" ]]; then
    log_error "check_rdns_resolution: No IPv6 address provided"
    return 1
  fi
  
  if [[ -z "$expected_hostname" ]]; then
    log_error "check_rdns_resolution: MAILCOW_HOSTNAME is not set"
    return 1
  fi
  
  log_info "Checking rDNS for IPv6 address: $ipv6_address"
  log_info "Expected hostname: $expected_hostname"
  
  # Perform reverse DNS lookup with timeout
  # Use host command with timeout to prevent hanging
  local rdns_result
  local timeout_seconds=5
  
  # Try to resolve the IPv6 address to hostname
  if command -v timeout >/dev/null 2>&1; then
    rdns_result=$(timeout "$timeout_seconds" host -W "$timeout_seconds" "$ipv6_address" 2>&1)
  else
    # Fallback if timeout command is not available
    rdns_result=$(host -W "$timeout_seconds" "$ipv6_address" 2>&1)
  fi
  
  local host_exit_code=$?
  
  # Check if the command succeeded
  if [[ $host_exit_code -ne 0 ]]; then
    if [[ $host_exit_code -eq 124 ]] || [[ "$rdns_result" == *"timed out"* ]]; then
      log_warning "rDNS lookup timed out for $ipv6_address"
    else
      log_warning "rDNS lookup failed for $ipv6_address: $rdns_result"
    fi
    return 1
  fi
  
  # Parse the result to extract hostname
  # host command output format: "x.x.x.x.ip6.arpa domain name pointer hostname."
  local resolved_hostname
  resolved_hostname=$(echo "$rdns_result" | grep -i "domain name pointer" | awk '{print $NF}' | sed 's/\.$//')
  
  if [[ -z "$resolved_hostname" ]]; then
    log_warning "No rDNS record found for $ipv6_address"
    return 1
  fi
  
  log_info "Resolved hostname: $resolved_hostname"
  
  # Compare resolved hostname with expected hostname (case-insensitive)
  if [[ "${resolved_hostname,,}" == "${expected_hostname,,}" ]]; then
    log_success "rDNS validation passed: $resolved_hostname matches $expected_hostname"
    return 0
  else
    log_warning "rDNS validation failed: $resolved_hostname does not match $expected_hostname"
    return 1
  fi
}

# Check if IPv6 address is listed on Spamhaus blocklist
# Queries zen.spamhaus.org with optional DQS key authentication
# Returns 0 if NOT listed (clean), 1 if listed or check fails
check_spamhaus_listing() {
  local ipv6_address="$1"
  
  # Validate input parameter
  if [[ -z "$ipv6_address" ]]; then
    log_error "check_spamhaus_listing: No IPv6 address provided"
    return 1
  fi
  
  log_info "Checking Spamhaus blocklist for IPv6 address: $ipv6_address"
  
  # Convert IPv6 address to reverse DNS format for Spamhaus query
  # IPv6 addresses need to be converted to nibble format (reverse hex digits with dots)
  # Example: 2001:db8::1 becomes 1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2
  
  # Expand IPv6 address to full format first
  local expanded_ipv6
  if command -v python3 >/dev/null 2>&1; then
    expanded_ipv6=$(python3 -c "import ipaddress; print(ipaddress.IPv6Address('$ipv6_address').exploded)" 2>/dev/null)
  elif command -v python >/dev/null 2>&1; then
    expanded_ipv6=$(python -c "import ipaddress; print(ipaddress.IPv6Address('$ipv6_address').exploded)" 2>/dev/null)
  else
    log_warning "Python not available for IPv6 address expansion, skipping Spamhaus check"
    return 0  # Treat as not listed if we can't check
  fi
  
  if [[ -z "$expanded_ipv6" ]]; then
    log_warning "Failed to expand IPv6 address: $ipv6_address"
    return 0  # Treat as not listed if expansion fails
  fi
  
  # Convert expanded IPv6 to nibble format (reverse hex digits)
  # Remove colons and reverse the string, then add dots between each character
  local nibble_format
  nibble_format=$(echo "$expanded_ipv6" | tr -d ':' | rev | sed 's/./&./g' | sed 's/\.$//')
  
  if [[ -z "$nibble_format" ]]; then
    log_warning "Failed to convert IPv6 address to nibble format"
    return 0  # Treat as not listed if conversion fails
  fi
  
  # Determine which Spamhaus zone to query
  local spamhaus_zone
  if [[ -n "${SPAMHAUS_DQS_KEY}" ]]; then
    # Use authenticated DQS endpoint
    spamhaus_zone="${nibble_format}.${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net"
    log_info "Using authenticated Spamhaus DQS query"
  else
    # Use public endpoint
    spamhaus_zone="${nibble_format}.zen.spamhaus.org"
    log_info "Using public Spamhaus query (unauthenticated)"
  fi
  
  # Perform DNS query with timeout and retry logic
  local timeout_seconds=5
  local max_retries=2
  local retry_count=0
  local query_result
  local query_exit_code
  
  while [[ $retry_count -lt $max_retries ]]; do
    log_info "Querying Spamhaus (attempt $((retry_count + 1))/$max_retries): $spamhaus_zone"
    
    # Use host command to query the blocklist
    if command -v timeout >/dev/null 2>&1; then
      query_result=$(timeout "$timeout_seconds" host -W "$timeout_seconds" -t A "$spamhaus_zone" 2>&1)
    else
      query_result=$(host -W "$timeout_seconds" -t A "$spamhaus_zone" 2>&1)
    fi
    
    query_exit_code=$?
    
    # Check the result
    if [[ $query_exit_code -eq 0 ]]; then
      # Query succeeded - check if address is listed
      if echo "$query_result" | grep -q "has address"; then
        # Extract the return code (127.0.0.x format)
        local return_code
        return_code=$(echo "$query_result" | grep "has address" | awk '{print $NF}' | head -n1)
        
        log_warning "IPv6 address IS LISTED on Spamhaus: $ipv6_address (return code: $return_code)"
        return 1  # Listed - should disable IPv6
      else
        log_success "IPv6 address is NOT listed on Spamhaus: $ipv6_address"
        return 0  # Not listed - OK to use IPv6
      fi
    elif [[ $query_exit_code -eq 1 ]] || echo "$query_result" | grep -qi "NXDOMAIN\|not found"; then
      # NXDOMAIN means not listed - this is good
      log_success "IPv6 address is NOT listed on Spamhaus: $ipv6_address"
      return 0  # Not listed - OK to use IPv6
    elif [[ $query_exit_code -eq 124 ]] || echo "$query_result" | grep -qi "timed out"; then
      # Timeout - retry
      log_warning "Spamhaus query timed out (attempt $((retry_count + 1))/$max_retries)"
      retry_count=$((retry_count + 1))
      
      if [[ $retry_count -lt $max_retries ]]; then
        sleep 2  # Wait before retry
      fi
    else
      # Other error - could be rate limiting or API issue
      log_warning "Spamhaus query failed: $query_result"
      retry_count=$((retry_count + 1))
      
      if [[ $retry_count -lt $max_retries ]]; then
        sleep 2  # Wait before retry
      fi
    fi
  done
  
  # If we exhausted retries, treat as check failure
  # Default to allowing IPv6 (fail open) to avoid false positives
  log_warning "Spamhaus check failed after $max_retries attempts, treating as NOT listed (fail-open)"
  return 0  # Treat as not listed if check fails
}

# Main function to check IPv6 SMTP sending eligibility
# Sets DISABLE_IPV6_SMTP_SENDING environment variable based on checks
# Implements comprehensive decision logic with fallback mechanisms
check_ipv6_smtp_sending_eligibility() {
  echo "========================================================================"
  log_info "Starting IPv6 SMTP sending eligibility check..."
  log_info "Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo "========================================================================"
  
  # Initialize decision tracking variables
  local should_disable_ipv6=false
  local disable_reason=""
  local check_results=()
  
  # Track individual check outcomes for comprehensive logging
  local config_check_result="not_checked"
  local ipv6_availability_result="not_checked"
  local ipv6_addresses_result="not_checked"
  local rdns_check_result="not_checked"
  local spamhaus_check_result="not_checked"
  
  # ============================================================================
  # Check 1: Read configuration parameter (highest priority)
  # ============================================================================
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "CHECK 1: Configuration Parameter"
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  
  if [[ -n "${DISABLE_IPV6_SMTP_SENDING}" ]]; then
    log_info "DISABLE_IPV6_SMTP_SENDING is set to: '${DISABLE_IPV6_SMTP_SENDING}'"
    
    if [[ "${DISABLE_IPV6_SMTP_SENDING}" =~ ^([yY][eE][sS]|[yY]|[tT][rR][uU][eE]|1)$ ]]; then
      log_warning "IPv6 SMTP sending is EXPLICITLY DISABLED via configuration parameter"
      should_disable_ipv6=true
      disable_reason="Explicitly disabled in configuration (DISABLE_IPV6_SMTP_SENDING=${DISABLE_IPV6_SMTP_SENDING})"
      config_check_result="disabled_by_config"
      check_results+=("CONFIG: Explicitly disabled")
      
      # Export and return immediately - configuration override takes precedence
      export DISABLE_IPV6_SMTP_SENDING="true"
      
      echo "========================================================================"
      log_warning "FINAL DECISION: IPv6 SMTP sending DISABLED"
      log_warning "Reason: ${disable_reason}"
      log_info "All checks bypassed due to explicit configuration"
      echo "========================================================================"
      return 0
      
    elif [[ "${DISABLE_IPV6_SMTP_SENDING}" =~ ^([nN][oO]|[nN]|[fF][aA][lL][sS][eE]|0)$ ]]; then
      log_success "Configuration parameter explicitly ALLOWS IPv6 SMTP sending"
      config_check_result="enabled_by_config"
      check_results+=("CONFIG: Explicitly enabled, proceeding with validation checks")
    else
      log_warning "Invalid DISABLE_IPV6_SMTP_SENDING value: '${DISABLE_IPV6_SMTP_SENDING}'"
      log_warning "Valid values: yes/y/true/1 (disable) or no/n/false/0 (enable)"
      log_info "Treating invalid value as 'no' (IPv6 enabled) and continuing checks"
      config_check_result="invalid_value_treated_as_no"
      check_results+=("CONFIG: Invalid value, defaulting to enabled")
    fi
  else
    log_info "DISABLE_IPV6_SMTP_SENDING not set in configuration"
    log_info "Defaulting to 'no' (IPv6 enabled) - will perform validation checks"
    config_check_result="not_set_default_enabled"
    check_results+=("CONFIG: Not set, defaulting to enabled")
  fi
  
  # ============================================================================
  # Check 2: Verify IPv6 is available on the system
  # ============================================================================
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "CHECK 2: IPv6 System Availability"
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  
  if ! check_ipv6_availability; then
    log_warning "IPv6 is NOT available on the system"
    should_disable_ipv6=true
    disable_reason="IPv6 not available on system (no kernel support or administratively disabled)"
    ipv6_availability_result="not_available"
    check_results+=("AVAILABILITY: IPv6 not available on system")
    
    # Export and return - no point in further checks
    export DISABLE_IPV6_SMTP_SENDING="true"
    
    echo "========================================================================"
    log_warning "FINAL DECISION: IPv6 SMTP sending DISABLED"
    log_warning "Reason: ${disable_reason}"
    log_info "Remaining checks skipped (IPv6 not available)"
    echo "========================================================================"
    return 0
  fi
  
  log_success "IPv6 is available on the system"
  ipv6_availability_result="available"
  check_results+=("AVAILABILITY: IPv6 available")
  
  # ============================================================================
  # Check 3: Extract IPv6 addresses from the system
  # ============================================================================
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "CHECK 3: IPv6 Address Detection"
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  
  local ipv6_addresses
  ipv6_addresses=$(get_ipv6_addresses)
  local get_addresses_result=$?
  
  if [[ $get_addresses_result -ne 0 ]] || [[ -z "$ipv6_addresses" ]]; then
    log_warning "No suitable IPv6 addresses found for SMTP sending"
    should_disable_ipv6=true
    disable_reason="No global IPv6 addresses found on system"
    ipv6_addresses_result="no_addresses_found"
    check_results+=("ADDRESSES: No global IPv6 addresses found")
    
    # Export and return - no addresses to validate
    export DISABLE_IPV6_SMTP_SENDING="true"
    
    echo "========================================================================"
    log_warning "FINAL DECISION: IPv6 SMTP sending DISABLED"
    log_warning "Reason: ${disable_reason}"
    log_info "Remaining checks skipped (no IPv6 addresses)"
    echo "========================================================================"
    return 0
  fi
  
  # Count and display found addresses
  local ipv6_address_count
  ipv6_address_count=$(echo "$ipv6_addresses" | wc -l)
  log_success "Found ${ipv6_address_count} global IPv6 address(es) for validation:"
  
  while IFS= read -r ipv6_addr; do
    log_info "  ➜ $ipv6_addr"
  done <<< "$ipv6_addresses"
  
  ipv6_addresses_result="found_${ipv6_address_count}_addresses"
  check_results+=("ADDRESSES: Found ${ipv6_address_count} global IPv6 address(es)")
  
  # ============================================================================
  # Check 4: Validate rDNS for IPv6 addresses
  # ============================================================================
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "CHECK 4: Reverse DNS (rDNS) Validation"
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  
  local rdns_check_passed=false
  local rdns_check_attempted=false
  local rdns_passed_count=0
  local rdns_failed_count=0
  
  # Check rDNS for each IPv6 address
  while IFS= read -r ipv6_addr; do
    [[ -z "$ipv6_addr" ]] && continue
    
    rdns_check_attempted=true
    
    if check_rdns_resolution "$ipv6_addr"; then
      rdns_check_passed=true
      rdns_passed_count=$((rdns_passed_count + 1))
      log_success "✓ rDNS validation PASSED for: $ipv6_addr"
    else
      rdns_failed_count=$((rdns_failed_count + 1))
      log_warning "✗ rDNS validation FAILED for: $ipv6_addr"
    fi
  done <<< "$ipv6_addresses"
  
  # Evaluate rDNS check results
  if [[ "$rdns_check_attempted" == "true" ]]; then
    if [[ "$rdns_check_passed" == "false" ]]; then
      log_error "rDNS validation FAILED for ALL ${rdns_failed_count} IPv6 address(es)"
      log_error "This indicates improper reverse DNS configuration"
      should_disable_ipv6=true
      disable_reason="rDNS validation failed for all IPv6 addresses (${rdns_failed_count} failed)"
      rdns_check_result="all_failed"
      check_results+=("rDNS: FAILED for all ${rdns_failed_count} address(es)")
    else
      log_success "rDNS validation PASSED for ${rdns_passed_count} of ${ipv6_address_count} IPv6 address(es)"
      if [[ $rdns_failed_count -gt 0 ]]; then
        log_info "Note: ${rdns_failed_count} address(es) failed rDNS, but at least one passed"
      fi
      rdns_check_result="passed_${rdns_passed_count}_of_${ipv6_address_count}"
      check_results+=("rDNS: PASSED for ${rdns_passed_count}/${ipv6_address_count} address(es)")
    fi
  else
    log_warning "rDNS check was not attempted (no addresses to check)"
    rdns_check_result="not_attempted"
    check_results+=("rDNS: Not attempted")
  fi
  
  # ============================================================================
  # Check 5: Query Spamhaus blocklist for IPv6 addresses
  # ============================================================================
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  log_info "CHECK 5: Spamhaus Blocklist Validation"
  log_info "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  
  local spamhaus_check_passed=true
  local spamhaus_check_attempted=false
  local spamhaus_clean_count=0
  local spamhaus_listed_count=0
  
  # Check Spamhaus for each IPv6 address
  while IFS= read -r ipv6_addr; do
    [[ -z "$ipv6_addr" ]] && continue
    
    spamhaus_check_attempted=true
    
    # check_spamhaus_listing returns 0 if NOT listed (clean), 1 if listed
    if check_spamhaus_listing "$ipv6_addr"; then
      spamhaus_clean_count=$((spamhaus_clean_count + 1))
      log_success "✓ Spamhaus check PASSED (not listed): $ipv6_addr"
    else
      spamhaus_listed_count=$((spamhaus_listed_count + 1))
      log_error "✗ Spamhaus check FAILED (LISTED): $ipv6_addr"
      spamhaus_check_passed=false
      # Don't break - check all addresses for complete logging
    fi
  done <<< "$ipv6_addresses"
  
  # Evaluate Spamhaus check results
  if [[ "$spamhaus_check_attempted" == "true" ]]; then
    if [[ "$spamhaus_check_passed" == "false" ]]; then
      log_error "Spamhaus blocklist check FAILED: ${spamhaus_listed_count} address(es) are LISTED"
      log_error "This indicates the IPv6 address(es) have poor reputation"
      should_disable_ipv6=true
      
      # Update disable reason if not already set by rDNS
      if [[ -z "$disable_reason" ]]; then
        disable_reason="IPv6 address(es) listed on Spamhaus blocklist (${spamhaus_listed_count} listed)"
      else
        disable_reason="${disable_reason}; IPv6 address(es) listed on Spamhaus (${spamhaus_listed_count} listed)"
      fi
      
      spamhaus_check_result="listed_${spamhaus_listed_count}_of_${ipv6_address_count}"
      check_results+=("SPAMHAUS: FAILED - ${spamhaus_listed_count}/${ipv6_address_count} address(es) LISTED")
    else
      log_success "Spamhaus blocklist check PASSED for all ${spamhaus_clean_count} IPv6 address(es)"
      spamhaus_check_result="all_clean"
      check_results+=("SPAMHAUS: PASSED - all ${spamhaus_clean_count} address(es) clean")
    fi
  else
    log_warning "Spamhaus check was not attempted (no addresses to check)"
    spamhaus_check_result="not_attempted"
    check_results+=("SPAMHAUS: Not attempted")
  fi
  
  # ============================================================================
  # Final Decision Logic with Comprehensive Logging
  # ============================================================================
  echo "========================================================================"
  log_info "DECISION SUMMARY"
  echo "========================================================================"
  
  # Log all check results
  log_info "Check Results:"
  for result in "${check_results[@]}"; do
    log_info "  • $result"
  done
  
  echo "------------------------------------------------------------------------"
  
  # Make final decision and export result
  if [[ "$should_disable_ipv6" == "true" ]]; then
    log_error "FINAL DECISION: IPv6 SMTP sending will be DISABLED"
    log_error "Reason: ${disable_reason}"
    export DISABLE_IPV6_SMTP_SENDING="true"
    
    # Log troubleshooting information
    echo "------------------------------------------------------------------------"
    log_info "Troubleshooting Information:"
    log_info "  • Configuration: ${config_check_result}"
    log_info "  • IPv6 Availability: ${ipv6_availability_result}"
    log_info "  • IPv6 Addresses: ${ipv6_addresses_result}"
    log_info "  • rDNS Validation: ${rdns_check_result}"
    log_info "  • Spamhaus Check: ${spamhaus_check_result}"
    
    if [[ "$rdns_check_result" == "all_failed" ]]; then
      echo "------------------------------------------------------------------------"
      log_info "rDNS Fix Recommendations:"
      log_info "  1. Ensure PTR records are configured for all IPv6 addresses"
      log_info "  2. Verify PTR records resolve to: ${MAILCOW_HOSTNAME}"
      log_info "  3. Check with your hosting provider or DNS administrator"
      log_info "  4. Test with: host <ipv6_address>"
    fi
    
    if [[ "$spamhaus_check_result" =~ ^listed_ ]]; then
      echo "------------------------------------------------------------------------"
      log_info "Spamhaus Listing Recommendations:"
      log_info "  1. Check listing details at: https://check.spamhaus.org/"
      log_info "  2. Follow Spamhaus delisting procedures if incorrectly listed"
      log_info "  3. Consider using different IPv6 addresses"
      log_info "  4. Review email sending practices and security"
    fi
    
  else
    log_success "FINAL DECISION: IPv6 SMTP sending will be ENABLED"
    log_success "All validation checks passed successfully"
    export DISABLE_IPV6_SMTP_SENDING="false"
    
    # Log success details
    echo "------------------------------------------------------------------------"
    log_info "Validation Summary:"
    log_info "  • Configuration: ${config_check_result}"
    log_info "  • IPv6 Availability: ${ipv6_availability_result}"
    log_info "  • IPv6 Addresses: ${ipv6_addresses_result}"
    log_info "  • rDNS Validation: ${rdns_check_result}"
    log_info "  • Spamhaus Check: ${spamhaus_check_result}"
  fi
  
  echo "========================================================================"
  log_info "IPv6 SMTP eligibility check completed at: $(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo "========================================================================"
  
  return 0
}
