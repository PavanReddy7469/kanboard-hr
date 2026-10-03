#!/bin/sh
#
# Raise PHP's upload limits for this application, on the PHP that actually
# serves the site.
#
#   sudo ops/set-php-limits.sh            apply, then restart the web PHP
#   sudo ops/set-php-limits.sh --check    report only, change nothing
#
# Why this exists rather than a .user.ini: a .user.ini was shipped in the web
# root and the server went on reporting a 2MB ceiling. .user.ini is read only
# by the CGI and FastCGI SAPIs. This host serves PHP through mod_php - the
# module loaded into Apache - which never reads the file at all. So the limit
# has to be set where that PHP does read it.
#
# Two layouts are handled, decided by what is actually running:
#
#   mod_php   the settings go in /etc/php/<ver>/apache2/conf.d, which is read
#             when Apache starts, so Apache is restarted (a reload does not
#             re-read php.ini).
#   php-fpm   the settings go in the FPM conf.d, and any php_admin_value in a
#             pool that pins a smaller number is raised too, because that
#             beats both the drop-in and .user.ini.
#
# Everything here is idempotent and guarded:
#
#   * the service is discovered, never guessed from the CLI PHP version. The
#     CLI and the web SAPI are separate installations and on this server they
#     disagree, which is why "systemctl restart php8.5-fpm" failed on every
#     deploy for weeks.
#   * every file it edits is backed up first.
#   * the configuration is validated BEFORE anything is restarted.
#   * if validation or the restart fails, the backups go back and the service
#     is restarted again, so a bad run leaves the server as it found it.
#
# Exit codes: 0 applied or already correct, 1 could not apply (unchanged),
# 2 applied but the restart failed and the rollback also failed - the only
# case that needs a person.

set -u

UPLOAD_MAX="100M"
POST_MAX="110M"
MEMORY="256M"
EXEC_TIME="300"

INI_NAME="99-superbee-uploads.ini"

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

say() { echo "set-php-limits: $1"; }
die() { echo "set-php-limits: ERROR: $1" >&2; exit 1; }

[ "$(id -u)" = "0" ] || die "must run as root (sudo)"

apache_ctl() {
    if command -v apachectl >/dev/null 2>&1; then apachectl "$@"
    elif command -v apache2ctl >/dev/null 2>&1; then apache2ctl "$@"
    elif command -v httpd >/dev/null 2>&1; then httpd "$@"
    else return 127
    fi
}

report_layout() {
    say "what this host has:"
    echo "    --- php-ish units ---"
    systemctl list-units --type=service --all --no-legend --plain 2>/dev/null \
        | grep -i php | sed 's/^/    /' || echo "    (none)"
    echo "    --- php config trees ---"
    ls -d /etc/php /etc/php.d /etc/php-fpm.d /etc/php[0-9]* /opt/plesk/php/*/etc \
          /opt/cpanel/ea-php*/root/etc /etc/opt/remi/php* 2>/dev/null | sed 's/^/    /'
    echo "    --- how apache runs php ---"
    apache_ctl -M 2>/dev/null | grep -iE 'php|proxy_fcgi|fcgid|lsapi' | sed 's/^/    /'
}

# ---------------------------------------------------------------- discover
#
# MODE is decided by what is running, in this order: an FPM service if there
# is one, otherwise mod_php inside Apache. Nothing is assumed from the
# filesystem alone - a /etc/php/8.3/fpm directory can sit on a host that
# serves everything through the module.

MODE=""
SERVICE=""
CONF_D=""
POOL_DIR=""
VALIDATE=""

SERVICE=$(systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null \
          | awk '{print $1}' | grep -m1 -iE '^[a-z0-9.-]*php[a-z0-9.-]*fpm\.service$')

if [ -z "$SERVICE" ]; then
    SERVICE=$(systemctl list-unit-files --type=service --no-legend --plain 2>/dev/null \
              | awk '{print $1}' | grep -m1 -iE '^[a-z0-9.-]*php[a-z0-9.-]*fpm\.service$')
fi

if [ -n "$SERVICE" ]; then
    MODE="fpm"

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
    VALIDATE="$FPM_BIN -t"

    say "mode          php-fpm"
    say "service       $SERVICE"
    say "config        $FPM_ETC"
