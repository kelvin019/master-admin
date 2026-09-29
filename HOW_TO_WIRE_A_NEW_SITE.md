# How to wire Master CMS Admin into a new website

This is the standard, repeatable procedure this workspace uses to turn a static
HTML template into a full, database-backed website with a working admin. Follow
this exact sequence for every new project. It assumes no prior context — a fresh
chat, a different agent (Codex, another Claude session, etc.), or a future you
should be able to execute this end to end from this document alone.

Read this entire file before touching any code. Do not skip the audit steps —
copying the admin is the easy part; wiring it correctly to a specific HTML
template without breaking the design is where mistakes happen.

---

## 0. What this pattern actually is

We do **not** run one shared admin for all sites. Every website gets its **own
private copy** of this admin folder, with its **own database**, its **own
config.php**, and its **own uploads folder**. The copy is then customized (module
list only — not the underlying engine) to match that specific site's content
types, and the site's existing static HTML/CSS is converted into PHP templates
that pull real data from that copy's database.

So the workflow per new site is:

1. Copy `master_cms_admin.local` into the new project as `admin/` (or similar).
2. Give it its own database and `config.php`.
3. Define that site's content modules in `config.php` (Projects, Gallery,
   Portfolio, Services, Team, etc. — whichever the site actually needs).
4. Install it (create the primary administrator).
5. Convert the site's static HTML pages into PHP that read from `cms_content`
   via the admin's data, replacing hardcoded text/images with live records.
6. Wire clean URLs (no `.php` in the address bar) and confirm the design is
   byte-for-byte the same as the original static HTML — the CMS conversion must
   never visibly change the site.
7. Test everything live (see Section 7) before calling it done.

This admin (`master_cms_admin.local`) itself is never the live site. It is the
**master template** you copy from. Never edit a site's copied admin expecting
those changes to appear elsewhere — each copy is independent from this point on.
If you build a genuinely reusable improvement (a new field type, a bug fix in
the core engine), make it here in the master too, so future copies inherit it.

---

## 1. Copy the admin into the new project

```bash
# Example: new project is /Applications/AMPPS/www/<newsite>.local
cp -R /Applications/AMPPS/www/master_cms_admin.local /Applications/AMPPS/www/<newsite>.local/admin
rm -rf /Applications/AMPPS/www/<newsite>.local/admin/.git 2>/dev/null
```

Do **not** copy `vendor/` if you can avoid it — instead run `composer install`
fresh inside the copied `admin/` folder so PHPMailer and any other dependency
is correctly linked for that project. If Composer isn't available in that
environment, copying `vendor/` verbatim also works.

Remove anything copy-specific that shouldn't travel between sites:
- Delete any existing `uploads/` contents (keep the folder, empty it).
- Delete `admin.login.txt` if one exists in the source (it holds the master's
  own credentials — never let another site inherit them).

## 2. Create the database

Each site gets its own MySQL database. Naming convention: `<sitekey>_cms` or
reuse the site's existing DB if it already has one for the frontend (the admin
tables live alongside the site's own tables fine — they're all prefixed `cms_`).

```sql
CREATE DATABASE IF NOT EXISTS <newsite>_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Edit `admin/config.php`

Update at minimum:
- `db.name` (and host/user/pass if different from the AMPPS default `root`/`root`)
- `site.name`, `site.tagline`
- `admin.title`, `admin.session_name` (must be unique per site so admin
  sessions/cookies from different local sites don't collide — e.g.
  `<newsite>_admin`)
- `admin.uploads_dir` / `admin.uploads_url` (usually fine as-is, relative to
  the copied admin folder)

### 3a. Define the content modules for this specific site

This is the step that actually customizes the admin per project. Look at the
static HTML you're converting and identify every repeating content type:
projects, services, team members, testimonials, gallery images, FAQs, etc.
Every module needs an entry under `modules` in `config.php`. Use the existing
master `config.php` in this repo as the reference for field-type syntax
(text, slug, textarea, richtext, image, gallery, date, number, status,
`['category', module_key]`, `['icon', [...options]]`, `['select', [...options]]`).

Only include modules the site actually needs. Delete/omit anything irrelevant
(e.g. a site with no blog doesn't need `posts`; a site with no equipment
listing doesn't need `equipment`). Keep the system modules (`media`,
`settings`, `users`, `roles`, `audit`, `mail`, `backups`) — those are part of
the admin shell, not content.

If the site needs a content type this admin has never modeled before (e.g. a
downloadable brochures library, a job-openings board), add a new module entry
with a field list — you do **not** need to write new CRUD code. The generic
engine in `lib/content.php` builds list, create, edit, trash, and delete
screens from the field definitions automatically. Only add real PHP/JS if the
new module needs a genuinely new **field type** the engine doesn't already
support (rare — check `render_content_control()` in `lib/content.php` and the
JS in `assets/admin.js` before assuming you need one).

## 4. Install

Browse to `/<newsite>.local/admin/install.php`, create the primary
administrator with a strong password (12+ characters), and save the generated
credentials into a new `admin.login.txt` at the **project root** (not inside
`admin/`, and never committed if this repo is ever pushed anywhere public):

```
================================================================
<SITE NAME> ADMIN — ADMIN ACCESS & CONFIGURATION GUIDE
================================================================
Project folder: /Applications/AMPPS/www/<newsite>.local

