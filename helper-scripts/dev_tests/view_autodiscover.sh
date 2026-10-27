#!/bin/bash

# Autodiscover XML Debug Script
# Usage: ./view_autodiscover.sh [OPTIONS] [email@domain.com]

# Function to display help
show_help() {
    cat << EOF
Autodiscover XML Debug Script

Usage: $0 [OPTIONS] [email@domain.com]

OPTIONS:
    -h, --help              Show this help message
    -d, --domain FQDN       Override autodiscover domain (default: autodiscover.DOMAIN)
                            Example: -d mail.example.com

EXAMPLES:
    $0 user@example.com
        Test autodiscover for user@example.com using autodiscover.example.com
    
    $0 -d mail.example.com user@example.com
        Test autodiscover for user@example.com using mail.example.com
    
    $0 -d localhost:8443 user@example.com
        Test autodiscover using localhost:8443 (useful for development)

EOF
    exit 0
}

# Initialize variables
EMAIL=""
DOMAIN_OVERRIDE=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            ;;
        -d|--domain)
            DOMAIN_OVERRIDE="$2"
            shift 2
            ;;
        -*)
            echo "Error: Unknown option $1"
            echo "Use -h or --help for usage information"
            exit 1
            ;;
        *)
            EMAIL="$1"
            shift
            ;;
    esac
done

# Check if xmllint is available
if ! command -v xmllint &> /dev/null; then
    echo "WARNING: xmllint not found. Output will not be formatted."
    echo "Install with: apt install libxml2-utils (Debian/Ubuntu) or yum install libxml2 (CentOS/RHEL)"
    echo ""
    USE_XMLLINT=false
else
    USE_XMLLINT=true
fi

# Get email address from user input if not provided
if [ -z "$EMAIL" ]; then
    read -p "Enter email address to test: " EMAIL
fi

# Validate email format
if [[ ! "$EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo "Error: Invalid email address format"
    exit 1
fi

# Extract domain from email
EMAIL_DOMAIN="${EMAIL#*@}"

# Determine autodiscover URL
if [ -n "$DOMAIN_OVERRIDE" ]; then
    AUTODISCOVER_URL="https://${DOMAIN_OVERRIDE}/Autodiscover/Autodiscover.xml"
    echo "Testing Autodiscover for: $EMAIL"
    echo "Override domain: $DOMAIN_OVERRIDE"
else
    AUTODISCOVER_URL="https://autodiscover.${EMAIL_DOMAIN}/Autodiscover/Autodiscover.xml"
    echo "Testing Autodiscover for: $EMAIL"
fi

echo "URL: $AUTODISCOVER_URL"
echo "============================================"
echo ""

# Make the request
RESPONSE=$(curl -k -s -X POST "$AUTODISCOVER_URL" \
  -H "Content-Type: text/xml" \
  -d "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<Autodiscover xmlns=\"http://schemas.microsoft.com/exchange/autodiscover/request/2006\">
  <Request>
    <EMailAddress>$EMAIL</EMailAddress>
    <AcceptableResponseSchema>http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a</AcceptableResponseSchema>
  </Request>
</Autodiscover>")

# Check if response is empty
if [ -z "$RESPONSE" ]; then
    echo "Error: No response received from server"
    exit 1
fi

# Format and display output
if [ "$USE_XMLLINT" = true ]; then
    echo "$RESPONSE" | xmllint --format - 2>&1
else
    echo "$RESPONSE"
fi

echo ""
echo "============================================"
echo "Response length: ${#RESPONSE} bytes"
