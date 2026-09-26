<?php
/**
 * Renumber every task's code to PROJECTCODE-NNN.
 *
 *   php ops/renumber-task-codes.php            # dry run: prints, changes nothing
 *   php ops/renumber-task-codes.php --apply    # writes
 *
 * Tasks keep their existing code if it is already in the right shape, so this
 * is safe to run twice. Numbering follows creation order within each project,
 * which makes it stable: running it again produces the same answer.
 *
 * TAKE A BACKUP FIRST - ops/backup.php - because this rewrites the identifier
 * people may already have quoted in documents and links.
 *
 * Command line only; it refuses to run over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

require dirname(__DIR__).'/config.php';

$apply = in_array('--apply', $argv, true);

try {
    $pdo = new PDO('mysql:host='.DB_HOSTNAME.';dbname='.DB_NAME.';charset=utf8mb4',
                   DB_USERNAME, DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (Exception $e) {
    fwrite(STDERR, "database connection failed\n");
    exit(1);
}

$projects = $pdo->query('SELECT id, name, identifier FROM projects ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$changed = 0;
$kept    = 0;

foreach ($projects as $project) {
    $prefix = $project['identifier'] !== null && $project['identifier'] !== ''
        ? strtoupper(substr($project['identifier'], 0, 4))
        : sprintf('P%03d', $project['id']);

    $stmt = $pdo->prepare('SELECT id, reference FROM tasks WHERE project_id = ? ORDER BY date_creation, id');
    $stmt->execute(array($project['id']));
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tasks)) {
        continue;
    }

    printf("\n%s  (%s)  %d task%s\n", $prefix, $project['name'], count($tasks), count($tasks) === 1 ? '' : 's');

    /* Codes already in the right shape are left exactly as they are, and the
       numbers they hold are reserved so nothing is issued twice. */
    $used = array();
    foreach ($tasks as $task) {
        if (preg_match('/^'.preg_quote($prefix, '/').'-([0-9]+)$/', strtoupper((string) $task['reference']), $m)) {
            $used[(int) $m[1]] = true;
        }
    }

    $next = 1;
    $update = $pdo->prepare('UPDATE tasks SET reference = ? WHERE id = ?');

    foreach ($tasks as $task) {
        $current = strtoupper((string) $task['reference']);

        if (preg_match('/^'.preg_quote($prefix, '/').'-([0-9]+)$/', $current)) {
            printf("  #%-5d %-12s keep\n", $task['id'], $current);
            $kept++;
            continue;
        }

        while (isset($used[$next])) {
            $next++;
        }

        $code = $prefix.'-'.sprintf('%03d', $next);
        $used[$next] = true;

        printf("  #%-5d %-12s -> %s\n", $task['id'], $current === '' ? '(none)' : $current, $code);

        if ($apply) {
            $update->execute(array($code, $task['id']));
        }

        $changed++;
    }
}

printf("\n%d to change, %d already correct\n", $changed, $kept);

if (! $apply) {
    echo "DRY RUN - nothing written. Re-run with --apply once the list above looks right.\n";
} else {
    echo "written\n";
}
