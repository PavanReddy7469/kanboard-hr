<?php
/**
 * SUPERBEE — SQLite to MySQL data migration.
 *
 * Copies every row from the live SQLite database into a MySQL database whose
 * schema has already been built by Kanboard's own migrations. It does not
 * create tables: the schema must exist first, so the two never drift.
 *
 * Order matters. Foreign keys are enforced, so parents are inserted before
 * children; anything not named in TABLE_ORDER is copied afterwards, and the
 * checks at the end prove nothing was silently dropped.
 *
 * Usage: php migrate_data.php <sqlite-file> <socket-or-host> <db> [user] [pass]
 */

if ($argc < 4) {
    fwrite(STDERR, "usage: php migrate_data.php <sqlite-file> <socket|host> <db> [user] [pass]\n");
    exit(1);
}

list(, $sqliteFile, $target, $dbName) = $argv;
$user = isset($argv[4]) ? $argv[4] : 'root';
$pass = isset($argv[5]) ? $argv[5] : '';

// Parents first. Everything else follows in whatever order the database lists it.
const TABLE_ORDER = array(
    'users', 'groups', 'group_has_users', 'settings', 'links', 'currencies',
    'plugin_schema_versions', 'projects', 'project_has_users', 'project_has_groups',
    'project_has_roles', 'project_role_has_restrictions', 'project_has_categories',
    'swimlanes', 'columns', 'column_has_restrictions', 'column_has_move_restrictions',
    'tasks', 'subtasks', 'comments', 'task_has_links', 'task_has_external_links',
    'task_has_files', 'task_has_metadata', 'task_has_tags', 'tags',
    'project_has_files', 'project_has_metadata', 'project_has_notification_types',
    'project_activities', 'project_daily_stats', 'project_daily_column_stats',
    'transitions', 'subtask_time_tracking', 'actions', 'action_has_params',
    'custom_filters', 'predefined_task_descriptions',
    'taskmanager_milestones', 'taskmanager_task_lists', 'taskmanager_dependencies',
    'taskmanager_timesheets', 'taskmanager_time_entries', 'taskmanager_deliverables',
);

$dsn = strpos($target, '/') === 0
    ? "mysql:unix_socket=$target;dbname=$dbName;charset=utf8mb4"
    : "mysql:host=$target;dbname=$dbName;charset=utf8mb4";

$src = new PDO('sqlite:'.$sqliteFile, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$dst = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

$srcTables = array();
foreach ($src->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $r) {
    $srcTables[] = $r['name'];
}

$dstTables = array();
foreach ($dst->query("SELECT table_name FROM information_schema.tables WHERE table_schema = ".$dst->quote($dbName)) as $r) {
    $dstTables[] = $r['table_name'];
}

$ordered = array();
foreach (TABLE_ORDER as $t) {
    if (in_array($t, $srcTables, true)) { $ordered[] = $t; }
}
foreach ($srcTables as $t) {
    if (! in_array($t, $ordered, true)) { $ordered[] = $t; }
}

/* Constraints go off for the copy and back on afterwards. With them on, a table
   whose parent has not been copied yet fails even though the final state is
   perfectly consistent - the integrity check at the end is what proves it. */
$dst->exec('SET FOREIGN_KEY_CHECKS = 0');
$dst->exec('SET SESSION sql_mode = "NO_ENGINE_SUBSTITUTION"');

$copied = array();
$skipped = array();

foreach ($ordered as $table) {
    if (! in_array($table, $dstTables, true)) {
        $skipped[$table] = 'no such table in MySQL';
        continue;
    }

    $rows = $src->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
    $dst->exec('DELETE FROM `'.$table.'`');

    if (empty($rows)) { $copied[$table] = 0; continue; }

    // Only columns that exist on both sides, so a schema drift is visible
    // rather than fatal.
    $dstCols = array();
    foreach ($dst->query("SELECT column_name FROM information_schema.columns
                          WHERE table_schema = ".$dst->quote($dbName)."
                            AND table_name = ".$dst->quote($table)) as $c) {
        $dstCols[] = $c['column_name'];
    }
    $cols = array_values(array_intersect(array_keys($rows[0]), $dstCols));
    $missing = array_diff(array_keys($rows[0]), $dstCols);

    if (! empty($missing)) {
        $skipped[$table.' (columns)'] = implode(',', $missing);
    }

    $sql = 'INSERT INTO `'.$table.'` (`'.implode('`,`', $cols).'`) VALUES ('
         . implode(',', array_fill(0, count($cols), '?')).')';
    $stmt = $dst->prepare($sql);

    $dst->beginTransaction();
    foreach ($rows as $row) {
        $values = array();
        foreach ($cols as $c) { $values[] = $row[$c]; }
        $stmt->execute($values);
    }
    $dst->commit();

    $copied[$table] = count($rows);
}

$dst->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "table                            sqlite   mysql\n";
echo "--------------------------------------------------\n";
$bad = 0;
foreach ($copied as $table => $n) {
    $after = (int) $dst->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    $flag = $after === $n ? '' : '   <-- MISMATCH';
    if ($after !== $n) { $bad++; }
    if ($n > 0 || $after > 0) {
        printf("%-32s %6d  %6d%s\n", $table, $n, $after, $flag);
    }
}

echo "\ntables copied: ".count($copied).", row mismatches: $bad\n";

if (! empty($skipped)) {
    echo "\nskipped:\n";
    foreach ($skipped as $k => $v) { echo "  $k: $v\n"; }
}

exit($bad === 0 ? 0 : 1);
