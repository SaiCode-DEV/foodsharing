# Testing

## Playwright: Automated Browser Testing

### Quick Start

**Prerequisites:** Docker and Docker Compose installed

**Run all tests:**
```bash
./scripts/test e2e
```

**Run specific test:**
```bash
./scripts/test-e2e specs/LoginTest.spec.ts
```

**Before pushing code:**
```bash
./scripts/test
```

### Usage

| Command | Purpose |
|---------|---------|
| `./scripts/test e2e` | Run all E2E tests (includes DB setup) |
| `./scripts/test-e2e` | Run E2E tests only (requires test env running) |
| `./scripts/test-e2e <file>` | Run specific test file |
| `./scripts/test-e2e --workers=2` | Run with custom worker count |
| `./scripts/test-e2e debug` | Open interactive shell for manual testing |

More options: https://playwright.dev/docs/running-tests

### Interactive Mode

For development and debugging:
```bash
./scripts/test-e2e debug
```

Inside the container:
```bash
yarn test                           # Run all tests
yarn test specs/LoginTest.spec.ts  # Run specific test
yarn test --workers=2               # Adjust parallelization
yarn test:ui                        # Run tests with browser UI. Open browser on host at http://localhost:23008
exit                                # Leave container
```

Press CTRL-C to stop tests gracefully and view the HTML report.

### Test Reports

After tests you can open the report with:
```bash
yarn show-report
```

**Artifacts:**
- Results: `tests/_output/test-results/`
- HTML report: `tests/_output/html-report/`

### Setup

**Docker:** Tests run in `foodsharing_test_e2e` container with Chromium, Firefox, and WebKit pre-installed.

**Linting:** Pre-commit hooks via Husky are installed automatically with `yarn install` in `tests/e2e/`.

To bypass: `git commit --no-verify`
