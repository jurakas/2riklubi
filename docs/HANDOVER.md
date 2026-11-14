# Handover record

Filled in by the owner at the end of [`DEPLOYMENT-ZONE.md`](DEPLOYMENT-ZONE.md) (step 11).

**Never write passwords, private keys or recovery codes into this file or anywhere in the repository.** For every secret, record only *where* it is stored (for example "club password manager, entry 'Zone my.zone.eu'") and *who* has access.

Handover date: ____________  Handed over by: ____________  Received by (club): ____________

---

## 1. Site

| Item | Value |
|---|---|
| Production URL | `https://<domain>/` (EN: `https://<domain>/en/`) |
| Canonical host | `<domain>` / `www.<domain>` (circle one) |
| Admin URL | `https://<domain>/wp-admin/` |
| Old address redirect | `ariklubi.riiska.ee` → `https://<domain>/` (301): yes / no / requested on ____ |
| Source repository | ____________ (URL; who has access) |

## 2. Accounts

| Account | Login / identifier | Owner (person) | Secret stored in | 2FA / recovery |
|---|---|---|---|---|
| Zone account (my.zone.eu) | | | | |
| Domain registrar (if not Zone) | | | | |
| DNS provider (if not Zone) | | | | |
| SSH access to virtual server | user@host: | key holders: | (public key only on server) | |
| Database | name: / user: / host: | | | |
| WordPress Administrator (owner, emergency) | | | | |
| WordPress Editor | | | | |
| WordPress Editor | | | | |
| Club email `ariklubi@valgamaa.ee` (WP admin email) | | | | |

## 3. Server setup

| Item | Value |
|---|---|
| Zone virtual server | ____________ |
| Web root | `~/domeenid/www.<domain>/htdocs` (confirm) |
| Source copy on server (outside web root) | `~/ariklubi-src` (confirm) |
| PHP version / limits | 8.3 / upload 64M / post 64M / memory 256M |
| TLS certificate | Let's Encrypt, auto-renew: yes / no |
| Cron job | `*/15 * * * *` `cd <web root> && wp cron event run --due-now --quiet` |
| `wp-config.php` constants | `WP_ENVIRONMENT_TYPE=production`, `DISALLOW_FILE_EDIT`, `WP_AUTO_UPDATE_CORE`, `DISABLE_WP_CRON` set: yes / no |
| Backups | Zone automatic backups, retention: ______; test restore done on ______ |

## 4. Software

| Component | Notes |
|---|---|
| WordPress | Auto-updates on (core, plugins, themes, translations) |
| Theme | GeneratePress (parent) + `ariklubi` (child) |
| Must-use plugin | `ariklubi-core` (CPTs Albums/Members, shortcodes, hardening) |
| Plugins | Polylang, Limit Login Attempts Reloaded (nothing else) |

## 5. Contacts

| Role | Name | Phone | Email |
|---|---|---|---|
| Website administrator (club side) | | | |
| Technical owner / builder | | | |
| Club contact person | Anneli Kattai | +372 528 0802 | anneli.kattai@valgamaa.ee |
| Zone support | Zone help desk | +372 696 3300 (verify) | info@zone.ee (verify), my.zone.eu |

Also write the website administrator's contact into section 12 of the admin guides you hand to the editors.

## 6. Handover checklist

- [ ] Go-live checklist in `DEPLOYMENT-ZONE.md` completed.
- [ ] All passwords transferred via password manager or in person (not by plain email).
- [ ] Zone account ownership/billing contact transferred to the club (or agreed otherwise).
- [ ] Editors have logged in and changed their passwords.
- [ ] Editors have received `ADMIN-GUIDE.et.md` / `ADMIN-GUIDE.en.md`.
- [ ] The owner's emergency Administrator account is documented above.
- [ ] Open questions from PLAN.md §8 answered or listed here: ____________

<!--
Screenshot placeholders used in this guide: none.
-->
