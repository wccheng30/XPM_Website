# XPM_Website

Source code for the Xpress Point Management Services website
([xpresspointservices.com](https://xpresspointservices.com)).

This is a custom PHP/MySQL application with:

- a public site (homepage, training programmes, job portal, facility booking, trainers, legal pages)
- user accounts (register, log in, reset password, view own bookings)
- an admin panel for managing content, registrations, applications and bookings
- [About the company](#about-the-company)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [First login and admin accounts](#first-login-and-admin-accounts)
- [Writable folders and uploads](#writable-folders-and-uploads)
- [Project layout](#project-layout)
- [Development notes](#development-notes)
- [Deployment checklist](#deployment-checklist)
- [License](#license)

---

## About the company

Xpress Point Management Services (xpresspointservices.com) is a comprehensive Human Resources (HR) consulting, outsourcing, and training firm based in Melaka, Malaysia. Established in 2010 and led by Founder and CEO iCoL Chua, the company specializes in providing end-to-end workforce solutions designed to streamline business administration and foster professional development for organizations of all sizes.

The company operates through two primary divisions:

**HR Services Division:** This branch handles essential outsourced HR functions, allowing businesses to focus on their core operations. Key services include end-to-end hiring and recruitment, comprehensive payroll and compensation management, and strict regulatory compliance. The firm ensures that clients adhere to local labor laws and maintain proper standing with Malaysian statutory authorities, such as KWSP, PERKESO, LHDN, and JTK.

**Training & Development Division:** As an officially registered HRD Corp Training Provider, Xpress Point offers a wide array of certified public and in-house training programs. These range from leadership development and executive coaching to practical HR mentoring and conflict resolution, all led by experienced industry professionals.

To support these educational initiatives, the company provides premium, fully-equipped facility rentals at their Malim Jaya headquarters. These state-of-the-art conference rooms, practical training rooms, and comfortable breakout areas are available to clients and external organizations for corporate workshops, meetings, and seminars.

Furthermore, the website serves as a recruitment hub, featuring a job portal that connects professionals with flexible career opportunities across multiple industries.

---

## Requirements

| Component | Requirement |
|-----------|-------------|
| PHP | **8.2 or newer** (required by PhpSpreadsheet 5.x) |
| Database | MySQL or MariaDB with `utf8mb4` support |
| Web server | Apache with `mod_rewrite` and `mod_headers` (security rules live in `.htaccess`) |
| Composer | 2.x |
| PHP extensions | `pdo_mysql`, `mbstring`, `gd` (with JPEG, PNG, GIF and WebP support), `zip`, `xml`, `xmlreader`, `xmlwriter`, `zlib` |

`composer install` will report any extension that is missing.

Production should run over HTTPS. The site sends an HSTS header and the auth code checks for HTTPS.

---

## Quick start

```bash
# 1. Get the code
git clone https://github.com/wccheng30/XPM_Website.git
cd XPM_Website

# 2. Install PHP dependencies (PhpSpreadsheet, TCPDF)
composer install

# 3. Create local config files from the examples
cp includes/database.example.php includes/database.php
cp config/email.local.example.php config/email.local.php

# 4. Edit includes/database.php (DB credentials) and config/email.local.php (SMTP password)

# 5. Create the database and tables
mysql -u <user> -p < setup/database.sql

# 6. Point your web server's document root at this folder
#    (or, for a quick local check:)
php -S localhost:8000
```

> **Local testing note:** `php -S` ignores `.htaccess`, so the security headers and
> folder protection will not be applied. Use Apache for anything beyond a quick look.

---

## Configuration

### Database: `includes/database.php`

Copy `includes/database.example.php` to `includes/database.php` and fill in:

```php
$db_config = [
    'host'     => 'localhost',
    'dbname'   => 'xpresspo_db',
    'username' => 'your_db_user',
    'password' => 'your_db_password'
];
```

This file is git-ignored and blocked from web access by `.htaccess`.

`setup/database.sql` creates a database named `xpresspo_db` by default. If you
use a different name, change it in both the SQL file and `database.php`.

### Email: `config/email_config.php` and `config/email.local.php`

- `config/email_config.php` holds the non-secret settings: recipient addresses for
programme registrations and job applications, the sender name and address, and the
SMTP host, port and security mode.
- `config/email.local.php` holds only the mailbox password (`$xpm_smtp_pass`).
It is git-ignored. Copy it from `config/email.local.example.php`.

If the password is left empty, the site falls back to PHP's built-in `mail()`.

Review the addresses in `email_config.php` before deploying a copy of the site, so
test submissions do not go to real staff mailboxes.

---

## First login and admin accounts

Everyone, including admins, signs in at `/login.php`. `/admin/` redirects there
and sends staff to the right dashboard after login.

| Role | Lands on |
|------|----------|
| `admin`, `manager` | `admin/dashboard.php` |
| `trainer` | `admin/programmes.php` |
| `user` | `account.php` |

`setup/database.sql` seeds one account with username `admin`. Its password is
set by a hash stored in that SQL file, so **anyone with access to this repository
can attempt to guess it.**

Before the site is reachable by anyone else, do one of these:

1. **Replace the hash before importing.** Generate a new one and put it in the

   `INSERT INTO users` statement in `setup/database.sql`:
2. **Or change the password immediately after import** via *My profile → Change password*

   in the admin panel (`admin/change-password.php`).

Also change the placeholder email (`admin@xpresspoint.com`) on that account.

Public registration (`register.php`) creates accounts with the lowest-privilege role.
Higher roles are managed by an admin in the admin panel under *Users*.

---

## Writable folders and uploads

The web server user needs write access to:

| Path | Used for |
|------|----------|
| `uploads/resumes/` | Résumés and cover letters submitted through the job application form (created automatically on first upload) |
| `assets/images/programmes/`, `trainers/`, `testimonials/`, `logos/`, `clients/`, `avatars/` | Images uploaded through the admin panel |

`uploads/` and `wp-content/uploads/` are git-ignored, so uploaded files are never committed.

**Privacy:** résumés contain personal data. Make sure `uploads/resumes/` is not
directly downloadable from the web (for example by denying access in the web server
config or placing a deny-all `.htaccess` in that folder), and that it is included in
your backup and data-retention plan.

Image upload folders get a generated `.htaccess` that blocks script execution.

---

## Project layout

```
.
├── index.php, programmes.php, jobs.php, ...   Public pages
├── login.php, register.php, account.php, ...  Account pages
├── admin/                                     Admin panel (dashboard, CRUD pages, exports)
│   ├── includes/                              Admin header, navigation, shared UI
│   ├── css/, js/, admin.min.css               Admin styles and scripts
│   └── build-css.php                          Builds admin.min.css
├── api/                                       JSON endpoints used by the front end
│   ├── booking-availability.php
│   ├── contact.php
│   ├── featured-programmes.php
│   └── nav-user.php
├── includes/                                  Shared PHP: auth, database, email, booking, pricing
├── config/                                    Email settings
├── partials/                                  Shared head, nav and footer
├── assets/                                    CSS, JS, images
├── setup/database.sql                         Database schema and seed data
├── composer.json                              PHP dependencies
└── .htaccess                                  Security headers and folder protection
```

### Database tables

`users`, `login_attempts`, `password_resets`, `contacts`, `programmes`,
`programme_registrations`, `programme_registration_questions`, `jobs`,
`applications`, `bookings`, `trainers`, `blacklist`, `testimonials`, `clients`,
`client_logos`.

### About the `wp-*` folders

This repository also contains a WordPress installation (`wp-admin/`,
`wp-includes/`, `wp-content/` and the root `wp-*.php` files). The custom
application described in this README is plain PHP in the repository root and does
not load WordPress. WordPress core files should be updated through WordPress itself
or removed if they are not in use.

---

## Development notes

- **Admin CSS:** after editing files in `admin/css/`, rebuild the bundle:
  ```bash
  php admin/build-css.php
  ```
- **Exports:** Excel and PDF exports in the admin panel use PhpSpreadsheet and TCPDF.
If they fail with a missing `vendor/autoload.php` error, run `composer install`.
- **Timezone:** date handling uses `Asia/Kuala_Lumpur`.
- **Security headers:** `.htaccess` sets a strict Content-Security-Policy. If you add
a script, font, image or embed from a new external host, it must be added to the
policy there or the browser will block it.

---


## License

MIT. See [LICENSE](LICENSE).