1. LOGIN
   URL      : http://localhost/<newsite>.local/admin/login.php
   Username : <username>
   Password : <password>

2. INSTALLATION STATUS
   Already initialized. Do not re-run install.php.
```

Then go to **Settings** in the sidebar and disable any modules you didn't end
up needing, and go to **Roles & Permissions** to confirm CRUD permissions look
right for administrator/editor/author roles (they're seeded automatically by
`install_schema()`, but always double check against what this specific site
actually needs delegated).

## 5. Seed real content

Before wiring the frontend, populate each enabled module with the site's
**actual** content (copied out of the static HTML you're replacing) through
the admin UI itself — Projects, Team, Services, Gallery images, etc. Wiring
the frontend against an empty database makes it impossible to visually verify
the conversion didn't break anything, so always seed first.

For image-heavy modules (gallery, portfolio, projects), upload the site's
existing images through the Media Library rather than leaving old hardcoded
`<img src="assets/img/...">` paths in place — the whole point of the CMS is
that these become editable without touching code.

## 6. Convert the frontend

This is the bulk of the work and the part most likely to be rushed. Do not
rush it.

### 6a. Read data from `cms_content`

The admin's schema (see `lib/schema.php`) stores **all** content-module rows
in one shared table, discriminated by `module_key`:

```sql
cms_content(
  id, module_key, title, slug, status, data JSON,
  created_by, updated_by, published_at,
  deleted_at, deleted_by,          -- soft-delete / trash
  created_at, updated_at
)
```

`data` is a JSON blob keyed by the module's field names (exactly as defined in
`config.php`). `title` and `slug` are duplicated out of `data` at save time for
fast listing/lookup — always query those two as real columns, not from inside
the JSON.

Add a small `includes/cms.php` (or extend the site's existing `includes/db.php`)
in the **frontend** project with helpers like:

```php
function cms_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = require __DIR__ . '/../admin/config.php';
    $d = $c['db'];
    $pdo = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}",
        $d['user'], $d['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $pdo;
}

/** Published, non-trashed rows for one module, newest first unless overridden. */
function cms_content(string $module, array $opts = []): array {
    $where = ['module_key = ?', 'deleted_at IS NULL'];
    $args = [$module];
    $where[] = 'status = ?'; $args[] = $opts['status'] ?? 'published';
    if (!empty($opts['slug'])) { $where[] = 'slug = ?'; $args[] = $opts['slug']; }
    $sql = 'SELECT * FROM cms_content WHERE ' . implode(' AND ', $where)
         . ' ORDER BY ' . ($opts['order_by'] ?? 'created_at DESC');
    if (!empty($opts['limit'])) $sql .= ' LIMIT ' . (int)$opts['limit'];
    $st = cms_db()->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    foreach ($rows as &$row) $row['data'] = json_decode((string)$row['data'], true) ?: [];
    return $rows;
}

function cms_content_one(string $module, string $slug): ?array {
    $rows = cms_content($module, ['slug' => $slug, 'limit' => 1]);
    return $rows[0] ?? null;
}

