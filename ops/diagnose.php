<?php
/**
 * Production health check.
 *
 *   php ops/diagnose.php
 *
 * Prints one line per check: OK, WARN or FAIL, and for anything that is not
 * OK, what to do about it. Exits 1 if there is at least one FAIL, so it can
 * be used in a deploy step.
 *
 * It reads: PHP settings, the data folders, config.php, and the database.
 * It writes nothing except one temporary probe file under data/files, which
 * it removes again. Safe to run on a live site.
 *
 * Command line only - it refuses to run over HTTP, because the output names
 * usernames, folder owners and configuration.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$root = dirname(__DIR__);

$results = array();

function check($status, $label, $detail = '', $remedy = '')
{
    global $results;
    $results[] = array('status' => $status, 'label' => $label, 'remedy' => $remedy);
    printf("  [%-4s] %s%s\n", $status, $label, $detail === '' ? '' : ': '.$detail);
    if ($status !== 'OK' && $remedy !== '') {
        echo '         -> '.$remedy."\n";
    }
}

function heading($title)
{
    echo "\n".$title."\n".str_repeat('-', strlen($title))."\n";
}

function bytes($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $n = (float) $value;
    switch ($unit) {
        case 'g': return (int) ($n * 1024 * 1024 * 1024);
        case 'm': return (int) ($n * 1024 * 1024);
        case 'k': return (int) ($n * 1024);
    }
    return (int) $n;
}

function human($n)
{
    if ($n >= 1073741824) {
        return round($n / 1073741824, 1).'G';
    }
    if ($n >= 1048576) {
        return round($n / 1048576, 1).'M';
    }
    if ($n >= 1024) {
        return round($n / 1024, 1).'K';
    }
    return $n.'B';
}

function owner($path)
{
    if (! file_exists($path)) {
        return 'missing';
    }
    $stat = stat($path);
    $mode = sprintf('%o', $stat['mode'] & 0777);
    if (function_exists('posix_getpwuid')) {
        $u = @posix_getpwuid($stat['uid']);
        $g = @posix_getgrgid($stat['gid']);
        return ($u ? $u['name'] : $stat['uid']).':'.($g ? $g['name'] : $stat['gid']).' mode '.$mode;
    }
    return 'uid '.$stat['uid'].' mode '.$mode;
}

function one(PDO $pdo, $sql, array $args = array())
{
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

function all(PDO $pdo, $sql, array $args = array())
{
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

echo "Superbee / Kanboard health check\n";
echo 'root: '.$root."\n";
echo 'date: '.date('Y-m-d H:i:s T')."\n";

/* ------------------------------------------------------------------ PHP */

heading('PHP');

if (version_compare(PHP_VERSION, '7.2', '<')) {
    check('FAIL', 'PHP version', PHP_VERSION, 'Kanboard 1.2.x needs PHP 7.2 or newer.');
} else {
    check('OK', 'PHP version', PHP_VERSION.' ('.PHP_SAPI.')');
}

$required = array('pdo', 'mbstring', 'json', 'ctype', 'filter', 'session', 'dom', 'simplexml', 'fileinfo', 'openssl');
$optional = array(
    'gd' => 'avatars and pasted screenshots',
    'zip' => 'exports and plugin installs',
    'curl' => 'outgoing webhooks and integrations',
    'Zend OPcache' => 'page speed',
);

foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        check('OK', 'extension '.$ext);
    } else {
        check('FAIL', 'extension '.$ext, 'not loaded',
            'Install php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-'.$ext.' and restart php-fpm.');
    }
}

foreach ($optional as $ext => $why) {
    if (extension_loaded($ext)) {
        check('OK', 'extension '.$ext);
    } else {
        check('WARN', 'extension '.$ext, 'not loaded', 'Needed for '.$why.'.');
    }
}

/* --------------------------------------------------------------- uploads */

heading('File uploads');

if (! ini_get('file_uploads')) {
    check('FAIL', 'file_uploads', 'Off', 'Set file_uploads = On in php.ini; document attachments cannot work without it.');
} else {
    check('OK', 'file_uploads', 'On');
}

$upload = bytes(ini_get('upload_max_filesize'));
$post = bytes(ini_get('post_max_size'));
$memory = bytes(ini_get('memory_limit'));

if ($upload < 8388608) {
    check('WARN', 'upload_max_filesize', human($upload), 'Small for drawings and PDFs. 32M is a reasonable floor.');
} else {
    check('OK', 'upload_max_filesize', human($upload));
}

