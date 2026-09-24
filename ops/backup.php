<?php
/**
 * Nightly database backup.
 *
 *   php ops/backup.php "D:\SuperbeeBackups"
 *
 * Writes a timestamped .sql dump to the folder given, and keeps the most
 * recent KEEP files. Give it a folder OUTSIDE the application directory: a
 * dump inside the web root is downloadable by anyone who can reach the site,
 * and it contains every password hash.
 *
 * Run from the command line only - it refuses to run over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

const KEEP = 14;

$root = dirname(__DIR__);
require $root.'/config.php';

$dir = isset($argv[1]) ? rtrim($argv[1], '\\/') : '';

if ($dir === '') {
    fwrite(STDERR, "usage: php ops/backup.php <destination-folder>\n");
    exit(1);
}

if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) {
    fwrite(STDERR, "cannot create $dir\n");
    exit(1);
}

/* Refuse to write inside the application: that is the mistake this script
   exists to prevent. */
if (strpos(realpath($dir), realpath($root)) === 0) {
    fwrite(STDERR, "refusing to write backups inside the application directory - anything there is downloadable over the network\n");
    exit(1);
}

$target = $dir.DIRECTORY_SEPARATOR.'kanboard-'.date('Ymd-His').'.sql';

try {
    $pdo = new PDO('mysql:host='.DB_HOSTNAME.';dbname='.DB_NAME.';charset=utf8mb4',
                   DB_USERNAME, DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (Exception $e) {
    fwrite(STDERR, "database connection failed\n");
    exit(1);
}

$fh = fopen($target, 'w');
fwrite($fh, "-- SUPERBEE backup ".date('c')."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

$rowTotal = 0;

foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);
    fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n".$create[1].";\n");

    $stmt = $pdo->query('SELECT * FROM `'.$table.'`');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vals = array();
        foreach ($row as $v) {
            $vals[] = $v === null ? 'NULL' : $pdo->quote($v);
        }
        fwrite($fh, 'INSERT INTO `'.$table.'` VALUES ('.implode(',', $vals).");\n");
        $rowTotal++;
    }

    fwrite($fh, "\n");
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

/* A backup that has never been restored is a guess. Reject an empty or
   truncated file rather than rotating a good one away for it. */
if (filesize($target) < 1024 || $rowTotal === 0) {
    fwrite(STDERR, "dump looks wrong (".filesize($target)." bytes, $rowTotal rows) - keeping it for inspection, not rotating\n");
    exit(1);
}

@chmod($target, 0600);

$existing = glob($dir.DIRECTORY_SEPARATOR.'kanboard-*.sql');
sort($existing);

while (count($existing) > KEEP) {
    unlink(array_shift($existing));
}

printf("%s  %d bytes  %d rows  (%d kept)\n", basename($target), filesize($target), $rowTotal, count($existing));
