#!/bin/sh
# Daily reminder run, for cron.
#
#   crontab -e  (as the user that owns the web root)
#   30 8 * * *  /var/www/<web-root>/ops/send-reminders.sh
#
# 08:30 rather than a round hour: everything on a shared host is scheduled
# on the hour, and a mail that lands at 09:00 with the rest of the internet
# is a mail that arrives late.
#
# Runs as whoever owns the files, never as root - the CLI writes to
# data/cache and data/debug.log, and a root-owned file there makes the next
# web request fail with a permission error that has nothing to do with mail.
set -e

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP="$(command -v php || echo /usr/bin/php)"

cd "$APP_DIR"

# Kanboard's own daily job: overdue notifications, project stats, triggers.
"$PHP" ./cli cronjob

# This fork's: start today, due in N days, due today, project end dates.
"$PHP" ./cli taskmanager:reminders
