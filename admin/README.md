# Multi-Site Administration (TAREVO / product)

Secure multi-site administration for OpenEMR 8.4 product builds on
`feat/multi-site-improvements`.

## Access

- `http://host/admin/login.php` or `http://host/admin/`
- Root `admin.php` always redirects to `admin/login.php` (legacy unauthenticated
  multi-site UI is not used on this product branch)

## Auth (P0.2)

- Default site (`site_id === default`) only — no null-coalesce fallbacks
- Shared gate: `admin/bootstrap.php`
- Credentials: default-site user with Administrators / `admin` super ACL
- Session rotate on login; IP/UA binding; 30-minute timeout; privilege recheck
- CSRF subjects: `admin_login`, `admin_dashboard`, `admin_add_site`
- Logout and cache refresh are **POST + CSRF only** (GET rejected with 405)

## Layout (code)

```
admin/
  bootstrap.php          # headers, default-site bootstrap, auth + CSRF helpers
  login.php              # unauthenticated login controller
  index.php              # authenticated dashboard controller
  README.md
src/Admin/
  AdminAuthService.php
  SiteAdministrationService.php
  SiteStatusCacheService.php
templates/admin/
  base.html.twig         # HTML shell + optional left side-nav
  login.html.twig        # full-page login (no side-nav)
  dashboard.html.twig    # sites table inside side-nav shell
sql/admin_site_status_cache.sql
```

## Template structure

### Inheritance

```
base.html.twig
├── login.html.twig        # admin_shell unset/false → content only (centered card)
└── dashboard.html.twig    # admin_shell=true → left rail + workspace
```

### Twig blocks (`base.html.twig`)

| Block | Purpose |
|-------|---------|
| `title` | Document title |
| `head` | Default `setupHeader(['no_main-theme'])` |
| `css` | Shared admin CSS (shell + body); children call `{{ parent() }}` |
| `pre_content` | Optional markup before shell/content |
| `content` | **Single** content block (defined once; shell wraps it when enabled) |
| `admin_sidebar_actions` | Footer of rail (default: CSRF logout form) |
| `post_content` | After shell/content, still inside `<body>` |
| `post_body` | After `</body>` scripts (shell toggle lives here) |

`content` must appear only once in the parent. The left-nav markup opens before
`{% block content %}` and closes after it when `admin_shell` is true, so login
and dashboard can share one base without duplicate blocks.

### Controller → template variables

**Login** (`admin/login.php` → `admin/login.html.twig`):

- Does **not** pass `admin_shell` (full-page gradient login)
- `loginFail`, `errorMessage`, `version`, CSRF via `csrfToken('admin_login', ...)`

**Dashboard** (`admin/index.php` → `admin/dashboard.html.twig`):

| Variable | Role |
|----------|------|
| `admin_shell` | `true` — enable left side-nav shell |
| `nav_active` | `'sites'` (or future keys) for `is-active` link |
| `username` | Shown in rail footer |
| `sites` | List of site status rows |
| `show_add_site_button` | Renders Add New Site section (`#add-site`) |
| `webroot` | Prefix for setup/login/portal links |
| `cache_age` / `using_cache` / `cache_metadata` | Toolbar cache status |
| CSRF | `admin_dashboard` on refresh/logout; `admin_add_site` on setup POST |

### Left side-nav (when `admin_shell`)

- Brand: TAREVO / Multi-Site Admin
- Navigation: Sites → `index.php`; Add New Site → `index.php#add-site`
- Statement Schedules: disabled placeholder (Poppy-only; not ported)
- Resources: Help doc, Clinic login (`site=default`)
- Footer: username + POST logout
- Mobile (`max-width: 768px`): off-canvas rail + topbar hamburger (`#adminNavToggle`)

CSS hooks: `.admin-shell`, `.admin-shell__sidebar`, `.admin-shell__workspace`,
`.admin-shell__main`, `.is-active`, `.nav-disabled`.

### Dashboard body

- Toolbar: Configured Sites heading, cache age badge, POST Refresh
- Table columns: Site ID, DB Name, Site Name, Version, Is Current, Log In, Portal
- Row states: needs setup / error / upgrade (DB|ACL|patch) / current
- Add New Site block posts to `setup.php` with CSRF

## Optional cache table

Apply `sql/admin_site_status_cache.sql` to the **default** site database for
dashboard caching. Without it, the dashboard still works (live site scan each
load). Full install/upgrade wiring is backlog **P0.3**.

## Not included

- Statement release schedules (Poppy-only)
- Upstream PR of this admin UI (product fork unless decided later)
