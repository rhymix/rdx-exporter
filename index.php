<?php
/**
 * -----------------------------------------------------------------------------
 *
 *  RDX Exporter
 *
 *  This script exports CMS data to the Rhymix Data Exchange format.
 *
 * -----------------------------------------------------------------------------
 *
 *  Copyright (c) Poesis Inc. and Contributors <devops@rhymix.org>
 *
 *  This program is free software: you can redistribute it and/or modify it
 *  under the terms of the GNU General Public License as published by the Free
 *  Software Foundation, either version 2 of the License, or (at your option)
 *  any later version.
 *
 *  This program is distributed in the hope that it will be useful, but WITHOUT
 *  ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 *  FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * -----------------------------------------------------------------------------
 */

/**
 * Load common libraries.
 */
require 'common.php';

/**
 * Display the index view.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET))
{
	echo view('index', ['current_lang' => $current_lang], true);
	exit();
}

/**
 * Handle validation request (POST).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate')
{
	$cms_type = preg_replace('/[^a-z0-9_]/i', '', $_POST['cms_type'] ?? '');
	if (!$cms_type || !file_exists(__DIR__ . "/drivers/{$cms_type}.php"))
	{
		echo json_encode([
			'success' => false,
			'message' => 'Missing or invalid CMS type',
		]);
		exit();
	}

	$driver_class = "Rhymix\\DataExchange\\Drivers\\{$cms_type}";
	if (!class_exists($driver_class) || !method_exists($driver_class, 'validate'))
	{
		echo json_encode([
			'success' => false,
			'message' => "{$cms_type} driver does not implement validate() method",
		]);
		exit();
	}

	try
	{
		$driver = new $driver_class();
		$result = $driver->validate($_POST);
		echo json_encode($result);
		exit();
	}
	catch (Exception $e)
	{
		echo json_encode([
			'success' => false,
			'message' => $e->getMessage(),
		]);
		exit();
	}
}

/**
 * Handle export request (POST).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export')
{
	$cms_type = preg_replace('/[^a-z0-9_]/i', '', $_POST['cms_type'] ?? '');
	if (!$cms_type || !file_exists(__DIR__ . "/drivers/{$cms_type}.php"))
	{
		echo json_encode([
			'success' => false,
			'message' => 'Missing or invalid CMS type',
		]);
		exit();
	}

	$driver_class = "Rhymix\\DataExchange\\Drivers\\{$cms_type}";
	if (!class_exists($driver_class) || !method_exists($driver_class, 'export'))
	{
		echo json_encode([
			'success' => false,
			'message' => "{$cms_type} driver does not implement export() method",
		]);
		exit();
	}

	try
	{
		$driver = new $driver_class();
		$result = $driver->export($_POST);
		echo json_encode($result);
		exit();
	}
	catch (Exception $e)
	{
		echo json_encode([
			'success' => false,
			'message' => $e->getMessage(),
		]);
		exit();
	}
}

/**
 * Handle download request.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file']))
{
	$file = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', strval($_GET['file']));
	$path = RDX_EXPORTER_PATH . '/temp/' . $file;
	if (!file_exists($path))
	{
		echo '<script> alert("File not found."); </script>';
		exit();
	}

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . basename($file) . '"');
	header('Content-Length: ' . filesize($path));
	while (ob_get_level())
	{
		ob_end_clean();
	}

	$fp = fopen($path, 'rb');
	while (($buffer = fread($fp, 8192)) !== false)
	{
		echo $buffer;
		flush();
	}
	fclose($fp);
	unlink($path);
	exit();
}

/**
 * All other requests are 404.
 */
header('HTTP/1.1 404 Not Found');
echo '<h1>404 Not Found</h1>';
exit();
