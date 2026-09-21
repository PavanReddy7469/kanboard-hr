<?php
/**
 * Router for PHP's built-in server:  php -S 0.0.0.0:8080 router.php
 *
 * Without it the built-in server hands out any file that exists under the
 * document root, with no authentication and no way to configure exceptions.
 * That is how the old SQLite database - every password hash in it - and a
 * plain-text copy of config.php were both downloadable from the LAN.
 *
 * A real web server should enforce this instead; these rules exist so the
 * pilot is not one stray filename away from leaking the database.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rawurldecode($path);

/* Serve the application for anything that is not an existing file. */
$file = realpath(__DIR__.$path);
$root = realpath(__DIR__);

if ($file === false || strpos($file, $root) !== 0 || is_dir($file)) {
    return false;
}

/* Directories that hold data, never public assets. */
$blockedDirs = array('/data/', '/mysql/', '/database/', '/.git/', '/tests/', '/vendor/');
$rel = str_replace('\\', '/', substr($file, strlen($root)));

foreach ($blockedDirs as $dir) {
    if (stripos($rel, $dir) === 0) {
        /* data/files holds user uploads, which Kanboard serves through its
           own controller after checking permissions - never directly. */
        header('HTTP/1.1 404 Not Found');
        return true;
    }
}

/* Extensions that are either credentials, data, or source we never publish.
   Note .php is deliberately absent: the app is PHP. What matters is that a
   file like config.php.backup does not end in .php and so would be handed
   out as plain text - the suffix check below covers that. */
$blockedExt = array('sql','sqlite','sqlite-wal','sqlite-shm','db','log','env','pem','key',
                    'bak','old','orig','swp','yml','yaml','lock','md','dist','ini','sh','bat');

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (in_array($ext, $blockedExt, true) || preg_match('/config\.php\./i', basename($file))) {
    header('HTTP/1.1 404 Not Found');
    return true;
}

/* Dotfiles (.htaccess, .gitignore, .env) */
if (strpos(basename($file), '.') === 0) {
    header('HTTP/1.1 404 Not Found');
    return true;
}

return false;
