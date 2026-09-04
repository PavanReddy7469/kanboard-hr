# SUPERBEE PROJECTS & TASKS — production deployment

Current state: PHP 8.4 built-in dev server, plain HTTP, MySQL 8 on the same
Windows machine. That is a demo setup. This is what has to change and why.

---

## 1. Replace the web server (blocking)

`php -S` is PHP's development server. Its own manual says not to use it in
production, and it is **single-threaded** — one slow request blocks every
other user. With more than one person in the app this is felt immediately.

Pick one:

| | Effort | Notes |
|---|---|---|
| **A. IIS on Windows** | Low | MySQL is already here. `web.config` ships with the app. Stays on one machine. |
| **B. Nginx + PHP-FPM on Linux** | Medium | The standard deployment. Easier TLS, easier backups, better long-term. |
| **C. Docker** | Medium | The bundled `docker-compose.mysql.yml` builds *stock* Kanboard, not this fork. It would need rewriting to mount `app/` and `plugins/`. Don't use it as-is. |

**A is recommended** if this stays an internal tool on hardware you already
have. **B** if it will be reached by more than a handful of people.

### A — IIS, step by step

1. Server Manager → Add Roles → **Web Server (IIS)**, with **CGI** enabled.
2. Install **URL Rewrite** (Microsoft download) — the app's `web.config`
   depends on it.
3. IIS Manager → server node → **Handler Mappings** → *Add Module Mapping*:
   - Request path `*.php`
   - Module `FastCgiModule`
   - Executable: full path to `php-cgi.exe`
   - Name: `PHP_via_FastCGI`
4. Move the app out of the user profile — `C:\inetpub\superbee` is the
   conventional home. `C:\Users\pavan\...` is not a production location.
5. Add a Site pointing at that folder, binding port 80 to start.
6. Grant the app pool identity (`IIS AppPool\<name>`) **modify** on `data\`
   only, and **read** on everything else.

### Verify before going further

    php -m               (pdo_mysql present)
    php -i | findstr display_errors     (must be Off)

---

## 2. TLS (blocking)

Right now logins cross the network in clear text. `ENABLE_HSTS` is already
`true` in `config.php`, which does nothing without HTTPS.

- **Internal only** → issue a certificate from your AD Certificate Services,
  or generate one and distribute the root to staff machines.
- **Reachable from outside** → real certificate via Let's Encrypt
  (`win-acme` on IIS handles issue *and* renewal).

Then bind 443, and add an HTTP→HTTPS redirect rule. Do not skip the
redirect; a bookmarked `http://` link silently downgrades everyone.

---

## 3. Database

Already done: dedicated `superbee` user rather than root.

**Still to do:**

- **Change the password.** The current one has appeared in a chat transcript
  and in screenshots.
- **Backups.** There are none. A daily dump plus retention:

      mysqldump -u superbee -p --single-transaction --routines superbee > superbee_%DATE%.sql

  Schedule it in Task Scheduler, write to a *different* drive or a network
  share, and keep at least 14 days. A backup on the same disk is not a backup.
- **Test a restore.** An untested backup is a guess. Restore into a scratch
  schema once and confirm the row counts.

---

## 4. Application config

In `config.php`:

| setting | value | why |
|---|---|---|
| `DEBUG` | `false` | already correct |
| `ENABLE_HSTS` | `true` | already set — becomes real once TLS is on |
| `LOG_DRIVER` | `file` or `stderr` | `system` is awkward to read on Windows |
| `SESSION_DURATION` | consider a limit | `0` means until browser close |
| `MAIL_TRANSPORT` | configure SMTP | notifications are silently dead otherwise |

File permissions: `config.php` holds the database password in plain text.
Restrict it to the app pool identity and administrators. Nobody else.

---

## 5. Already handled — don't re-solve these

- `data/` is blocked from the web on **both** servers: `data/.htaccess` for
  Apache, `data/web.config` (`accessPolicy="None"`) for IIS. Verified.
- Sessions are stored in the database (`SESSION_HANDLER = 'db'`), so they
  survive restarts and work behind a load balancer later.
- Foreign keys are enforced — 74 of them, InnoDB throughout.

---

## 6. Housekeeping before you move

- `data/` holds **11 stale SQLite backups** from the migration work. They are
  not web-reachable, but they are complete copies of the old database
  including password hashes. Move them somewhere off this machine, then
  delete them from `data/`.
- `_to_delete/` and `scratch/` are development leftovers. Neither belongs on
  a server.

---

## 7. Open risks, not deployment steps

- ~~Role denial is unverified.~~ **Verified.** Logged in as a Role: User
  account and issued approve, reject, close-task, user-administration and
  settings requests, each carrying a valid CSRF token from that user's own
  session. All five returned **HTTP 403**, and no data changed. The refusal
  is the server-side role check, not the token check.
- **`isAdmin()` covers both Administrator *and* Manager**, so an app-manager
  can approve reports on any project, including ones they are not a member
  of. Decide whether that is intended.
- **~32 core files are modified**, so Kanboard security patches cannot be
  applied cleanly. This grows more expensive over time. The fix is to move
  what remains into the plugin using template overrides and hooks.

---

## Order of work

1. Change the database password
2. Set up backups, and test a restore
3. Stand up IIS (or Nginx), move the app out of the user profile
4. TLS + HTTP→HTTPS redirect
5. Move the stale backups off the machine
