<?php
/** Benchmarking functions
 *
 * @package Default
 */

$start_memory = memory_get_usage();
$start_time = microtime(true);

/** Get Human Readable Size
 *
 * Convert size to human readable units
 *
 * @param int $size Size to convert
 *
 * @return string $result Human readable size
 */
function get_human_readable_usage($size) {
	$units = array('B', 'KB', 'MB', 'GB');
        $result = $size . ' B';

        if ($size < 1024) return $result;

        $index = floor(log($size, 1024));
        if ($index < sizeof($units)) {
            $result = round($size / pow(1024, ($index)), 2) . ' ' . $units[$index];
        }

	return $result;
}

/** Print Current Memory and Runtime Stats
 */
function display_current_stats() {
	global $start_time, $start_memory;
	$memory_usage = get_human_readable_usage(memory_get_usage() - $start_memory);
	$elapsed_time = sprintf("%.5f", microtime(true) - $start_time);
	echo "Memory usage: " . $memory_usage . ", elapsed time: " . $elapsed_time;
}

