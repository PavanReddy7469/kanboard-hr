# Email setup — Google Workspace SMTP relay

Superbee Aeronautics Pvt Ltd · `inventory.superbeeaeronautics.com/kanboard/`

Four steps, in this order. Step 3 is the one that is easy to skip and
quietly breaks every link in every email.

---

## 1. Turn on the SMTP relay in Google Admin

Sign in to **admin.google.com** as a super administrator.

**Apps → Google Workspace → Gmail → Routing → SMTP relay service → Configure**

| Field | Set it to |
|---|---|
| Description | `Kanboard PMO portal` |
| Allowed senders | **Only addresses in my domains** |
| Authentication | tick **Only accept mail from the specified IP addresses** |
| IP addresses/ranges | the public IP of the server (`curl -s ifconfig.me` on it) |
| Encryption | tick **Require TLS encryption** |

Save. Changes can take a few minutes to apply.

**Authenticate by IP, not by password.** The server then needs no
credential at all, so nothing secret goes into `config.php`, nothing has to
be rotated when somebody leaves, and no App Password is tied to one
person's account. The cost is that the IP must be static — if the server is
rebuilt or moves, come back and change it here.

Then create the sending address: **Directory → Users → Add new user**, or a
group, named `no-reply@superbeeaeronautics.com`. The relay only accepts
senders in your domain, so the From address must be a real one.

---

## 2. Point Kanboard at the relay

Edit `config.php` in the web root on the server. Replace the existing
`MAIL_*` block with:

```php
define('MAIL_CONFIGURATION', true);
define('MAIL_FROM', 'no-reply@superbeeaeronautics.com');
define('MAIL_BCC', '');
define('MAIL_TRANSPORT', 'smtp');
define('MAIL_SMTP_HOSTNAME', 'smtp-relay.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USERNAME', '');
define('MAIL_SMTP_PASSWORD', '');
define('MAIL_SMTP_HELO_NAME', 'inventory.superbeeaeronautics.com');
define('MAIL_SMTP_ENCRYPTION', 'tls');
define('MAIL_SENDMAIL_COMMAND', '/usr/sbin/sendmail -bs');
```

Notes on the three that matter:

- **Username and password stay empty.** With IP authentication the relay
  does not want them. Swift Mailer only attempts AUTH when a username is
  set, so blank means it connects and sends without one. If you ever switch
  to App Password authentication, these are the two lines that change.
- `MAIL_SMTP_HELO_NAME` must be a real hostname. Google rejects a HELO of
  `localhost`, which is what a default PHP install announces.
- Port **587** with `tls` is STARTTLS. Do not use 465 here — that is
  implicit SSL and expects `MAIL_SMTP_ENCRYPTION` to be `'ssl'`.

`config.php` is gitignored and lives only on the server, so a deploy will
not overwrite this.

---

## 3. Set the portal address — do not skip this

**Settings → Application → Application URL**

```
https://inventory.superbeeaeronautics.com/kanboard/
```

(with the trailing slash)

This is a **database setting, not a constant in `config.php`.** Until it is
filled in, `url->base()` returns `http://localhost/` and every link in
every email — the task button, the sign-in link in the welcome mail — points
at the recipient's own machine. Core's own templates silently drop their
links when it is empty; this fork's templates say so in the body instead,
so if a test email arrives telling you to set the Application URL, this is
the step that was missed.

While you are on that screen, set **Timezone** to `Asia/Kolkata`. The
reminders decide "is this due today" in the server's timezone, so a server
left on UTC will tell people a task is due today at 05:30 their time, and
on the wrong day for anything due late in the evening.

---

## 4. Schedule the daily run

The date reminders are a cron job. Without it, nothing dated is ever sent —
task assignment and comment notifications still work, because those are sent
in the request that causes them.

As the user that owns the web root (**not root** — the CLI writes to
`data/cache` and `data/debug.log`, and a root-owned file there makes the
next web request fail):

```sh
crontab -e
```

```cron
30 8 * * *  sh /var/www/kanboard-hr/ops/send-reminders.sh
```

**`sh`, not the path on its own.** The deploy's permissions pass runs
`chmod 640` over every file in the tree, so nothing in `ops/` keeps its
executable bit and calling the script directly fails with `Permission
denied` — silently, because cron mails the error to a local mailbox nobody
reads. The workflow invokes `ops/set-php-limits.sh` the same way, for the
same reason.

`ops/send-reminders.sh` runs Kanboard's own `cronjob` (overdue
notifications, project stats, automatic actions) and then this fork's
`taskmanager:reminders`.

08:30 rather than 09:00 on purpose: almost everything on a shared host is
scheduled on the hour, and mail queued at 09:00 arrives late.

---

## Testing it

**See what would be sent, without sending anything:**

```sh
cd /var/www/kanboard-hr
php ./cli taskmanager:reminders --dry-run
```

Prints a table of every reminder it would send and to whom. Sends nothing
and records nothing, so it is safe to run as often as you like.

**Rehearse a future date:**

```sh
php ./cli taskmanager:reminders --dry-run --date=2026-12-01
```

**Send for real and show what went:**

```sh
php ./cli taskmanager:reminders --show
```

**Test the transport itself** — create a throwaway user with your own
address, which triggers the welcome mail immediately.

---

## What gets sent

| When | To whom | Source |
|---|---|---|
| Account created | the new user | this fork |
| Task assigned to you, commented on, closed, reopened | the people the project notifies | Kanboard |
| Task starts today | the assignee | this fork |
| Task due in 3 days | the assignee | this fork |
| Task due today | the assignee | this fork |
| Task overdue | project members with notifications on | Kanboard |
| Project end date today or in 3 days | that project's managers and team leads | this fork |

A Kanboard **task** has a start date and a due date, and nothing else — the
end date is a property of the **project**. That is why the project row is
the only "end date" line in the table.

To change the three-day warning, edit `REMIND_BEFORE_DAYS` in
`plugins/TaskManager/Model/ReminderModel.php`.

---

## Everyone is opted in

Kanboard defaults `users.notifications_enabled` to `0`, so configuring SMTP
on its own changes nothing — every message is composed, addressed and then
dropped, with no error to explain it.

Schema migration 13 switches everyone on and gives each account an `email`
delivery type. Both halves are needed: with the switch on but no row in
`user_has_notification_types`, the delivery loop runs zero times and the
mail is dropped exactly as before.

Anyone can still turn it off for themselves under **My profile →
Notifications**. Nobody is opted out by the migration.

---

## When it does not work

Run the dry run first — it tells you whether the problem is *selection*
(nothing listed) or *delivery* (listed but nothing arrives).

| Symptom | Cause |
|---|---|
| Dry run lists nothing | No task has a start or due date within the window, or the tasks are unassigned — a task with no assignee is deliberately never mailed about |
| Lists rows, says `no (opted out or no address)` | That person has no email address on their account, or turned notifications off |
| Nothing arrives, no error | Check `data/debug.log`. Set `define('DEBUG', true);` in `config.php` to see each send logged |
| `Expected response code 220 but got 421` | The server's IP is not in the relay's allowed list, or the change has not propagated yet |
| `must issue a STARTTLS command first` | `MAIL_SMTP_ENCRYPTION` is not `'tls'` |
| `5.7.1 Invalid credentials` | A username is set. With IP authentication both username and password must be empty |
| Mail arrives, links point at `localhost` | Step 3 was skipped |
| Reminders arrive a day early or late | Server timezone. Set it in Settings → Application |
| Everyone gets two of everything | A second crontab entry. `crontab -l` on every account on the box |

Google's relay allows 10,000 messages a day per domain, which this will not
approach.
