<?php

namespace Rhymix\DataExchange\Libraries;

use Exception;
use ZipArchive;

class RDXWriter
{
	/**
	 * Filename of the archive.
	 */
	protected $_filename = '';

	/**
	 * The password for the archive, if any.
	 */
	protected $_password = '';

	/**
	 * ZipArchive instance.
	 */
	protected $_zip;

	/**
	 * The default structure of the index.json file.
	 */
	protected $_index = [
		'version' => '1.0',
		'source' => 'Unknown',
		'tz' => 'Etc/UTC',
		'entries' => [],
	];

	/**
	 * List of source paths to delete after finalizing the archive.
	 */
	protected $_delete_sources = [];

	/**
	 * Open a zip file.
	 *
	 * @param string $path
	 * @param string $password
	 */
	public function __construct($path, $password = '')
	{
		$this->_filename = $path;
		$this->_zip = new ZipArchive();
		if ($this->_zip->open($this->_filename, ZipArchive::CREATE) !== true)
		{
			throw new Exception('Failed to create ZIP file: ' . $this->_filename);
		}

		// If a password was provided, check if ZIP encryption is supported.
		if ($password !== '')
		{
			if (!version_compare(\PHP_VERSION, '7.2.0', '>='))
			{
				throw new Exception('Password-protected archives require PHP 7.2 or higher.');
			}
			if (!defined('ZipArchive::EM_AES_128'))
			{
				throw new Exception('Password-protected archives require ZIP extension with AES support.');
			}
			if (!$this->_zip->setPassword($password))
			{
				throw new Exception('Failed to set ZIP password.');
			}
			$this->_password = $password;
		}
	}

	/**
	 * Set the source information for this export.
	 *
	 * @param string $source
	 * @return void
	 */
	public function setSource($source)
	{
		$this->_index['source'] = strval($source);
	}

	/**
	 * Set the timezone information for this export.
	 *
	 * @param string $tz
	 * @return void
	 */
	public function setTimezone($tz)
	{
		$this->_index['tz'] = strval($tz);
	}

	/**
	 * Add an index entry.
	 *
	 * @param string $filename
	 * @param string $source_path
	 * @param string $type
	 * @param string $title
	 * @param string $range
	 * @param bool $delete_source_path
	 * @return bool
	 */
	public function addEntry($filename, $source_path, $type, $title = '', $range = '', $delete_source_path = false)
	{
		$this->addFile($filename, $source_path);

		$this->_index['entries'][] = [
			'filename' => $filename,
			'type' => $type,
			'title' => $title,
			'range' => $range,
		];

		if ($delete_source_path)
		{
			$this->_delete_sources[] = $source_path;
		}

		return true;
	}

	/**
	 * Add a file to the Zip archive.
	 *
	 * @param string $filename
	 * @param string $source_path
	 * @return bool
	 */
	public function addFile($filename, $source_path)
	{
		if (!$this->_zip->addFile($source_path, $filename))
		{
			throw new Exception('Failed to add file to ZIP: ' . $filename);
		}
		if ($this->_password !== '')
		{
			if (!$this->_zip->setEncryptionName($filename, ZipArchive::EM_AES_128))
			{
				throw new Exception('Failed to encrypt file in ZIP: ' . $filename);
			}
		}

		return true;
	}

	/**
	 * Add a string to the Zip archive as a file.
	 *
	 * @param string $filename
	 * @param string $content
	 * @return bool
	 */
	public function addString($filename, $content)
	{
		if (!$this->_zip->addFromString($filename, $content))
		{
			throw new Exception('Failed to add string to ZIP: ' . $filename);
		}
		if ($this->_password !== '')
		{
			if (!$this->_zip->setEncryptionName($filename, ZipArchive::EM_AES_128))
			{
				throw new Exception('Failed to encrypt file in ZIP: ' . $filename);
			}
		}

		return true;
	}

	/**
	 * Close the Zip archive.
	 *
	 * @return bool
	 */
	public function finalize()
	{
		$index = json_encode($this->_index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$this->addString('index.json', $index);

		if (!$this->_zip->close())
		{
			throw new Exception('Failed to finalize ZIP file: ' . $this->_filename);
		}

		foreach ($this->_delete_sources as $source)
		{
			if (file_exists($source))
			{
				unlink($source);
			}
		}

		return true;
	}
}