/** Resolve a stored media path exactly the way the admin does. */
function cms_media_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path)) return $path;
    return rtrim('admin/' . ltrim(($GLOBALS['__cms_uploads_url'] ??= (require __DIR__.'/../admin/config.php')['admin']['uploads_url'] ?? 'uploads'), '/'), '/') . '/' . ltrim($path, '/');
}
```

Adjust the relative path from the frontend's `includes/` folder to `admin/`
to match the real project layout. Keep this helper file small and boring —
its only job is reading `cms_content`, never writing to it (all writes go
through the admin).

### 6b. Gallery fields

Gallery-type fields (`'gallery' => 'gallery'` in the module's field list) are
stored as a JSON array of media paths inside `data['gallery']`
(e.g. `["2026/09/abc123.jpg", "2026/09/def456.jpg"]`). Loop and pass each
through `cms_media_url()`:

```php
$project = cms_content_one('projects', $slug);
foreach ($project['data']['gallery'] ?? [] as $image) {
    echo '<img src="' . e(cms_media_url($image)) . '">';
}
```

**Do not** assume the field is always an array in code that also runs against
older/differently-shaped data — guard with `?? []` and `is_array()` checks, the
same way `lib/content.php` had to be fixed to do (see the `is_array($value) &&
$type !== 'gallery'` fix already applied in this admin's core — that bug is
what happens when this assumption is skipped).

### 6c. Replace every static content block

Go through each page of the original static HTML and replace hardcoded repeated
blocks (project cards, team cards, testimonial quotes, FAQ items, gallery grids)
with a loop over `cms_content(...)`. Keep every class name, wrapper `<div>`,
and markup structure **exactly** as the static HTML had it — only the data
source changes, never the visual output. This is the single most important
rule in this whole workflow: **the converted page must be visually identical
to the static original**, verified by screenshot comparison (Section 7).

Singular/detail pages (`project.php?slug=`, `service.php?slug=`, etc.) use
`cms_content_one($module, $slug)` and 404 if it returns null.

### 6d. Contact forms / messages

If the site has a contact form, either:
- Wire it to write into `cms_content` with `module_key = 'messages'` (if you
  added a `messages` module to this site's `config.php`, matching the shape
  the admin's Mail inbox expects — check `lib/mail_client.php`'s
  `mail_contact_messages()` in the master admin for the exact query it runs), or
- Keep it as a simple mailer using the same SMTP settings configured in
  the admin's **Settings → Outgoing mail (SMTP)** panel, reading `cms_settings`
  the same way `mail_client.php` does, so there's one single place
  (the admin UI) to configure mail delivery for the whole site.

## 7. Clean URLs (no `.php` in the address bar)

Every site in this workspace uses extension-less URLs
(`/about`, `/project/some-slug`, not `/about.php` or `/project.php?slug=...`).
Set this up with `.htaccess` in the **frontend** project root (not inside
`admin/` — the admin keeps its own `.htaccess` protections and is never
rewritten):

```apache
RewriteEngine On
RewriteBase /<subfolder-if-any>/

# Canonical redirect: kick a request that still hits the old ?slug= pattern
# to the clean URL, if the old pattern is still reachable at all.
RewriteCond %{THE_REQUEST} \s/+([^?\s]*?)\.php\?slug=([^\s&]+) [NC]
RewriteRule ^ %1/%2? [R=301,L]

# Resolve /about -> about.php if the file exists.
RewriteCond %{REQUEST_FILENAME}\.php -f
RewriteRule ^([^/]+)/?$ $1.php [L]

