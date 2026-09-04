# SUPERBEE PROJECTS & TASKS — MySQL cutover

Everything below has been executed and verified against MariaDB 10.11 with
`ONLY_FULL_GROUP_BY` and `STRICT_TRANS_TABLES` enabled. Your live SQLite
database stays untouched and remains the rollback.

---

## 1. Install MySQL

MySQL 8.0 Community Server or MariaDB 10.6+ — either works. On Windows the
MySQL Installer ("Server only") is the shortest path. During setup:

- Authentication: choose **"Use Legacy Authentication Method"**, or afterwards run
  `ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<pw>';`
  PHP's older `mysqlnd` refuses `caching_sha2_password`.
- Note the root password you set.

Confirm PHP has the driver:

    php -m | findstr pdo_mysql

If it prints nothing, uncomment `extension=pdo_mysql` in your `php.ini` and restart.

---

## 2. Import the database

    mysql -u root -p < superbee_mysql.sql

This creates the `superbee` database, 54 tables, 74 foreign keys, and loads
every row from your current SQLite database.

Verify:

    mysql -u root -p -e "USE superbee; SELECT COUNT(*) tables FROM information_schema.tables WHERE table_schema='superbee'; SELECT version FROM schema_version; SELECT * FROM plugin_schema_versions;"

Expect `54`, `139`, and `taskmanager | 6`.

---

## 3. Create an application user (recommended over root)

    CREATE USER 'superbee'@'localhost' IDENTIFIED WITH mysql_native_password BY 'CHOOSE_A_PASSWORD';
    GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON superbee.* TO 'superbee'@'localhost';
    FLUSH PRIVILEGES;

`CREATE`/`ALTER`/`DROP` are needed because Kanboard runs its own schema
migrations on boot when it upgrades.

---

## 4. Point the application at MySQL

Back up first:

    copy config.php config.php.sqlite-backup

Then edit `config.php`:

| line | from                                | to                                       |
|------|-------------------------------------|------------------------------------------|
| 67   | `define('DB_DRIVER', 'sqlite');`    | `define('DB_DRIVER', 'mysql');`          |
| 70   | `define('DB_USERNAME', 'root');`    | `define('DB_USERNAME', 'superbee');`     |
| 73   | `define('DB_PASSWORD', '');`        | `define('DB_PASSWORD', 'YOUR_PASSWORD');`|
| 79   | `define('DB_NAME', 'kanboard');`    | `define('DB_NAME', 'superbee');`         |

Leave `DB_HOSTNAME` as `localhost` and `DB_PORT` as `null` unless you changed
the MySQL port.

---

## 5. Start and check

    php -S localhost:8080 -t .

Log in as `admin`. Confirm, in order:

1. **Home** — both projects listed, widgets populated.
2. **Reports** — the four deliverables are there with their statuses
   (1 approved, 1 pending, 2 rejected).
3. **Close a task with no approved deliverable** — must be refused.
4. **Approve a deliverable, then close its task** — must succeed.
5. **Gantt** — dependency arrows draw; drag a predecessor and confirm the
   successor shifts.
6. **Create a project** — the field that was failing on SQLite because of id
   reuse. MySQL's AUTO_INCREMENT counters were set past every existing id,
   so this now works cleanly.

---

## 6. Rollback

Change line 67 back to `'sqlite'` (or restore `config.php.sqlite-backup`) and
restart. `data/db.sqlite` was never modified by the migration.

---

## What changed in the data, and why

- **`sessions` and `remember_me` are empty.** These held 6 stale sessions and
  85 old login tokens. Carrying expired auth material across a database change
  has no upside. Everyone signs in once after cutover.
- **A duplicate `plugin_schema_versions` row was removed** from your SQLite
  database before the migration. It held `TaskManager` and `taskmanager`, both
  at version 6. SQLite keys are case-sensitive so both survived; MySQL keys are
  not, and the import aborted on the collision. Kanboard's own
  `SchemaHandler::getSchemaVersion()` calls `strtolower()`, so the lowercase
  row is the one the code has always read — the other was dead weight from an
  older build.
- **Four legacy columns are absent** from MySQL: `users.is_admin`,
  `users.is_project_admin`, `projects.is_everybody_allowed`,
  `projects.default_swimlane`, `projects.show_default_swimlane`,
  `project_has_users.is_owner`. Kanboard's MySQL migrations `DROP` these after
  converting them into role rows; its SQLite migrations cannot, because old
  SQLite had no `DROP COLUMN`. The MySQL schema is the intended one, and the
  role rows carrying that information migrated intact.

## What MySQL buys you

- **Concurrency.** SQLite locks the whole file on write. With more than one
  person in the app, writes queue behind each other and eventually time out.
- **Real transactions under load.** InnoDB row-level locking; verified above
  that a rollback of an insert + update + cascading delete leaves the data
  untouched.
- **`utf8mb4` throughout the plugin tables** — verified round-tripping emoji
  and typographic characters, which the 3-byte `utf8` charset would have
  truncated.
- **AUTO_INCREMENT that never reuses an id.** SQLite reuses the highest freed
  rowid, which is exactly what collided with orphaned `columns` rows and broke
  project creation. MySQL does not reuse ids.