else
    # No FPM. Is Apache serving PHP through the module?
    if apache_ctl -M 2>/dev/null | grep -qi 'php[0-9_]*_module'; then
        MODE="apache"
    else
        say "no php*fpm service, and apache does not report a php module."
        report_layout
        die "cannot tell how PHP is served on this host; nothing changed"
    fi

    # Which Apache service. Both names can exist; take the one that runs.
    for u in apache2 httpd; do
        if systemctl is-active "$u" >/dev/null 2>&1; then SERVICE="$u.service"; break; fi
    done
    [ -n "$SERVICE" ] || die "mod_php is loaded but neither apache2 nor httpd is active"

    # The module's own config tree. Debian keeps one per PHP version; if more
    # than one is installed take the highest, which is what a2enmod would
    # have enabled last. sort -V so 8.10 sorts above 8.9.
    APACHE_PHP_ETC=$(ls -d /etc/php/*/apache2 2>/dev/null | sort -V | tail -1)
    if [ -n "$APACHE_PHP_ETC" ]; then
        CONF_D="$APACHE_PHP_ETC/conf.d"                 # Debian / Ubuntu
        [ -d "$CONF_D" ] || CONF_D="$APACHE_PHP_ETC"
    elif [ -d /etc/php.d ]; then
        APACHE_PHP_ETC="/etc/php.d"                      # RHEL / Alma / Rocky
        CONF_D="/etc/php.d"
    else
        report_layout
        die "mod_php is loaded but its config tree was not found"
    fi

    VALIDATE="apache_configtest"

    say "mode          mod_php (apache module)"
    say "service       $SERVICE"
    say "config        $APACHE_PHP_ETC"
fi

apache_configtest() { apache_ctl -t >/dev/null 2>&1; }

run_validate() {
    if [ "$VALIDATE" = "apache_configtest" ]; then apache_configtest
    else $VALIDATE >/dev/null 2>&1
    fi
}

show_validate_output() {
    if [ "$VALIDATE" = "apache_configtest" ]; then apache_ctl -t 2>&1 | sed 's/^/    /'
    else $VALIDATE 2>&1 | sed 's/^/    /'
    fi
}

report_current() {
    say "currently enforced:"
    if [ "$MODE" = "fpm" ]; then
        "$FPM_BIN" -tt 2>&1 | grep -iE 'upload_max_filesize|post_max_size' | sed 's/^/    /' || true
        grep -rhoE 'php_(admin_)?value\[(upload_max_filesize|post_max_size|memory_limit)\][[:space:]]*=.*' \
            "$POOL_DIR" 2>/dev/null | sed 's/^/    pool: /' || true
    else
        # No way to ask the module directly from a shell, so show what is on
        # disk for it: the files Apache's PHP will read, newest setting last.
        grep -rhsE '^[[:space:]]*(upload_max_filesize|post_max_size|memory_limit)[[:space:]]*=' \
            "$APACHE_PHP_ETC" 2>/dev/null | sed 's/^/    /' || true
    fi
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
        rm -f "$CONF_D/$INI_NAME"
    fi
}

# 0. Is there anything to do at all? This runs on every deploy, and under
#    mod_php applying it means restarting Apache - a second where the site
#    does not answer. Doing that on every push, to write a file that is
#    already byte-for-byte correct, would be a self-inflicted outage on a
#    schedule. So: if the drop-in already says what it should and no pool
#    pins a smaller number, say so and stop without touching the service.
[ -d "$CONF_D" ] || die "no conf.d directory at $CONF_D; nothing changed"

WANTED=$(cat <<EOF
; Managed by ops/set-php-limits.sh in the kanboard-hr repository.
; Edit there, not here - a deploy rewrites this file.
upload_max_filesize = $UPLOAD_MAX
post_max_size = $POST_MAX
memory_limit = $MEMORY
max_execution_time = $EXEC_TIME
max_input_time = $EXEC_TIME
max_file_uploads = 20
EOF
)

# A pool that pins these is only "work to do" if what it pins is not already
# the number we want - otherwise every deploy would rewrite and restart.
NEEDS_POOL_WORK=0
if [ "$MODE" = "fpm" ]; then
    if grep -rhE 'php_admin_value\[(upload_max_filesize|post_max_size|memory_limit)\]' "$POOL_DIR" 2>/dev/null \
       | grep -qvE "=[[:space:]]*($UPLOAD_MAX|$POST_MAX|$MEMORY)[[:space:]]*$"; then
        NEEDS_POOL_WORK=1
    fi
fi

if [ -f "$CONF_D/$INI_NAME" ] && [ "$NEEDS_POOL_WORK" = "0" ] \
   && [ "$WANTED" = "$(cat "$CONF_D/$INI_NAME")" ]; then
    say "already correct; nothing to change, service left running"
    report_current
    exit 0
fi

# 1. The ini drop-in. Under mod_php this is the whole fix; under FPM it is
#    correct whenever nothing pins the value, and harmless when something
#    does - it simply loses to the pool, which step 2 then raises.
if [ -d "$CONF_D" ]; then
    if [ -f "$CONF_D/$INI_NAME" ]; then
        backup "$CONF_D/$INI_NAME"
    else
        INI_WAS_CREATED=1
    fi

    cat > "$CONF_D/$INI_NAME" <<EOF
; Managed by ops/set-php-limits.sh in the kanboard-hr repository.
; Edit there, not here - a deploy rewrites this file.
upload_max_filesize = $UPLOAD_MAX
post_max_size = $POST_MAX
memory_limit = $MEMORY
max_execution_time = $EXEC_TIME
max_input_time = $EXEC_TIME
max_file_uploads = 20
EOF
    say "wrote $CONF_D/$INI_NAME"
else
    die "no conf.d directory at $CONF_D; nothing changed"
fi

# 2. FPM only: anything pinning a smaller value in a pool has to be raised
#    where it sits, because php_admin_value beats both the drop-in and
#    .user.ini.
if [ "$MODE" = "fpm" ]; then
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
fi

# --------------------------------------------------------------- validate

if ! run_validate; then
    say "the resulting configuration does not parse - rolling back, nothing restarted"
    show_validate_output
    restore
    exit 1
fi

say "configuration validates"

# ---------------------------------------------------------------- restart
#
# mod_php reads php.ini once, at startup. A reload re-reads Apache's own
# config but not PHP's, so this has to be a restart for the new limit to
# take effect at all.

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
