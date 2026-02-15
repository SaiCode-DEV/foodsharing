# OAuth 2.0 / OpenID Connect Server

Foodsharing provides an OAuth 2.0 authorization server with OpenID Connect support for authenticating users and accessing their data.

## Features

- Authorization Code Grant with PKCE support
- OpenID Connect userinfo endpoint
- User consent management with "remember this choice" option
- JWT access tokens (1 hour) and refresh tokens (1 month)
- Region-based client restrictions

## Scopes

- **`openid`** - Required. Provides `sub` (user ID) and `preferred_username`
- **`profile`** - Name, picture, locale (always `de-DE`)
- **`email`** - Email address (always verified in foodsharing)
- **`regions`** - Arrays of region IDs: `region_ids` (member) and `region_ambassador` (coordinator)

## Endpoints

### Authorization
`GET|POST /oauth/authorize`

```
https://foodsharing.network/oauth/authorize?
  response_type=code&
  client_id=YOUR_CLIENT_ID&
  redirect_uri=https://your-app.com/callback&
  scope=openid%20profile%20email&
  state=RANDOM_STATE&
  code_challenge=CHALLENGE&
  code_challenge_method=S256
```

### Token  
`POST /oauth/token`

**Authorization code exchange:**
```bash
curl -X POST https://foodsharing.network/oauth/token \
  -d "grant_type=authorization_code" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "client_secret=YOUR_SECRET" \
  -d "code=AUTH_CODE" \
  -d "redirect_uri=https://your-app.com/callback" \
  -d "code_verifier=VERIFIER"
```

**Refresh token:**
```bash
curl -X POST https://foodsharing.network/oauth/token \
  -d "grant_type=refresh_token" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "client_secret=YOUR_SECRET" \
  -d "refresh_token=REFRESH_TOKEN"
```

### UserInfo
`GET|POST /oauth/userinfo`

```bash
curl https://foodsharing.network/oauth/userinfo \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

### Discovery
- `GET /.well-known/openid-configuration` - Provider metadata
- `GET /.well-known/jwks.json` - JWT signing keys

### Token Lifetimes
- Access tokens: **1 hour**
- Refresh tokens: **1 month**  
- Authorization codes: **10 minutes**

## UserInfo Response Examples

**Minimal (openid only):**
```json
{
  "sub": "12345",
  "preferred_username": "Max12345"
}
```

**With profile + email:**
```json
{
  "sub": "12345",
  "preferred_username": "Max12345",
  "name": "Max Mustermann",
  "given_name": "Max",
  "family_name": "Mustermann",
  "picture": "https://foodsharing.network/images/profile/12345.jpg",
  "locale": "de-DE",
  "email": "max@example.org",
  "email_verified": true
}
```

**With regions:**
```json
{
  "sub": "12345",
  "preferred_username": "Max12345",
  "name": "Max Mustermann",
  "email": "max@example.org",
  "email_verified": true,
  "region_ids": ["741", "1567", "1566"],
  "region_ambassador": ["1567"]
}
```

## Registering OAuth Clients

Go to `/admin/oauthclients` (Orga members only) and click "+ Neuen Client erstellen".

**Required fields:**
- **Client-ID**: Unique identifier (e.g., `bluespice-wiki`, `mobile-app`)
- **Name**: Displayed to users during consent
- **Confidential Client**: ✅ for server apps (have secret), ❌ for browser/mobile apps (use PKCE)
- **Redirect URIs**: Exact callback URLs (one per line, must match exactly)
- **Scopes**: Select `openid` + any combination of `profile`, `email`, `regions`
- **Grant Types**: Select `authorization_code` and optionally `refresh_token`

**Optional:**
- **Required Regions**: Restrict to users in specific regions (leave empty for global access)

After creating, **save the client secret immediately** - it won't be shown again.

## Setup

### Keys
OAuth keys are in `keys/` directory (auto-generated on first run):
- `oauth-private.key` - RSA private key for signing JWTs
- `oauth-public.key` - RSA public key for validation
- `oauth-encryption.key` - Encryption key (auto-generated)

To regenerate manually:
```bash
cd keys/
openssl genrsa -out oauth-private.key 4096
openssl rsa -in oauth-private.key -pubout -out oauth-public.key
chmod 600 oauth-private.key oauth-public.key
```

### Database
```bash
./scripts/db-migrate
```

### BlueSpice Integration
```bash
BLUESPICE="true" ./scripts/start
```

## Security Notes

- **Always HTTPS in production** (required)
- **PKCE required for public clients**, strongly recommended for all
- **Validate `state` parameter** to prevent CSRF
- **Redirect URIs must match exactly** (including trailing slash)
- **Store client secrets securely** (environment variables, never in code)
- **Access tokens are JWTs** - validate signature and expiration
- **Refresh tokens are encrypted** - store securely, revoke on logout

## Troubleshooting

**"Invalid redirect_uri"** - URI must match exactly (including protocol and trailing slash)

**"Code challenge required"** - Public clients must use PKCE (`code_challenge` + `code_verifier`)

**"User not in required regions"** - User must be member of at least one region specified in client settings

**"Invalid or expired code"** - Authorization codes expire in 10 minutes and can only be used once

## References

- [OAuth 2.0 RFC 6749](https://datatracker.ietf.org/doc/html/rfc6749)
- [PKCE RFC 7636](https://datatracker.ietf.org/doc/html/rfc7636)
- [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html)
- [OAuth 2.0 Playground](https://www.oauth.com/playground/) (for testing)
- [JWT.io](https://jwt.io/) (decode tokens)
