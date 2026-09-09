# SUPERBEE PROJECTS & TASKS — LAN pilot checklist

Running the pilot from a laptop. Work through this in order; each step is
verifiable before you move on.

Tell the team up front: **available during working hours, down when the
laptop is away.** Set that expectation before they depend on it, not after.

---

## 1. Power — stop the laptop killing the server

Settings → System → Power & battery → Screen and sleep:

- Plugged in, turn off screen after: **anything**
- Plugged in, put device to sleep after: **Never**

Control Panel → Power Options → Choose what closing the lid does:

- When plugged in: **Do nothing**

Sleep is the single most common reason a laptop-hosted service "randomly
goes down".

---

## 2. IIS with PHP

Already done: `pdo_mysql` and `opcache` are enabled in `php.ini`.
`php-cgi.exe` is present, which is what IIS needs.

1. Control Panel → Programs → **Turn Windows features on or off**
   - Internet Information Services
   - → World Wide Web Services → Application Development Features → **CGI**
2. Install **URL Rewrite** from Microsoft — the app's `web.config` needs it.
3. IIS Manager → server node → **Handler Mappings** → *Add Module Mapping*:

   | field | value |
   |---|---|
   | Request path | `*.php` |
   | Module | `FastCgiModule` |
   | Executable | `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php-cgi.exe` |
   | Name | `PHP_via_FastCGI` |

**Verify:** create `C:\inetpub\wwwroot\t.php` containing `<?php phpinfo();`
and open `http://localhost/t.php`. Confirm `pdo_mysql` and `Zend OPcache`
both appear, then **delete the file** — it exposes your configuration.

---

## 3. Move the application off your user profile

    C:\Users\pavan\kanboard-hr   ->   C:\inetpub\superbee

A service should not run from a user profile. Then in IIS Manager:

- Add a Site → physical path `C:\inetpub\superbee` → port 80
- Permissions on that folder:
  - `IIS AppPool\<your-app-pool>` — **Modify** on `data\` only
  - **Read & execute** on everything else

Update the git remote path if you moved the repo, and keep working from the
new location so you do not edit one copy and serve another.

---

## 4. Firewall — nothing reaches you until this is done

Run PowerShell **as Administrator**:

    New-NetFirewallRule -DisplayName "SUPERBEE HTTP"  -Direction Inbound -Protocol TCP -LocalPort 80  -Action Allow -Profile Domain,Private
    New-NetFirewallRule -DisplayName "SUPERBEE HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -Profile Domain,Private

`Private` and `Domain` only — deliberately **not** `Public`, so it stays
closed on café and hotel Wi-Fi.

---

## 5. An address that does not change

    hostname          # your machine name
    ipconfig          # IPv4 address

Give people **`http://<your-machine-name>/`**, not the IP. Windows resolves
machine names on a LAN, so the link keeps working when DHCP hands you a
different address. If you prefer the IP, set a DHCP reservation on the
router first.

Prefer Ethernet over Wi-Fi if there is a port near you. Wi-Fi drops look
like application faults to users.

---

## 6. HTTPS

Logins cross the LAN in clear text without it. Internal does not mean safe.

    # PowerShell as Administrator
    New-SelfSignedCertificate -DnsName "<your-machine-name>" -CertStoreLocation "cert:\LocalMachine\My"

Bind it in IIS (Site → Bindings → Add → https, port 443, select the
certificate), then add an HTTP→HTTPS redirect rule.

Browsers will warn on a self-signed certificate. Either accept the warning
during the pilot, or have IT push the certificate to staff machines. If your
company has AD Certificate Services, issue it from there instead — no
warnings.

---

## 7. Backups — before anyone logs in, not after

The pilot is the first time this holds work that is not yours. If the laptop
disk fails, it all goes.

Create `C:\inetpub\superbee\backup.bat`:

    @echo off
    set STAMP=%date:~-4%%date:~3,2%%date:~0,2%
    "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump" -u superbee -pYOURPASSWORD --single-transaction --routines superbee > "D:\superbee-backups\superbee_%STAMP%.sql"
    forfiles /p "D:\superbee-backups" /m *.sql /d -14 /c "cmd /c del @file"

Task Scheduler → daily, 7pm, **Run whether user is logged on or not**.

Three rules:
- Write to a **different disk**, a network share, or a synced cloud folder.
  A backup on the same laptop is not a backup.
- **Test a restore once.** Import into a scratch schema and compare row
  counts. An untested backup is a guess.
- That file contains the database password — restrict it to your account.

---

## 8. Accounts

People & Roles → New User, one per person. Roles:

| role | can |
|---|---|
| Administrator | everything, including approving completion reports |
| Manager | approve reports on **any** project — verify this is what you want |
| User | submit reports; cannot approve, cannot close tasks |

Verified: a User account receives HTTP 403 on approve, reject, close-task,
user administration and settings — even with a valid session token. The
approval gate is enforced server-side, not just hidden in the interface.

Have each person set their own password at first login. Do not reuse one
shared password.

---

## 9. What to watch during the pilot

Things that only appear with real people on real data:

- Two people editing the same task at once
- Whether notification email actually sends (`MAIL_TRANSPORT` is currently
  `mail`, which usually sends nothing on Windows — configure SMTP)
- Timezones, if anyone is in a different one
- Permission edges you did not anticipate
- Page speed with several people clicking at once

Keep a running list. The pilot's job is to produce that list.

---

## 10. Before it becomes permanent

Move off the laptop to a machine that stays on. The pilot's findings are
the argument for it — collected evidence beats asking up front.

Also outstanding:
- Change the database password (it has appeared in a chat transcript)
- Move `_archive-sqlite-backups/` off this machine — 11 full copies of the
  old database, password hashes included
- ~34 modified core files block clean Kanboard security updates; worth
  addressing before the first upgrade, not before the pilot
