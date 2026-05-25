<?php

/**
 * Define a constant to prevent direct access to views.
 */
define('RDX_EXPORTER_PATH', __DIR__);

/**
 * Register autoloader.
 */
spl_autoload_register(function ($class) {
	$prefix = 'Rhymix\\DataExchange\\';
	if (strncmp($prefix, $class, strlen($prefix)) !== 0)
	{
		return;
	}
	$relative_class = substr($class, strlen($prefix));
	$file = __DIR__ . '/' . lcfirst(str_replace('\\', '/', $relative_class)) . '.php';
	if (file_exists($file))
	{
		require_once $file;
	}
});

/**
 * Attempt to auto-detect CMS type and installation path from parent directory.
 *
 * @return array [cms_type, cms_path]
 */
function auto_detect_cms()
{
	$check_dir = function($dir) {
		if (file_exists($dir . '/files/config/config.php')) {
			return ['Rhymix', $dir];
		} elseif (file_exists($dir . '/files/config/db.config.php')) {
			return ['XE1', $dir];
		} elseif (file_exists($dir . '/data/dbconfig.php')) {
			return ['Gnuboard5', $dir];
		} elseif (file_exists($dir . '/wp-config.php')) {
			return ['WordPress', $dir];
		} else {
			return ['', ''];
		}
	};

	$parent_dir = dirname(RDX_EXPORTER_PATH);
	list($cms_type, $cms_path) = $check_dir($parent_dir);
	if (!$cms_type) {
		$grandparent_dir = dirname($parent_dir);
		list($cms_type, $cms_path) = $check_dir($grandparent_dir);
	}
	return [$cms_type, $cms_path];
}

/**
 * Get the list of constants defined in a CMS's config file, without actually executing the file.
 *
 * @param string $filename
 * @return array
 */
function get_constants_in_file($filename)
{
	$content = file_get_contents($filename);
	preg_match_all('/\bdefine\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $matches, PREG_SET_ORDER);
	$constants = [];
	foreach ($matches as $match)
	{
		$constants[$match[1]] = $match[2];
	}
	return $constants;
}

/**
 * Get the list of simple global variables defined in a CMS's config file, without actually executing the file.
 *
 * @param string $filename
 * @return array
 */
function get_global_vars_in_file($filename)
{
	$content = file_get_contents($filename);
	preg_match_all('/\s\$([a-zA-Z0-9_]+)\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $matches, PREG_SET_ORDER);
	$vars = [];
	foreach ($matches as $match)
	{
		$vars[$match[1]] = $match[2];
	}
	return $vars;
}

/**
 * Asset loading function for views.
 *
 * @param string $path
 * @return string
 */
function asset($path)
{
	$full_path = RDX_EXPORTER_PATH . '/' . ltrim($path, './');
	if (!file_exists($full_path))
	{
		throw new Exception('Asset file not found: ' . $path);
	}

	return './' . ltrim($path, './') . '?t=' . filemtime($full_path);
}

/**
 * Escape function for views.
 *
 * @param string $string
 * @param bool $double_encode
 * @return string
 */
function escape($string, $double_encode = true)
{
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8', $double_encode);
}

/**
 * Load a view.
 *
 * @param string $view_name
 * @param array $vars
 * @param bool $wrap_layout
 * @return string
 */
function view($view_name, $vars = [], $wrap_layout = false)
{
	$view_name = preg_replace('/[^a-z0-9_-]+/i', '', $view_name);
	$view_file = __DIR__ . '/views/' . $view_name . '.php';
	if (!file_exists($view_file))
	{
		throw new Exception('View file not found: ' . $view_name);
	}

	ob_start();
	extract($vars, EXTR_SKIP);
	include $view_file;
	$content = ob_get_clean();

	if ($wrap_layout)
	{
		ob_start();
		include __DIR__ . '/views/layout.php';
		return ob_get_clean();
	}
	else
	{
		return $content;
	}
}
