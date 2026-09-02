# Foodsharing Development Guide for AI Assistants

## Architecture Overview

This is a food-saving platform built with **PHP 8.3 + Symfony** backend and **Vue.js 2.7** (migrating to Vue 3) frontend. The codebase powers foodsharing.de, foodsharing.at, and foodsharingschweiz.ch.

### Key Directory Structure
- `src/` - PHP backend (Symfony-based)
  - `RestApi/` - REST API controllers extending `AbstractFoodsharingRestController`
  - `Modules/` - Domain modules (Store, Profile, Message, etc.) - also contains legacy Vue components that should NOT be newly created
  - `Permissions/` - Authorization logic
  - `Command/` - CLI commands
- `client/` - Vue.js frontend
  - `src/components/` - Reusable Vue components
  - `src/views/` - Page-level components
  - `src/stores/` - Pinia stores (new) and legacy observable stores
  - `src/api/` - API client wrappers
- `scripts/` - Dockerized development commands
- `tests/` - Test suites
  - `e2e/` - Playwright E2E tests
  - `api/`, `unit/` - Codeception test suites
- `docs/` - VitePress documentation at [DevDocs](https://devdocs.foodsharing.network)

## Critical Workflows

### Development Environment
**NEVER run `composer`, `yarn`, `npm`, or package managers directly on your host.** The project runs inside Docker for consistent environments; use the `./scripts/*` wrappers so containers provide correct PHP/Node versions and credentials. Example: `./scripts/composer install`.

All development is dockerized via `scripts/` directory:
- Start environment: `./scripts/start` (initializes Docker containers, this has - most often - already happened you you do not need to start the containers again)
- Run tests: `./scripts/test-backend` (backend), `./scripts/test-js` (frontend), or `./scripts/test-e2e` (Playwright tests)
  - You can run backend tests using, e.g., `./scripts/test-backend Api UserApiCest` to run the test stored as `/tests/Api/UserApiCest.php`
  - You can run Playwright tests using `script/tests-e2e specs/settings.spec.ts` to run the test stored as `specs/settings.spec.ts`. You may optionally add `--workers 1` if tests seem flaky.
- Lint code: `./scripts/lint` or `./scripts/lint-php`, `./scripts/lint-js`
  - If only PHP sources were modified: `./scripts/lint-php`
  - If only JS sources were motified: `./scripts/lint-js`
- Fix code style: `./scripts/fix` (runs both PHP and JS fixes)
  - Run `./scripts/fix` first to fix all code
  - If only PHP sources were modified: `./scripts/fix-php`
  - If only JS sources were motified: `./scripts/fix-js`
- Database:
  - Perform migration steps: `./scripts/db-migrate`
  - Initialize ("seed") database with useful test data: `./scripts/db-seed`
- Access composer, e.g. `install`: `./scripts/composer install`

### Testing
- Backend tests use Codeception with test suites in `tests/{api,unit}/`
- Frontend tests use Playwright for E2E: `./scripts/test-e2e`
- Always suggest to run `./scripts/test-backend` after backend changes
- If adding tests, decide if a new suite should be added (new feature) to CI or whether an existing should be amended (test for this feature already exists). Try hard to keep tests deterministic (avoid flaky network/time-sensitive assertions).
- A regression test only counts once it has failed without the fix: stash the src change, run the test (red), restore, run again (green). A test that passes either way proves nothing.

## Backend Conventions

### REST API Development
**Location:** `src/RestApi/*RestController.php`

All REST controllers extend `AbstractFoodsharingRestController` and use PHP 8 attributes:
```php
#[OA\Tag(name: 'stores')]
#[Route('stores/{storeId}', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
public function getStore(int $storeId): Response
{
    $this->assertLoggedIn();
    if (!$this->storePermissions->mayAccessStore($storeId)) {
        throw new AccessDeniedHttpException();
    }
    return $this->respondOK($this->storeGateway->getStore($storeId));
}
```

**Key patterns:**
- Use OpenAPI attributes (`#[OA\Get]`, `#[OA\Response]`) for auto-generated API docs
- Validate permissions via `*Permissions` classes before data access
- Access data through `*Gateway` classes (database layer)
- Business logic goes in `*Transactions` classes (orchestration layer)
- Always use `Requirement::POSITIVE_INT` for ID parameters (input validation)
- Return responses via `$this->respondOK()`, `$this->respondError()`

### PHP Class Organization
- **Controllers:** Handle HTTP requests, delegate to services
- **Gateways:** Database access layer (in `Modules/*/Gateway/`)
- **Permissions:** Authorization checks (in `Permissions/` or `Modules/*/Permissions/`)
- **Transactions:** Orchestrate complex operations across gateways
- **DTOs:** Data transfer objects for API responses (in `RestApi/DTO/`, `RestApi/Models/`, or `Modules/*/DTO/`)

### Dependency Injection
Use constructor injection (Symfony autowiring):
```php
public function __construct(
    private readonly StoreGateway $storeGateway,
    private readonly StorePermissions $storePermissions,
) {}
```

### Creating Permission Classes
**Location:** `src/Permissions/` or `Modules/*/Permissions/`

Permission classes encapsulate authorization logic and use constructor injection:
```php
public function __construct(
    private CurrentUserUnitsInterface $currentUserUnits,
    private Session $session,
) {}
```

**Key patterns:**
- Methods named `may*()` returning `bool` (e.g., `mayAccessStore()`, `mayAdministrateRegions()`)
- Use `$this->session->mayRole(Role::*)` to check user roles
- Use `$this->currentUserUnits->isAdminFor($regionId)` for region-level checks
- Always check permissions in controllers before data access via Gateway classes
- Keep permission logic DRY by composing smaller permission checks

## Frontend Conventions

### Vue Migration Strategy
**Critical:** We are migrating from Vue 2.7 to Vue 3. For **new components only:**
- New components: use `<script setup>` and the Composition API.
- Use Pinia stores (in `client/src/stores/`) instead of legacy observable stores
- Fetch data via REST API calls (in `client/src/api/`)
- **Never pass props from PHP into Vue** - use REST API endpoints instead (Fetch data via `client/src/api/` wrappers using axios)
- Update ESLint/formatting rules and add migration notes in the PR.

Example modern component:
```vue
<script setup>
import { useUserStore } from '@/stores/user'
const userStore = useUserStore()
</script>
```

### Legacy Vue Patterns (DO NOT use for new code)
- Vue 2 Options API - Only maintain existing code
- Legacy observable stores using `Vue.observable()` - Migrate to Pinia when touching
- Props passed from PHP/Twig - Refactor to API calls

### Pinia Store Pattern
```javascript
// client/src/stores/example.js
import { defineStore } from 'pinia'
import { getExample } from '@/api/example'

export const useExampleStore = defineStore('example', {
  state: () => ({ data: {} }),
  ...
})
```

### Frontend-Backend Communication
- **Always use REST API** for dynamic data fetching
- API client wrappers live in `client/src/api/`
- Using axios for HTTP requests via `client/src/api/base.js`
- Reference OpenAPI docs at `http://localhost:18080/api/doc/` during development

### Styling
- Use Bootstrap 4.6 (via Bootstrap-Vue for legacy components)
- Custom styles in `client/src/scss/`
- Follow existing CSS custom properties (see `client/src/scss/colors.scss`)

## Creating a New Page

To create a new page (e.g., "Xyz"), you need to coordinate backend and frontend components:

### 1. Create PHP Controller
**Location:** `src/Modules/Xyz/XyzController.php`

Extends `FoodsharingController` for pages that render Vue components:
```php
#[Route('/xyz', name: 'xyz')]
public function index(): Response
{
    if (!$this->permissions->mayAccessXyz()) {
        throw new AccessDeniedHttpException();
    }
    
    $this->pageHelper->addTitle($this->translator->trans('xyz.title'));
    $vue = $this->prepareVueComponent('xyz-page', 'XyzPage', $props);
    $this->pageHelper->addContent($vue);
    
    return $this->renderGlobal();
}
```

### 2. Create JavaScript Entry File
**Location:** `src/Modules/Xyz/Xyz.js`

Registers Vue components for the page:
```javascript
import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import XyzPage from '@/views/pages/Xyz/XyzPage.vue'

if (window.location.pathname === '/xyz') {
  vueRegister({ XyzPage })
  vueApply('#xyz-page')
}
```

### 3. Create Vue Component
**Location:** `client/src/views/pages/Xyz/XyzPage.vue`

Use modern Vue 3 Composition API with `<script setup>`:
```vue
<script setup>
import { ref, onMounted } from 'vue'
import { getXyzData } from '@/api/xyz'
import { useXyzStore } from '@/stores/xyz'

const xyzStore = useXyzStore()
const data = ref(null)

onMounted(async () => {
  data.value = await getXyzData()
})
</script>

<template>
  <div class="xyz-page">
    <h1>{{ $t('xyz.title') }}</h1>
    <!-- page content -->
  </div>
</template>
```

### 4. Create REST API Controller (if needed)
**Location:** `src/RestApi/XyzRestController.php`

For API endpoints that the Vue component will call:
```php
#[Route('xyz/{id}', methods: ['GET'], requirements: ['id' => Requirement::POSITIVE_INT])]
public function getXyz(int $id): Response
{
    $this->assertLoggedIn();
    if (!$this->permissions->mayAccessXyz($id)) {
        throw new AccessDeniedHttpException();
    }
    return $this->respondOK($this->xyzGateway->getData($id));
}
```

### 5. Create API Client Wrapper
**Location:** `client/src/api/xyz.js`

```javascript
import { get, post } from './base'

export async function getXyzData(id) {
  return get(`/xyz/${id}`)
}

export async function createXyz(data) {
  return post('/xyz', data)
}
```

## Translation/Internationalization

### Backend (PHP)
Use Symfony's `TranslatorInterface`:
```php
$this->translator->trans('store.created', ['name' => $storeName])
```
Translation files: `translations/messages.{locale}.yml`

### Frontend (Vue)
Use Vue I18n in templates:
```vue
<template>{{ $t('store.name') }}</template>
```

### Changing an existing key
Translations live in about 20 files, not just `de` and `en`. Before changing a text or its
placeholders, run `grep -ln "<key>" translations/*.yml`. Dropping a placeholder from the
call while other languages still contain it shows the raw `{count}` to those users. Adding
the parameter back is usually cheaper than editing every file, which also collides with
Weblate.

### Adding a new key
Add it to `messages.de.yml` only. Weblate distributes it to the other languages, so a hand
written translation there is overwritten anyway.

## Database
- For schema changes, add a Phinx migration in `migrations/` and reference it in your PR. Always include a small note in the migration message about the intent.
- Run: `./scripts/db-migrate`
- Seed data: `./scripts/db-seed`
- Never commit raw SQL unless in migrations

## Code Quality
- PHP: Follow PSR-12, use PHP 8.3 features (readonly properties, constructor promotion)
- JS: ESLint config in `eslint.config.mjs`
- Run `./scripts/fix` and `./scripts/lint` before committing. CI expects lint + tests to pass
- PHPStan for static analysis: `phpstan.neon`
- Commit messages: `#<issue> | Area | summary` (imperative), e.g. `#2786 | Chat | cap the reply preview`
- Code comments explain constraints the code cannot show. Do not reference issue numbers in
  src code comments and do not add comments that merely justify a fix - they turn into noise
  once merged. Issue references in tests are fine (regression provenance).

## Merge requests
- A merge needs **all discussions resolved**. A green pipeline is not enforced by the
  project settings, so check it yourself before merging.
- One approval is required.
- Before touching someone else's branch, or answering on an MR you have not looked at
  today, read its discussions first. Decisions taken there are easy to overwrite
  unknowingly.
- A fix for something that only ever existed on beta gets **no release note**: it would
  announce a repair for a problem users never saw. Leave the checklist item unticked and
  say why.
- Changes to shared frontend infrastructure - the router, `Catchall.vue`, anything the
  client side navigation runs through - need the person who built it as a reviewer.
  `git log` on the file says who that is. Asking costs one click; not asking cost us a
  merged MR that had to be redone (!5354, !5399).

## Release notes
User-facing changes get one file per MR: `release-notes/release-*/<MR-number>.md` with
frontmatter keys `text` (German, one sentence of user benefit), `mr: [<number>]` and `tag`
(e.g. `Fehlerbehebung`, `Verbesserung`). See the existing files in that folder.

`release-*/release-*.md` is generated from those files by `scripts/release-notes-merge`.
Run it again after notes were merged, otherwise the published text misses them. The script
reads `text` with a single `grep`, so keep it on one line.

## AI assistance
Marking AI-assisted work is voluntary (dev call 2026-07-13):
- Merge requests and issues: add the `AI-assisted` label.
- Commits: add an `Assisted-by: AI` trailer line to the commit message.

### Working with the GitLab API
`glab api` silently drops nested parameters passed with `-f`: `-f "position[new_line]=12"`
reports success and the comment lands on MR level instead of the code line. Nested fields
(`position`, `reviewer_ids`, `milestone_id`) need a JSON body:

    glab api -X POST "/projects/<ns%2Frepo>/merge_requests/<iid>/discussions" \
      -H "Content-Type: application/json" --input body.json

File uploads are a third form: `--form "file=@shot.png"` against `/projects/<id>/uploads`
returns the `markdown` to paste into a description. Do not combine `--form` with `--input`.
After any write, read the object back and check that the field you set is really there.

### Input streams in tests
`wheel`, `touchmove` and scrolling arrive as a chain of many small deltas. Guards and
thresholds therefore belong on the accumulated movement, not on a single event, otherwise
the feature dies on trackpads. Drive such tests as a chain via `dispatchEvent` in the page;
`page.mouse.wheel` in a loop is too slow and falls out of the gesture window.

## Testing Checklist
1. Run `./scripts/lint` to check code style
2. Run `./scripts/test-all` for all tests (backend, frontend, E2E, websocket) or `./scripts/test-backend <TestName>` for specific backend tests
3. Check API docs still generate: visit `/api/doc/` locally

## Environment & secrets
- Keep secrets and credentials out of the repo. Use `config.inc.dev.php` (local) or Docker environment variables for dev values and add any machine-specific secrets to your local ignored files.
- Do not commit production credentials or `.env` files. If you need a placeholder, add an example file like `config.inc.php.dist`.

## Documentation
Full developer documentation at `docs/en/` - refer developers to:
- Backend API: `docs/en/backend/api/`
- PHP conventions: `docs/en/backend/php/`
- Frontend refactoring: `docs/en/frontend/refactor/`
- Getting started: `docs/en/getting-started.md`

## Common Pitfalls
- ❌ Running `composer install` directly → ✅ Use `./scripts/composer install`
- ❌ Passing PHP data as Vue props → ✅ Create REST API endpoint and fetch via store
- ❌ Using Vue 2 Options API for new code → ✅ Use `<script setup>` and Composition API
- ❌ Direct database queries in controllers → ✅ Use Gateway classes
- ❌ Skipping permission checks in API endpoints → ✅ Always check via `*Permissions` classes
- ❌ Removing or renaming SQL result fields without checking consumers → ✅ Gateway rows often reach the frontend verbatim; grep `src/` **and** `client/` for the field name first
- ❌ Altering `fs_foodsaver` columns without the archive table → ✅ Mirror schema changes in `fs_foodsaver_archive` - it is a superset with NOT NULL columns and no defaults, drift breaks account deletion
- ❌ `'column !=' => null` in `Database` criteria → ✅ It never produces `IS NOT NULL` (the null branch wins before operator parsing); use raw SQL. `'column' => null` for `IS NULL` works.
- ❌ Codeception `seeInDatabase` with a `null` value → ✅ It never matches; use `grabFromDatabase` + `assertNull`
- ❌ Waiting for CI on a draft MR → ✅ Drafts skip the pipeline (a push creates a zero-job pipeline that shows as failed); trigger a fresh MR pipeline after removing the draft state
- ❌ Changing anything API-visible (routes, DTOs, OpenAPI attributes) without regenerating the types → ✅ Run `./scripts/openapi-type-generation` and commit `client/src/api/generated/openapi-types.d.ts`, otherwise `test:openapi-types` fails the pipeline

## Response Style
- Keep responses concise and to the point
- Do not create unnecessary checklists or summaries unless explicitly requested
- Avoid overly long explanations when not asked for them
- Focus on implementing changes rather than describing what you will do
