# Update dependencies

## Slack
Every Sunday a scheduled pipeline "send outdated dependency report to slack" runs and results appear in **#fs-outdated**.

## Version Number Structure
```
2.3.5
│ │ │
│ │ └─── Patch (bug fixes, security patches)
│ └───── Minor (new features, backward compatible)
└─────── Major (breaking changes, API changes)
```

**Special cases:**
- `0.x.x` = Beta versions (any change can contain breaking changes)
- `1.0.0-alpha.x` = Pre-release versions

## Update Rules & Best Practices

### ✅ DO
- **Separate commits**: Dev dependencies vs runtime dependencies
- **Use `~` instead of `^`** for predictable builds across environments
- **Security updates first**: Always prioritize security patches

### ❌ DON'T
- **Mix dependency types** in one commit
- **Bulk update** without testing individual packages
- **Do not run `yarn update` unless all version use `~`

### 📋 Update Priority Order
1. **Security patches** (any version bump with CVE fixes)
2. **Patch versions** (`~` updates for bug fixes)
3. **Minor versions** (new features, backward compatible)
4. **Major versions** (separate MR)

## Package Manager Standardization

**All projects now use Yarn** for consistency:
- `client/` - Frontend (Vue.js)
- `websocket/` - WebSocket Server (Node.js)
- `docs/` - Documentation (VitePress)
- `tests/e2e/` - E2E Tests (Playwright)

### Check outdated packages
```bash
# Frontend
./scripts/docker-compose run --rm client sh
yarn outdated

# WebSocket Server  
./scripts/docker-compose run --rm websocket sh
yarn outdated

# E2E Tests
cd tests/e2e
yarn outdated

# Documentation
cd docs
yarn outdated
```

### Security vulnerability scan
```bash
# Check for known vulnerabilities
./scripts/docker-compose run --rm client sh
yarn audit

# WebSocket Server
./scripts/docker-compose run --rm websocket sh
yarn audit

# E2E Tests
cd tests/e2e
yarn audit

# Documentation
cd docs
yarn audit
```

### Check for deprecated packages
```bash
# Look for abandoned packages
yarn outdated | grep -i "deprecated\|abandoned"
```

## Update Procedures by Project

### 🎨 Frontend (`client/`)
```bash
# 1. Check current status
./scripts/docker-compose run --rm client sh
yarn outdated

# 2. Update package.json manually

# 3. Install dependencies
yarn

# 4. Test
yarn lint && yarn test
exit
```

### 💬 WebSocket Server (`websocket/`)
```bash
# 1. Check status
./scripts/docker-compose run --rm websocket sh
yarn outdated

# 2. Update package.json manually

# 3. Install dependencies
yarn

# 4. Test
yarn lint && yarn test
exit
```

### 📚 Documentation (`docs/`)
```bash
cd docs

# 1. Check status
yarn outdated

# 2. Update package.json manually

# 3. Install dependencies
yarn

# 4. Test build
yarn docs:build
```

### 🧪 E2E Tests (`tests/e2e/`)
```bash
cd tests/e2e

# 1. Check status
yarn outdated

# 2. Update package.json manually

# 3. Install dependencies
yarn

# 4. Test
yarn lint && yarn prettier
```

## Testing Strategy

### After Each Update
```bash
# Linting (all projects)
./scripts/lint
```

### Before Merge Request
```bash
# Full test suite
./scripts/test-all
./scripts/lint
```
