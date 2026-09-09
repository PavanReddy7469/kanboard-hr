<?php
// Temporary diagnostic. Reports only opcache state - no configuration dump.
header('Content-Type: text/plain');
$on = function_exists('opcache_get_status');
echo "opcache_loaded: " . ($on ? 'yes' : 'no') . "\n";
if ($on) {
    $s = @opcache_get_status(false);
    if ($s === false) { echo "opcache_enabled: no (loaded but disabled)\n"; }
    else {
        echo "opcache_enabled: " . ($s['opcache_enabled'] ? 'yes' : 'no') . "\n";
        echo "cached_scripts:  " . $s['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "hits:            " . $s['opcache_statistics']['hits'] . "\n";
        echo "misses:          " . $s['opcache_statistics']['misses'] . "\n";
        echo "memory_used_mb:  " . round($s['memory_usage']['used_memory']/1048576, 1) . "\n";
    }
}
echo "php_sapi: " . php_sapi_name() . "\n";