if ($post <= $upload) {
    check('FAIL', 'post_max_size', human($post).' <= upload_max_filesize '.human($upload),
        'A form carries the file plus its other fields, so post_max_size must be larger. When it is not, PHP throws the entire POST away and the upload fails with no error message - this is the usual cause of a silent upload failure.');
} else {
    check('OK', 'post_max_size', human($post));
}

if ($memory > 0 && $memory < $post) {
    check('WARN', 'memory_limit', human($memory).' < post_max_size '.human($post), 'Raise memory_limit to at least post_max_size.');
} else {
    check('OK', 'memory_limit', $memory <= 0 ? 'unlimited' : human($memory));
}

$maxFiles = (int) ini_get('max_file_uploads');
if ($maxFiles > 0 && $maxFiles < 10) {
    check('WARN', 'max_file_uploads', $maxFiles, 'The attachment form accepts several files at once; 20 is a sensible value.');
} else {
    check('OK', 'max_file_uploads', $maxFiles);
}

$tmp = (string) ini_get('upload_tmp_dir');
$tmp = $tmp === '' ? sys_get_temp_dir() : $tmp;
if (! is_dir($tmp) || ! is_writable($tmp)) {
    check('FAIL', 'upload temp dir', $tmp.' not writable',
        'PHP writes every upload here first. Fix the permissions, or set upload_tmp_dir to a folder the web server user owns.');
} else {
    check('OK', 'upload temp dir', $tmp);
}

$exec = (int) ini_get('max_execution_time');
if ($exec > 0 && $exec < 30) {
    check('WARN', 'max_execution_time', $exec.'s', 'Large uploads may be cut off mid-transfer.');
} else {
    check('OK', 'max_execution_time', $exec === 0 ? 'unlimited' : $exec.'s');
}

/* ----------------------------------------------------------- data folder */

heading('Data folders');

$dirs = array(
    'data' => $root.'/data',
    'data/files' => $root.'/data/files',
    'data/cache' => $root.'/data/cache',
);

foreach ($dirs as $label => $path) {
    if (! file_exists($path)) {
        check('FAIL', $label, 'missing',
            'Create it and hand it to the web server user: mkdir -p '.$path.' && chown www-data: '.$path);
    } elseif (! is_writable($path)) {
        check('FAIL', $label, 'not writable ('.owner($path).')',
            'The web server user cannot write here, so uploads fail. chown -R www-data: '.$path.' && chmod -R u+rwX '.$path);
    } else {
        check('OK', $label, owner($path));
    }
}

/* A real write: is_writable() lies under some ACL and SELinux setups. */
if (is_dir($root.'/data/files')) {
    $probe = $root.'/data/files/.diagnose-'.getmypid();
    if (@file_put_contents($probe, 'probe') === false) {
        check('FAIL', 'write probe in data/files', 'refused',
            'is_writable() said yes but the write failed - look at SELinux (restorecon -R data), a POSIX ACL, or a read-only mount.');
    } else {
        $back = @file_get_contents($probe);
        @unlink($probe);
        check($back === 'probe' ? 'OK' : 'FAIL', 'write probe in data/files',
            $back === 'probe' ? 'wrote and read back' : 'read back the wrong content');
    }
}

$savePath = (string) ini_get('session.save_path');
$savePath = $savePath === '' ? sys_get_temp_dir() : preg_replace('/^\d+;/', '', $savePath);
if (ini_get('session.save_handler') === 'files' && (! is_dir($savePath) || ! is_writable($savePath))) {
    check('FAIL', 'session.save_path', $savePath.' not writable',
        'Nobody can stay signed in if PHP cannot write session files. chown www-data: '.$savePath);
} else {
    check('OK', 'session.save_path', $savePath);
}

/* ---------------------------------------------------------------- config */

heading('Configuration');

if (! file_exists($root.'/config.php')) {
    check('FAIL', 'config.php', 'missing', 'Copy config.default.php to config.php and fill in the database settings.');
} else {
    check('OK', 'config.php', owner($root.'/config.php'));
    $stat = stat($root.'/config.php');
    if (($stat['mode'] & 0044) && function_exists('posix_getpwuid')) {
        check('WARN', 'config.php permissions', sprintf('%o', $stat['mode'] & 0777),
            'It holds the database password and is world-readable. chown root:www-data config.php && chmod 640 config.php');
    }
    require $root.'/config.php';
}

/* constants.php calls helpers defined in app/functions.php, which normally
   arrives through Composer's autoloader. Load whichever is available. */
