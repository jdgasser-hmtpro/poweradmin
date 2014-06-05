<?php
/**
 * Plugin API
 *
 * Dynamically add behavior by putting code in the plugins directory
 * of the application root.  For the loader to include a plugin,
 * there must be a file named:
 *
 *     plugins/[name]/[name].plugin.php
 *
 * Further imports, functions, or definitions can be done from
 * that top-level script.
 */
$hook_listeners = array ();

/**
 * Register function to be executed for the given hook
 *
 * @param string $hook        	
 * @param mixed $function        	
 */
function add_listener($hook, $function) {
	if (! $hook || ! $function) {
		trigger_error ( 'add_listener requires both a hook name and a function', E_USER_ERROR );
	}
	global $hook_listeners;
	$hook_listeners [$hook] [] = $function;
}

/**
 * Removes all listeners from the given hook
 *
 * @param string $hook        	
 */
function clear_listeners($hook) {
	if (! $hook) {
		trigger_error ( 'clear_listeners requires a hook name', E_USER_ERROR );
	}
	global $hook_listeners;
	$hook_listeners [$hook] = array ();
}

/**
 * Execute a hook, call registered listener functions
 */
function do_hook() {
	global $hook_listeners;
	$argc = func_num_args ();
	$argv = func_get_args ();
	if ($argc < 1) {
		trigger_error ( 'Missing argument in do_hook', E_USER_ERROR );
	}
	
	$hook_name = array_shift ( $argv );
	
	if (! isset ( $hook_listeners [$hook_name] )) {
		return;
	}
	
	foreach ( $hook_listeners [$hook_name] as $func ) {
		$response = call_user_func_array ( $func, (array)$argv );
		return $response;
	}
}

/**
 * Look for plugins and perform imports
 */
function import_plugins() {
	$plugins_dir = 'inc/plugins';
	$contents = scandir ( $plugins_dir );
	foreach ( $contents as $dir ) {
		if ($dir == '.' || $dir == '..') {
			continue;
		}
		$plugin_file = $plugins_dir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $dir . '.plugin.php';
		if (file_exists ( $plugin_file )) {
			
			require_once $plugin_file;
		}
	}
}
import_plugins ();
											