# Resolve /project/some-slug -> project.php?slug=some-slug
RewriteCond %{DOCUMENT_ROOT}/project.php -f
RewriteRule ^project/([^/]+)/?$ project.php?slug=$1 [L,QSA]
# repeat the above pattern per singular content type (service/, post/, etc.)
```

Adjust `RewriteBase` per environment: it must be `/` at the domain root in
production, and `/<foldername>/` locally under AMPPS if the site lives in a
subfolder of `htdocs`/`www`. Derive it dynamically instead of hardcoding where
possible (see how `farm.exaltedgroup.local/includes/site.php`'s
`site_base_path()` derives this from `APP_URL`/document root, so the same code
works unmodified in both places) — this exact hardcoding-vs-production mistake
has bitten this project before.

Never link to `*.php` from within site templates — always use the clean path
(`url('project/'.$row['slug'])` style helper), the same convention every other
site in this workspace already follows.

## 8. Lock down before calling it done

- Confirm `admin/install.php` cannot be re-run against a live install (either
  delete it after setup, or leave the built-in "already initialized" guard in
  place — ask the project owner which they prefer; both are valid choices
  made on different past projects in this workspace).
- Confirm `admin/.htaccess` denies `config.php`, `.env`, `composer.json/lock`,
  `*.sql`, `*.log`, and blocks direct access to `vendor/`, `lib/`, `storage/`.
- Confirm SMTP is configured (Settings → Outgoing mail) and a real test email
  actually arrives — don't trust `mail()` returning `true`. Use a local
  Mailpit catcher (port 1025 SMTP / 8025 web UI) to prove delivery if there
  are no real SMTP credentials yet for that site.
- Change the admin session name (`admin.session_name` in config.php) so it
  never collides with another project's admin session if both are open in the
  same browser during testing.

## 9. Verify live before reporting done

Static review is not enough — actually load the pages:

1. `php -l` every PHP file you touched.
2. Load every converted frontend page and diff it visually against the
   original static HTML (screenshot both, side by side).
3. Log into the admin, create/edit/trash/restore one record in every module
   you wired, and confirm the change round-trips to the live frontend page
   without a manual cache clear or restart.
4. Submit the contact form (if any) and confirm the message either lands in
   the admin's Mail inbox or is actually delivered by SMTP — check, don't
   assume.
5. Resize the browser to a mobile width and confirm the CMS-sourced content
   (especially galleries and long text fields) doesn't break the original
   responsive layout.

Only after all five pass should you consider the conversion complete.

---

## Reference: modules already modeled in this master admin

Use these as field-shape templates when defining a new site's modules —
copy the closest existing one and trim/extend it rather than starting blank:

`pages`, `posts`, `projects`, `portfolio`, `services`, `team`, `testimonials`,
`faqs`, `certifications`, `equipment`, `galleries`, `categories`, `menus`,
`forms`, `newsletter_subscribers`, `redirects`.

See `config.php` in this folder for their exact field definitions.

---

## Reference: inventory of sites built with our custom admins

This is the confirmed list of sites built with one of our own custom admins
(as opposed to other, unrelated projects that happen to live in the same
`/Applications/AMPPS/www/` folder). They fall into three lineages — check
which one a given site belongs to before assuming this doc's instructions
apply as-is, since the module/config shape and file layout differ between
them and instructions from one don't transfer cleanly to another.

### Lineage A — this master admin (`config.php` with a `modules` array)

Content-module CMS engine: generic list/create/edit/trash screens generated
from field definitions in `config.php`, no e-commerce concept.

- **`vorcentaglobalconstruction.local`** — construction company site (Services,
  Projects, Team, Testimonials, FAQs, Posts, Galleries modules). Built and
  documented in this conversation. The first production copy made from this
  master template.

### Lineage B — older lightweight gallery/content admin (`list.php` / `edit.php` / `delete.php`, `config.php` with `site` + `brand` sections)

A simpler, earlier custom admin: flat list/edit/delete CRUD per content type
(no generic module-driven engine), a gallery-upload feature, and a
`site`/`brand`-keyed config rather than a `modules` array.

- **`pgmnigeria.local`** — PGM Nigeria (governance/programmes org site)
- **`resourcefield.local`**
- **`windforcesafeguards.local`**
- **`zeitgeistaesthetics.local`**
- **`culturepreneurclusters.local`**

All five share near-identical admin file sizes and listings — almost
certainly copies of one shared template, distinct from this master admin.

### Lineage C — e-commerce admin (products, orders, customers, cart/checkout, delivery)

- **`farm.exaltedgroup.local`** — lean, purpose-sized commerce admin. ~21
  small admin files (25KB total). Schema: `products`, `categories`, `orders`/
  `order_items`/`order_status_history`, `customers`, `coupons`, `riders`
  (delivery tracking), `inventory_log`, `payment_transactions`, `reviews`,
  plus separate `content_pages`/`farm_posts`/`farm_services` tables for the
  non-commerce pages. Config via `.env`, not `config.php`. **The reference
  to use for "normal content + a narrow single-category product line +
  delivery"** — e.g. the fertilizer-only shop under discussion.

### Not part of our admin lineages

- **`osuntourismpreneur.local`** — a service/directory finder (tourism
  amenities, bookings, claims). It has its own bespoke `config.php` and
  `admin/` folder, but doesn't share a real lineage with Lineage A or B —
  don't use it as a template for a new site.

### How to use this when starting new work

1. Identify what the new project actually needs: pure content (→ Lineage A,
   this master admin) or a narrow single-product-line shop with delivery
   (→ Lineage C, `farm.exaltedgroup.local`).
2. Don't mix lineages inside one project (e.g. don't bolt this master admin's
   `modules` engine onto `farm.exaltedgroup.local`'s schema) — pick the one
   whose existing data model already matches the requirement, and extend it,
   rather than gluing two admin engines together.
3. If unsure which lineage a given site belongs to, check for a `modules`
   array in `config.php` (Lineage A), `list.php`/`edit.php`/`delete.php` file
   names with a `site`/`brand` config (Lineage B), or `products`/`orders`
   tables plus `cart.php`/`checkout.php` (Lineage C).
4. Update this list every time a new site is added, so it stays a reliable
   inventory rather than going stale.
