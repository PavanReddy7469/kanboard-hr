# SUPERBEE PROJECTS & TASKS

Project and task management for SUPERBEE Aeronautics — a fork of
[Kanboard](https://github.com/kanboard/kanboard) 1.2.50 with a rebuilt
interface and a task-completion approval workflow.

## Stack

| | |
|---|---|
| PHP | 8.4 |
| Database | MySQL 8 (`superbee` schema; core schema 139, TaskManager plugin schema 6) |
| Base | Kanboard 1.2.50 (MIT) |

## What this fork adds

**Completion reports.** Assignees submit a live link or Google Drive
document as evidence that a task is finished. An administrator or the
project's manager reviews it. A task **cannot** be closed until an
approved report backs it — the gate lives in `TaskStatusModel::close()`,
not in the UI, so it holds regardless of how the close is attempted.
Withdrawing an approval reopens the task, because a closed task with
nothing approved is exactly the state the feature exists to prevent.

**Plugins**

| plugin | purpose |
|---|---|
| `TaskManager` | grid views, dashboard, milestones, task lists, dependencies, time tracking, deliverables |
| `Gantt` | rewritten chart with dependency arrows and date markers |
| `Calendar` | calendar views |
| `Superbee` | design tokens, application shell, styling that brings stock Kanboard pages into the design system |

## Local setup

1. Install PHP 8.4 and MySQL 8. Enable `pdo_mysql` in `php.ini` — it ships
   commented out, and the app fails with *"PHP extension required"* until
   you uncomment it.
2. Create the database:

       mysql -u root -p < mysql/superbee_mysql.sql

3. Copy `config.default.php` to `config.php` and set `DB_DRIVER` to
   `mysql`, plus the database name, user and password.
4. Serve it. `php -S localhost:8080 -t .` works for development only —
   it is single-threaded, so eighteen assets arrive one after another and
   a page takes seconds. See `DEPLOYMENT.md` before anyone else uses it.

## Documentation

- `DEPLOYMENT.md` — what has to change before this leaves a laptop
- `mysql/CUTOVER.md` — the SQLite to MySQL migration, and how to roll back

## Not in version control

`config.php` (database password in plain text), `data/` (live database,
uploads, backups) and `mysql/*.sql` (dumps containing password hashes).
See `.gitignore`.

## Licence

MIT, inherited from Kanboard. Copyright (c) Frédéric Guillot for the
original work.
