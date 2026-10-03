#!/bin/sh
#
# Raise PHP's upload limits for this application, on the web server's PHP.
#
#   sudo ops/set-php-limits.sh            apply, then restart PHP-FPM
#   sudo ops/set-php-limits.sh --check    report only, change nothing
#
# Why this exists rather than a .user.ini: a .user.ini was shipped in the web
# root and the server went on reporting a 2MB ceiling. .user.ini cannot
# override a php_admin_value set in the FPM pool - that is the mechanism a
# host uses to impose a limit tenants are not allowed to raise - so the limit
# has to be set at the same level or higher.
#
# Everything here is idempotent and guarded:
#
#   * the FPM service name is discovered, never guessed from the CLI PHP
#     version. The two are separate installations and on this server they
#     disagree, which is why "systemctl restart php8.5-fpm" has been failing
#     on every deploy.
#   * every file it edits is backed up first.
#   * the result is validated with `php-fpm -t` BEFORE anything is restarted.
#     A config PHP-FPM cannot parse would stop the service and take the whole
#     site down, not just uploads.
#   * if validation or the restart fails, the backups go back and the service
#     is restarted again, so a bad run leaves the server as it found it.
#
# Exit codes: 0 applied or already correct, 1 could not apply (unchanged),
# 2 applied but the restart failed and the rollback also failed - the only
# case that needs a human.

set -u

UPLOAD_MAX="100M"
POST_MAX="110M"
MEMORY="256M"
EXEC_TIME="300"

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

say() { echo "set-php-limits: $1"; }
die() { echo "set-php-limits: ERROR: $1" >&2; exit 1; }

[ "$(id -u)" = "0" ] || die "must run as root (sudo)"

# ---------------------------------------------------------------- discover

# The running FPM service. Its name is the only reliable statement of which
# PHP actually serves the site; the `php` on $PATH is the CLI build and can be
# an entirely different version with its own php.ini.
SERVICE=$(systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null \
          | awk '{print $1}' | grep -m1 -E '^php[0-9.]*-fpm\.service$')

if [ -z "$SERVICE" ]; then
    SERVICE=$(systemctl list-unit-files --type=service --no-legend --plain 2>/dev/null \
              | awk '{print $1}' | grep -m1 -E '^php[0-9.]*-fpm\.service$')
fi

# Vendor builds do not use the Debian name. Plesk ships plesk-php84-fpm,
# cPanel ships ea-php83-php-fpm, Remi ships php83-php-fpm. Widen the net
# before giving up, matching anything that is both php and fpm.
if [ -z "$SERVICE" ]; then
    SERVICE=$(systemctl list-units --type=service --all --no-legend --plain 2>/dev/null \
              | awk '{print $1}' | grep -m1 -iE '^[a-z0-9.-]*php[a-z0-9.-]*fpm\.service$')
fi

