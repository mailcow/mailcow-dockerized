# SimpleLogin-Compatible API Implementation

## Overview
This implementation adds SimpleLogin-compatible API endpoints to mailcow, allowing third-party applications like Bitwarden and Vaultwarden to automatically create and manage email aliases.

## Files Added

1. **`data/web/simplelogin-api.php`** - Main API implementation
2. **`data/web/api/simplelogin-openapi.yaml`** - OpenAPI/Swagger documentation for the API

## API Endpoints

### Authentication
All endpoints require authentication via `X-API-Key` header using your mailcow API key.

### Endpoints

#### POST `/api/v1/alias/random/new`
Create a random alias.

**Request Body:**
```json
{
  "note": "Description of the alias",
  "name": "Display name"
}
```

**Response:**
```json
{
  "id": "YWxpYXNAZXhhbXBsZS5jb20=",
  "email": "random1234@example.com",
  "creation_date": "2026-03-16T12:30:00Z",
  "nb_block": 0,
  "nb_forward": 0,
  "nb_reply": 0,
  "enabled": true,
  "note": "Description",
  "name": "Display name",
  "pgp_enabled": false,
  "pgp_verified": false,
  "pinned": false,
  "custom": false
}
```

#### POST `/api/v1/alias/custom/new`
Create a custom alias with specific prefix.

**Request Body:**
```json
{
  "alias_prefix": "support",
  "note": "Customer support",
  "name": "Support Team"
}
```

#### GET `/api/v1/alias/{alias_id}`
Get information about a specific alias.

**Response:**
```json
{
  "id": "YWxpYXNAZXhhbXBsZS5jb20=",
  "email": "support@example.com",
  "enabled": true,
  ...
}
```

#### DELETE `/api/v1/alias/{alias_id}`
Delete an alias.

#### GET `/api/v1/domains`
List available domains for alias creation.

## Installation

1. Copy `simplelogin-api.php` to `data/web/` directory
2. Copy `simplelogin-openapi.yaml` to `data/web/api/` directory
3. Add nginx rewrite rule to route SimpleLogin API calls

## Nginx Configuration

Add the following to your mailcow nginx configuration:

```nginx
# SimpleLogin API compatibility
location ~ ^/api/v1/(alias|domains) {
    rewrite ^/api/v1/(.*)$ /simplelogin-api.php last;
}

location ~ ^/api/v1/alias/random/new$ {
    rewrite ^ /simplelogin-api.php last;
}

location ~ ^/api/v1/alias/custom/new$ {
    rewrite ^ /simplelogin-api.php last;
}

location ~ ^/api/v1/alias/options$ {
    rewrite ^ /simplelogin-api.php last;
}

location ~ ^/api/v1/alias/([A-Za-z0-9+/=]+)$ {
    rewrite ^/api/v1/alias/(.*)$ /simplelogin-api.php?query=$1 last;
}

location ~ ^/api/v1/domains$ {
    rewrite ^ /simplelogin-api.php last;
}
```

## Testing

```bash
# Test random alias creation
curl -X POST https://mail.example.com/api/v1/alias/random/new \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"note":"Test","name":"Test Alias"}'

# Test custom alias creation
curl -X POST https://mail.example.com/api/v1/alias/custom/new \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"alias_prefix":"support","note":"Support email"}'

# Get alias info
curl https://mail.example.com/api/v1/alias/YWxpYXNAZXhhbXBsZS5jb20= \
  -H "X-API-Key: YOUR_API_KEY"

# List domains
curl https://mail.example.com/api/v1/domains \
  -H "X-API-Key: YOUR_API_KEY"
```

## Bitwarden/Vaultwarden Integration

Once installed, configure Bitwarden/Vaultwarden to use this API:

1. Go to Settings > Generator > Forwarded Email
2. Select "SimpleLogin" or "Addy.io"
3. Set API URL: `https://mail.example.com/api/v1`
4. Enter your mailcow API token

## Error Codes

| Error | Description |
|-------|-------------|
| authentication_failed | Invalid or missing API key |
| bad_request | Invalid request parameters |
| alias_exists | Alias already exists |
| not_found | Alias not found |
| no_domain | No active domain available |
| creation_failed | Failed to create alias |

## Security

- All endpoints require valid API key authentication
- Users can only access their own aliases
- Alias IDs are base64 encoded
- Input validation on all parameters
- Rate limiting recommended in production

## Compatibility

This implementation is compatible with SimpleLogin API v1 and supports:
- ✅ Random alias creation
- ✅ Custom alias creation
- ✅ Alias information retrieval
- ✅ Alias deletion
- ✅ Domain listing
- ⚠️ PGP support (marked as disabled - requires additional mailcow config)
