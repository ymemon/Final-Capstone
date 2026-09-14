<?php
if (isset($GLOBALS['wpaas_cache_class'])) {
    $GLOBALS['wpaas_cache_class']->do_ban();
    $GLOBALS['wpaas_cache_class']->flush_cdn();
    do_action('shutdown');
    echo "flushed\n";
} else {
    echo "wpaas_cache_class not set\n";
}