if [ -z "$SERVICE" ]; then
    # Say what IS here rather than only what is not. These three answers
    # between them identify the layout: which php services exist, where the
    # config lives, and - the one that matters - whether Apache is serving
    # PHP through FPM at all or through a module, because .user.ini is only
    # read by the CGI/FastCGI SAPIs and was ignored here.
    echo "set-php-limits: no php*fpm service. Reporting what this host has instead:"
    echo "--- php-ish units ---"
    systemctl list-units --type=service --all --no-legend --plain 2>/dev/null \
        | grep -i php | sed 's/^/    /' || echo "    (none)"
    echo "--- php config trees ---"
    ls -d /etc/php /etc/php.d /etc/php-fpm.d /etc/php[0-9]* /opt/plesk/php/*/etc \
          /opt/cpanel/ea-php*/root/etc /etc/opt/remi/php* 2>/dev/null | sed 's/^/    /' \
        || echo "    (none of the usual places)"
    echo "--- how apache runs php ---"
    (apachectl -M 2>/dev/null || httpd -M 2>/dev/null || apache2ctl -M 2>/dev/null) \
        | grep -iE 'php|proxy_fcgi|fcgid|suexec|lsapi' | sed 's/^/    /' || echo "    (apache module list unavailable)"
    echo "--- other web servers ---"
    for u in litespeed lsws nginx apache2 httpd; do
        systemctl is-active "$u" >/dev/null 2>&1 && echo "    $u: active"
    done
    echo "--- php binaries ---"
    ls /usr/sbin/php-fpm* /usr/sbin/*fpm* /usr/local/sbin/*fpm* 2>/dev/null | sed 's/^/    /' || echo "    (no fpm binary on the usual paths)"
    die "no php*-fpm service found; see the layout printed above"
fi

# php8.3-fpm.service -> 8.3 ; php-fpm.service -> (empty, unversioned layout)
VERSION=$(echo "$SERVICE" | sed -n 's/^php\([0-9.]*\)-fpm\.service$/\1/p')

if [ -n "$VERSION" ] && [ -d "/etc/php/$VERSION/fpm" ]; then
    FPM_ETC="/etc/php/$VERSION/fpm"              # Debian / Ubuntu
    FPM_BIN=$(command -v "php-fpm$VERSION" || true)
elif [ -d /etc/php-fpm.d ]; then
    FPM_ETC="/etc/php-fpm"                        # RHEL / Alma / Rocky
    FPM_BIN=$(command -v php-fpm || true)
else
    die "found $SERVICE but not its configuration directory; unsupported layout"
fi

[ -n "${FPM_BIN:-}" ] || FPM_BIN=$(command -v php-fpm || true)
[ -n "$FPM_BIN" ] || die "found $SERVICE but no php-fpm binary to validate with"

POOL_DIR="$FPM_ETC/pool.d"
[ -d "$POOL_DIR" ] || POOL_DIR="/etc/php-fpm.d"
CONF_D="$FPM_ETC/conf.d"

say "service       $SERVICE"
say "config        $FPM_ETC"
say "validator     $FPM_BIN"

report_current() {
    say "currently enforced:"
    "$FPM_BIN" -tt 2>&1 | grep -iE 'upload_max_filesize|post_max_size' | sed 's/^/    /' || true
    grep -rhoE 'php_(admin_)?value\[(upload_max_filesize|post_max_size|memory_limit)\][[:space:]]*=.*' \
        "$POOL_DIR" 2>/dev/null | sed 's/^/    pool: /' || true
}

if [ "$CHECK_ONLY" = "1" ]; then
    report_current
    exit 0
fi

# ------------------------------------------------------------------- apply

STAMP=$(date -u +%Y%m%d%H%M%S)
BACKUPS=""
INI_WAS_CREATED=0

backup() {
    cp -p "$1" "$1.superbee-$STAMP.bak" || die "could not back up $1"
    BACKUPS="$BACKUPS $1"
}

restore() {
    for f in $BACKUPS; do
        if [ -f "$f.superbee-$STAMP.bak" ]; then
            mv -f "$f.superbee-$STAMP.bak" "$f"
        fi
    done

    # A file this script created did not exist before it ran, so putting the
    # server back means removing it rather than restoring anything.
    if [ "$INI_WAS_CREATED" = "1" ]; then
        rm -f "$CONF_D/99-superbee-uploads.ini"
    fi
}

# 1. The ordinary ini drop-in. Correct whenever nothing pins the value, and
#    harmless when something does - it simply loses to the pool.
if [ -d "$CONF_D" ]; then
    if [ -f "$CONF_D/99-superbee-uploads.ini" ]; then
        backup "$CONF_D/99-superbee-uploads.ini"
    else
        INI_WAS_CREATED=1
    fi

    cat > "$CONF_D/99-superbee-uploads.ini" <<EOF
; Managed by ops/set-php-limits.sh in the kanboard-hr repository.
; Edit there, not here - a deploy rewrites this file.
upload_max_filesize = $UPLOAD_MAX
post_max_size = $POST_MAX
memory_limit = $MEMORY
max_execution_time = $EXEC_TIME
max_input_time = $EXEC_TIME
max_file_uploads = 20
EOF
    say "wrote $CONF_D/99-superbee-uploads.ini"
fi

# 2. Anything pinning a smaller value in a pool has to be raised where it
#    sits: php_admin_value beats both the ini drop-in and .user.ini, which is
#    the whole reason the 2MB survived everything tried so far.
PINNED=$(grep -rlE 'php_admin_value\[(upload_max_filesize|post_max_size|memory_limit)\]' "$POOL_DIR" 2>/dev/null || true)

for f in $PINNED; do
    backup "$f"
    sed -i \
        -e "s|^[[:space:]]*php_admin_value\[upload_max_filesize\][[:space:]]*=.*|php_admin_value[upload_max_filesize] = $UPLOAD_MAX|" \
        -e "s|^[[:space:]]*php_admin_value\[post_max_size\][[:space:]]*=.*|php_admin_value[post_max_size] = $POST_MAX|" \
        -e "s|^[[:space:]]*php_admin_value\[memory_limit\][[:space:]]*=.*|php_admin_value[memory_limit] = $MEMORY|" \
        "$f"
    say "raised the pinned values in $f"
done

[ -z "$PINNED" ] && say "no pool pins these; the ini drop-in is enough"

# --------------------------------------------------------------- validate

if ! "$FPM_BIN" -t >/dev/null 2>&1; then
    say "the resulting configuration does not parse - rolling back, nothing restarted"
    "$FPM_BIN" -t 2>&1 | sed 's/^/    /'
    restore
    exit 1
fi

say "configuration validates"

# ---------------------------------------------------------------- restart

if systemctl restart "$SERVICE" 2>/dev/null; then
    say "restarted $SERVICE"
else
    say "restart of $SERVICE failed - rolling back"
    restore
    if systemctl restart "$SERVICE" 2>/dev/null; then
        say "rolled back and the service is running again"
        exit 1
    fi
    say "ROLLED BACK BUT THE SERVICE WILL NOT START - this needs a person"
    exit 2
fi

# Tidy the backups only once the service is up with the new config.
for f in $BACKUPS; do rm -f "$f.superbee-$STAMP.bak"; done

report_current
exit 0
