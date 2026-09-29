# Master CMS Admin

Reusable, admin-only CMS foundation for copied website projects.

**Converting a static HTML site into a full website with this admin?** Read
[`HOW_TO_WIRE_A_NEW_SITE.md`](HOW_TO_WIRE_A_NEW_SITE.md) first — it is the
step-by-step procedure this workspace uses every time, written so a fresh
chat or a different coding agent can follow it without any prior context.

## Included foundation

- Generic content CRUD engine using a module registry
- Projects, portfolio, services, pages, posts, team, testimonials, FAQs, certifications, equipment, galleries, categories, menus, forms, Mail, newsletter subscribers and redirects
- Media library with MIME validation and randomized stored filenames
- Site settings and per-site module enable/disable controls
- Roles and explicit CRUD permissions for every module
- Protected primary super administrator
- Subordinate super administrators with the same `super_admin` role, while the primary administrator remains protected from deletion
- Configurable administrator, editor and author roles
- CSRF protection, strict sessions, login throttling, password hashing and audit logging
- Mail template management
- Local database backup creation
- Apache protection for configuration, SQL backups, libraries and storage

## Initialize

1. Browse to `/master_cms_admin.local/install.php`.
2. Create the primary administrator using a strong password of at least 12 characters.
3. Remove or protect `install.php` after installation.
4. Use **Settings** to enable or disable modules for the copied website.
5. Use **Roles & Permissions** to assign CRUD permissions.

The default database is `master_cms_admin`. Database credentials can be overridden with `MASTER_CMS_DB_HOST`, `MASTER_CMS_DB_PORT`, `MASTER_CMS_DB_NAME`, `MASTER_CMS_DB_USER` and `MASTER_CMS_DB_PASS` environment variables.

## Permission format

Every module receives four permissions:

```text
gallery.read
gallery.create
gallery.update
gallery.delete
```

The primary administrator bypasses role checks, but the primary user is marked separately and cannot be deleted by any administrator. A subordinate `super_admin` receives all generated CRUD permissions through the role table without becoming the protected primary user.

## Architecture direction

The data-driven `cms_content` table lets a copied site add or remove content modules without creating a new CRUD controller for each type. The next implementation phase should add field-level configuration, revisions, workflow approvals, scheduled publishing, image derivatives, SMTP transport, incoming mail, API tokens, webhooks, multi-site profiles and automated tests.