if (file_exists($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
} elseif (file_exists($root.'/app/functions.php')) {
    require_once $root.'/app/functions.php';
}

require $root.'/app/constants.php';

if (! defined('APPLICATION_URL') || APPLICATION_URL === '') {
    check('WARN', 'APPLICATION_URL', 'not set',
        'Links in notification emails and in the RSS/iCal feeds are built from it. Left empty they come out as bare paths and do not work outside the browser. Set it in config.php to the site address including the trailing slash.');
} elseif (substr(APPLICATION_URL, -1) !== '/') {
    check('WARN', 'APPLICATION_URL', APPLICATION_URL, 'Add the trailing slash, otherwise generated links lose the last path segment.');
} elseif (strpos(APPLICATION_URL, 'http://') === 0) {
    check('WARN', 'APPLICATION_URL', APPLICATION_URL,
        'It is http://. Cookies marked secure are not sent over http, so sign-in will not stick. Use https://.');
} else {
    check('OK', 'APPLICATION_URL', APPLICATION_URL);
}

check(DEBUG ? 'FAIL' : 'OK', 'DEBUG', DEBUG ? 'on' : 'off',
    'Turn DEBUG off in production - it writes stack traces and SQL into a log file inside the web root.');

if (defined('SESSION_DURATION')) {
    check('OK', 'SESSION_DURATION', SESSION_DURATION === 0
        ? '0 (the session cookie dies when the browser closes; Remember me covers the rest)'
        : SESSION_DURATION.'s');
}

check(REMEMBER_ME_AUTH ? 'OK' : 'WARN', 'REMEMBER_ME_AUTH', REMEMBER_ME_AUTH ? 'enabled' : 'disabled',
    'With it disabled the Remember me tick box on the login page does nothing.');

if (defined('ENABLE_HSTS')) {
    check(ENABLE_HSTS ? 'OK' : 'WARN', 'ENABLE_HSTS', ENABLE_HSTS ? 'on' : 'off',
        'Worth turning on once the site is reliably reachable over https.');
}

if (defined('MAIL_TRANSPORT')) {
    $configured = MAIL_TRANSPORT !== 'mail' || (defined('MAIL_FROM') && MAIL_FROM !== '');
    check($configured ? 'OK' : 'WARN', 'mail', MAIL_TRANSPORT.(defined('MAIL_FROM') && MAIL_FROM !== '' ? ' from '.MAIL_FROM : ''),
        'Without a working transport nobody receives task notifications or a password-reset link, and the only way back into a locked-out account is the database.');
}

/* -------------------------------------------------------------- database */

heading('Database');

$pdo = null;

try {
    if (DB_DRIVER === 'sqlite') {
        $pdo = new PDO('sqlite:'.DB_FILENAME);
    } elseif (DB_DRIVER === 'postgres') {
        $pdo = new PDO('pgsql:host='.DB_HOSTNAME.';dbname='.DB_NAME, DB_USERNAME, DB_PASSWORD);
    } else {
        $port = defined('DB_PORT') && DB_PORT ? ';port='.DB_PORT : '';
        $pdo = new PDO('mysql:host='.DB_HOSTNAME.$port.';dbname='.DB_NAME.';charset=utf8mb4', DB_USERNAME, DB_PASSWORD);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    check('OK', 'connection', DB_DRIVER.' as '.(defined('DB_USERNAME') ? DB_USERNAME : '?'));
} catch (Exception $e) {
    check('FAIL', 'connection', $e->getMessage(), 'Nothing below can be checked until the database is reachable.');
}

if ($pdo !== null) {
    /* Core schema version. PicoDb keeps it in its own one-row table; the
       version the code expects is the VERSION constant in the schema file
       for this driver. */
    try {
        $have = (int) one($pdo, 'SELECT version FROM schema_version');

        $schemaFile = $root.'/app/Schema/'.ucfirst(DB_DRIVER === 'postgres' ? 'postgres' : DB_DRIVER).'.php';
        $want = 0;
        if (file_exists($schemaFile)) {
            require_once $schemaFile;
            if (defined('Schema\VERSION')) {
                $want = (int) constant('Schema\VERSION');
            }
        }

        if ($want === 0 || $have === $want) {
            check('OK', 'core schema', 'version '.$have);
        } elseif ($have < $want) {
            check('FAIL', 'core schema', 'database at '.$have.', code expects '.$want,
                'The migration has not run, so the code is querying columns that do not exist yet. Run: php cli db:migrate');
        } else {
            check('FAIL', 'core schema', 'database at '.$have.', code expects '.$want,
                'The database is ahead of the code - an older checkout was deployed over a newer database. Deploy the matching commit rather than migrating backwards.');
        }
    } catch (Exception $e) {
        check('WARN', 'core schema', $e->getMessage());
    }

    /* Plugin schema versions */
    foreach (glob($root.'/plugins/*/Schema/Mysql.php') as $file) {
        $plugin = basename(dirname(dirname($file)));
        preg_match_all('/function\s+version_(\d+)/', (string) file_get_contents($file), $m);
        $want = empty($m[1]) ? 0 : max(array_map('intval', $m[1]));

        try {
            $have = one($pdo, 'SELECT version FROM plugin_schema_versions WHERE plugin = ?', array($plugin));
            $have = $have === false ? -1 : (int) $have;
        } catch (Exception $e) {
            $have = -1;
        }

        if ($have === -1) {
            check('WARN', 'plugin '.$plugin, 'no schema row yet',
                'Plugin migrations run on the first page request. Open the site once and re-run this check.');
        } elseif ($have < $want) {
            check('FAIL', 'plugin '.$plugin, 'database at '.$have.', code ships version_'.$want,
                'The migration has not run, so the new columns are missing and anything that reads them fails. Open the site once as an administrator, then re-run this check.');
        } else {
            check('OK', 'plugin '.$plugin, 'version '.$have);
        }
    }

    /* Users */
    $users = all($pdo, 'SELECT id, username, email, role, is_active FROM users ORDER BY id');
    $admins = array();
    foreach ($users as $u) {
        if ($u['role'] === 'app-admin' && $u['is_active']) {
            $admins[] = $u['username'].' <'.$u['email'].'>';
        }
    }

    if (empty($admins)) {
        check('FAIL', 'administrators', 'none active',
            'Nobody can reach Settings, Users or New Project, and nobody can add members to a project. Promote an account: UPDATE users SET role = \'app-admin\' WHERE username = \'...\';');
    } else {
        check('OK', 'administrators', implode(', ', $admins));
    }

    $disabled = 0;
    foreach ($users as $u) {
        if (! $u['is_active']) {
            $disabled++;
        }
    }
    check('OK', 'user accounts', count($users).' total, '.$disabled.' disabled');

    foreach ($users as $u) {
        if ($u['is_active'] && ($u['email'] === '' || $u['email'] === null)) {
            check('WARN', 'user '.$u['username'], 'no email address',
                'They cannot receive notifications and cannot reset their own password.');
        }
    }

    /* Left-over default account */
    $adminRow = null;
    foreach ($users as $u) {
        if ($u['username'] === 'admin') {
            $adminRow = $u;
        }
    }
    if ($adminRow !== null && $adminRow['is_active']) {
        check('WARN', 'default admin account', 'username "admin" is still active',
            'Every Kanboard install ships with it and its default password is published. Once a named administrator can sign in, disable it: UPDATE users SET is_active = 0 WHERE username = \'admin\';');
    } else {
        check('OK', 'default admin account', $adminRow === null ? 'removed' : 'disabled');
    }

    /* Forced password change */
    try {
        $pending = (int) one($pdo, 'SELECT COUNT(*) FROM users WHERE must_change_password = 1 AND is_active = 1');
        check('OK', 'forced password change', $pending.' account(s) will be asked at next sign-in');
    } catch (Exception $e) {
        check('FAIL', 'forced password change', 'column must_change_password is missing',
            'The TaskManager migration that adds it has not run, so accounts created with the shared default password are never asked to change it. Open the site once as an administrator, then re-run this check.');
    }

    /* Projects */
    $projects = all($pdo, 'SELECT id, name, identifier, is_active FROM projects ORDER BY id');
    check('OK', 'projects', count($projects).' total');

    $orphans = array();
    foreach ($projects as $p) {
        try {
            $managers = (int) one($pdo,
                'SELECT COUNT(*) FROM project_has_users u JOIN users s ON s.id = u.user_id'
                .' WHERE u.project_id = ? AND u.role = \'project-manager\' AND s.is_active = 1',
                array($p['id']));
        } catch (Exception $e) {
            $managers = -1;
        }
        if ($managers === 0) {
            $orphans[] = $p['name'].' (#'.$p['id'].')';
        }
    }

    if ($orphans) {
        check('WARN', 'projects with no manager', implode(', ', $orphans),
            'On these projects only an application administrator can add members, edit columns or create tasks - everyone else gets Access Forbidden. Give somebody the project-manager role on each.');
    } else {
        check('OK', 'project managers', 'every project has at least one');
    }

    /* Project codes against the MC/FW/HI scheme */
    $bad = array();
    $dupes = array();
    $seen = array();
    foreach ($projects as $p) {
        $code = strtoupper((string) $p['identifier']);
        if ($code === '') {
            $bad[] = $p['name'].' (no code)';
            continue;
        }
        if (! preg_match('/^(MC|FW|HI)\d{2,}$/', $code)) {
            $bad[] = $p['name'].' ('.$code.')';
        }
        if (isset($seen[$code])) {
            $dupes[] = $code;
        }
        $seen[$code] = true;
    }

    check($bad ? 'WARN' : 'OK', 'project codes',
        $bad ? count($bad).' off-scheme: '.implode(', ', array_slice($bad, 0, 8)) : 'all match MC/FW/HI plus a number',
        'These predate the aircraft-type scheme. Rename them in Project settings if the codes matter for reporting.');

    check($dupes ? 'FAIL' : 'OK', 'duplicate project codes',
        $dupes ? implode(', ', array_unique($dupes)) : 'none',
        'Task codes are derived from the project code, so duplicates produce colliding task codes. Rename one of each pair.');

    /* Task codes */
    $tasks = (int) one($pdo, 'SELECT COUNT(*) FROM tasks');
    $noRef = (int) one($pdo, 'SELECT COUNT(*) FROM tasks WHERE reference IS NULL OR reference = \'\'');
    $short = (int) one($pdo, 'SELECT COUNT(*) FROM tasks WHERE reference <> \'\' AND reference NOT LIKE \'%-%\'');
    check('OK', 'tasks', $tasks.' total');

    if ($noRef > 0 || $short > 0) {
        check('WARN', 'task codes', $noRef.' without a code, '.$short.' in the old short format',
            'Take a backup (php ops/backup.php <folder>), then run php ops/renumber-task-codes.php to see the plan and --apply to carry it out.');
    } else {
        check('OK', 'task codes', 'all in PROJECT-NNN form');
    }

    /* Sample data left behind */
    $demo = (int) one($pdo,
        'SELECT COUNT(*) FROM projects WHERE LOWER(name) LIKE \'%demo%\' OR LOWER(name) LIKE \'%test%\''
        .' OR LOWER(name) LIKE \'%sample%\' OR LOWER(name) LIKE \'%example%\'');
    if ($demo > 0) {
        check('WARN', 'sample projects', $demo.' project name(s) look like demo data',
            'Delete them so the live site starts clean.');
    } else {
        check('OK', 'sample projects', 'none found');
    }

    /* Attachments recorded in the database but missing from disk */
    try {
        $rows = all($pdo, 'SELECT id, name, path FROM task_has_files');
        $missing = 0;
        foreach ($rows as $r) {
            if ($r['path'] !== '' && ! file_exists($root.'/data/files/'.$r['path'])) {
                $missing++;
            }
        }
        if ($missing > 0) {
            check('FAIL', 'task attachments', $missing.' of '.count($rows).' recorded files are not on disk',
                'The database row survived but the file did not - usually a deploy that replaced data/files, or an upload that half-succeeded. Restore data/files from a backup; until then those download links 404.');
        } else {
            check('OK', 'task attachments', count($rows).' file(s), all present on disk');
        }
    } catch (Exception $e) {
        check('WARN', 'task attachments', $e->getMessage());
    }
}

/* --------------------------------------------------------------- summary */

$fail = 0;
$warn = 0;
foreach ($results as $r) {
    if ($r['status'] === 'FAIL') {
        $fail++;
    } elseif ($r['status'] === 'WARN') {
        $warn++;
    }
}

echo "\n".str_repeat('=', 62)."\n";
printf("%d checks: %d OK, %d WARN, %d FAIL\n", count($results), count($results) - $warn - $fail, $warn, $fail);

if ($fail > 0) {
    echo "\nFix first:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo '  - '.$r['label'].($r['remedy'] === '' ? '' : ' - '.$r['remedy'])."\n";
        }
    }
}

echo "\nNot covered here - check these from a browser against the live host:\n";
echo "  - /kanboard/.git/config must return 403 or 404, not the file\n";
echo "  - /kanboard/database/*.sql must return 403 or 404\n";
echo "  - the login page must load over https with a valid certificate\n";

exit($fail > 0 ? 1 : 0);
