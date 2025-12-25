# OAuth 2.0 / OpenID Connect Server

Foodsharing provides an OAuth 2.0 authorization server with OpenID Connect (OIDC) support, allowing external applications to authenticate users and access their data with their permission.

## Features

- OAuth 2.0 Authorization Code Grant
- OpenID Connect support with userinfo endpoint
- Scope-based access control
- User consent management
- Secure JWT access tokens

## Available Scopes

- `openid` - OpenID Connect support (required for OIDC)
- `profile` - User profile information (name, picture, locale)
- `email` - User email address
- `regions` - List of regions the user is associated with

## Endpoints

- **Authorization**: `GET /oauth/authorize`
- **Token**: `POST /oauth/token`
- **UserInfo**: `GET /oauth/userinfo` (OIDC)

## Setup

### 1. Generate OAuth Keys

The OAuth keys should already be generated during setup. They are located in the `keys/` directory:
- `oauth-private.key` - RSA private key for signing JWTs
- `oauth-public.key` - RSA public key for validating JWTs
- `oauth-encryption.key` - Encryption key for authorization codes and refresh tokens (auto-generated)

If you need to regenerate them:

```bash
cd keys/
openssl genrsa -out oauth-private.key 4096
openssl rsa -in oauth-private.key -pubout -out oauth-public.key
chmod 600 oauth-private.key oauth-public.key
```

### 2. Run Database Migrations

Ensure the OAuth tables are created:

```bash
./scripts/docker-compose exec app bin/console phinx migrate
```

### 3. Register OAuth Clients

Use the command-line tool to manage OAuth clients:

#### List all clients:
```bash
./scripts/docker-compose exec app bin/console foodsharing:oauth-client list
```

#### Add a new client:
```bash
./scripts/docker-compose exec app bin/console foodsharing:oauth-client add \
  --identifier="my-app" \
  --name="My Application" \
  --redirect-uri="https://myapp.example.com/callback" \
  --confidential \
  --scopes="openid,profile,email" \
  --grant-types="authorization_code"
```

Interactive mode (prompts for all values):
```bash
./scripts/docker-compose exec app bin/console foodsharing:oauth-client add
```

**Important**: Save the client secret shown after creation - it won't be displayed again!

#### Remove a client:
```bash
./scripts/docker-compose exec app bin/console foodsharing:oauth-client remove --identifier="my-app"
```

## Bluespice usage

you can use 
```bash
BLUESPICE="true" ./scripts/start
```
to start foodsharing with bluespice. You will still need to configure oauth clients as described above.

## Client Configuration

### Confidential vs Public Clients

- **Confidential clients**: Server-side applications that can securely store the client secret (use `--confidential` flag)
- **Public clients**: Browser-based or mobile apps that cannot securely store secrets (omit `--confidential` flag)

### Redirect URIs

Specify one or more redirect URIs where users will be sent after authorization:
- Use `--redirect-uri` multiple times for multiple URIs
- Or provide a comma-separated list in interactive mode
- URIs must match exactly during authorization (including query parameters)

## Authorization Flow

### 1. Authorization Request

Direct users to the authorization endpoint:

```
GET /oauth/authorize?
  response_type=code&
  client_id=YOUR_CLIENT_ID&
  redirect_uri=YOUR_REDIRECT_URI&
  scope=openid+profile+email&
  state=RANDOM_STATE
```

Parameters:
- `response_type`: Must be `code`
- `client_id`: Your client identifier
- `redirect_uri`: One of your registered redirect URIs
- `scope`: Space-separated list of requested scopes
- `state`: Random string to prevent CSRF attacks

### 2. User Authorization

The user will be redirected to the Foodsharing login page (if not logged in), then shown an authorization page listing the requested permissions. They can approve or deny the request.

If they previously approved and chose "remember", they will be auto-approved.

### 3. Authorization Code

On approval, the user is redirected back to your redirect URI with an authorization code:

```
https://yourapp.example.com/callback?code=AUTHORIZATION_CODE&state=RANDOM_STATE
```

### 4. Token Exchange

Exchange the authorization code for access and refresh tokens:

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&
code=AUTHORIZATION_CODE&
redirect_uri=YOUR_REDIRECT_URI&
client_id=YOUR_CLIENT_ID&
client_secret=YOUR_CLIENT_SECRET
```

Response:
```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1Q...",
  "refresh_token": "def50200..."
}
```

### 5. Access User Data

Use the access token to request user information:

```bash
GET /oauth/userinfo
Authorization: Bearer ACCESS_TOKEN
```

Response (depends on granted scopes):
```json
{
  "sub": "12345",
  "name": "Max Mustermann",
  "given_name": "Max",
  "family_name": "Mustermann",
  "email": "max@example.com",
  "email_verified": true,
  "picture": "https://foodsharing.de/images/profile/12345.jpg",
  "locale": "de-DE",
  "regions": [
    {"id": 1, "name": "Berlin"},
    {"id": 2, "name": "Hamburg"}
  ]
}
```

### 6. Refresh Token

When the access token expires, use the refresh token:

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&
refresh_token=REFRESH_TOKEN&
client_id=YOUR_CLIENT_ID&
client_secret=YOUR_CLIENT_SECRET
```

## Token Lifetimes

- **Authorization Code**: 10 minutes
- **Access Token**: 1 hour
- **Refresh Token**: 1 month

## Security Considerations

1. **Always use HTTPS** in production
2. **Validate the state parameter** to prevent CSRF attacks
3. **Store client secrets securely** - never commit them to version control
4. **Validate redirect URIs** - they must match exactly
5. **Use PKCE** for public clients (mobile/SPA apps) - future enhancement
6. **Rotate refresh tokens** when possible

## Development/Testing

For local development, you can use `http://localhost` redirect URIs, but ensure your client is configured accordingly.

## Troubleshooting

### "Invalid client" error
- Verify the client_id exists: `./scripts/docker-compose exec app bin/console foodsharing:oauth-client list`
- Check that the client is active

### "Invalid redirect URI" error
- Ensure the redirect_uri in your request exactly matches one registered for the client
- URIs are case-sensitive and must include scheme, host, port, and path

### "Invalid scope" error
- Check that all requested scopes are in the list of supported scopes
- Verify the client is allowed to request those scopes

### Token validation fails
- Ensure OAuth keys exist and are readable
- Check that the public key matches the private key used to sign tokens
- Verify token hasn't expired

## Further Reading

- [OAuth 2.0 RFC 6749](https://datatracker.ietf.org/doc/html/rfc6749)
- [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html)
- [PHP League OAuth2 Server Documentation](https://oauth2.thephpleague.com/)
