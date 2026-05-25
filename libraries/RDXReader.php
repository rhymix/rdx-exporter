<?php

namespace Rhymix\DataExchange\Libraries;

use Exception;
use Generator;
use ZipArchive;

class RDXReader
{
	/**
	 * ZipArchive instance.
	 */
	protected $_zip;

	/**
	 * The index data structure.
	 */
	protected $_index = [];

	/**
	 * Open an RDX archive.
	 *
	 * @param string $path
	 * @param string $password
	 * @throws Exception
	 */
	public function __construct($path, $password = '')
	{
		// Check if the file exists and is readable.
		if (!file_exists($path) || !is_readable($path))
		{
			throw new Exception('File not found or not readable: ' . $path);
		}

		// Attempt to open the RDX archive as a Zip file.
		$this->_zip = new ZipArchive();
		if ($this->_zip->open($path, defined('ZipArchive::RDONLY') ? ZipArchive::RDONLY : 0) !== true)
		{
			throw new Exception('Failed to open RDX archive: ' . $path);
		}

		// If a password was provided, try it.
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
		}

		// Load the index.json file from the archive.
		$index_content = $this->_zip->getFromName('index.json');
		if (!$index_content)
		{
			throw new Exception('Failed to read RDX archive. Is it password protected?');
		}
		$this->_index = json_decode($index_content, true);
		if (!$this->_index)
		{
			throw new Exception('Failed to parse index.json: ' . json_last_error_msg());
		}
	}

	/**
	 * Get the version of the current RDX archive.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return $this->_index['version'] ?? '1.0';
	}

	/**
	 * Get the source CMS of the current RDX archive.
	 *
	 * @return string
	 */
	public function getSource()
	{
		return $this->_index['source'] ?? 'Unknown';
	}

	/**
	 * Get the timezone of the current RDX archive.
	 *
	 * @return string
	 */
	public function getTimezone()
	{
		return $this->_index['timezone'] ?? 'ETC/UTC';
	}

	/**
	 * Get index entries.
	 *
	 * @return array
	 */
	public function getIndexEntries()
	{
		return $this->_index['entries'] ?? [];
	}

	/**
	 * Get the list of boards available in the archive.
	 *
	 * @return array
	 */
	public function getBoardNames()
	{
		$board_names = [];
		foreach ($this->getIndexEntries() as $entry)
		{
			if ($entry['type'] === 'board')
			{
				$board_names[] = $entry['title'];
			}
		}
		return $board_names;
	}

	/**
	 * Get members.
	 *
	 * @return Generator
	 */
	public function getMembers()
	{
		foreach ($this->getIndexEntries() as $entry)
		{
			if ($entry['type'] === 'member')
			{
				$fp = $this->_zip->getStream($entry['filename']);
				if ($fp)
				{
					while (!feof($fp))
					{
						$line = fgets($fp);
						if ($line)
						{
							yield json_decode($line);
						}
					}
					fclose($fp);
				}
			}
		}
	}

	/**
	 * Get messages.
	 *
	 * @return Generator
	 */
	public function getMessages()
	{
		foreach ($this->getIndexEntries() as $entry)
		{
			if ($entry['type'] === 'message')
			{
				$fp = $this->_zip->getStream($entry['filename']);
				if ($fp)
				{
					while (!feof($fp))
					{
						$line = fgets($fp);
						if ($line)
						{
							yield json_decode($line);
						}
					}
					fclose($fp);
				}
			}
		}
	}

	/**
	 * Get documents from a board.
	 *
	 * @param string $board_name
	 * @param int $offset
	 * @param int $count
	 * @return Generator
	 */
	public function getDocuments($board_name, $offset = 0, $count = 0)
	{
		$current_offset = 0;
		$yielded_count = 0;
		foreach ($this->getIndexEntries() as $entry)
		{
			if ($entry['type'] === 'board' && $entry['title'] === $board_name)
			{
				$fp = $this->_zip->getStream($entry['filename']);
				if ($fp)
				{
					while (!feof($fp))
					{
						$line = fgets($fp);
						if ($line && $current_offset >= $offset)
						{
							yield json_decode($line);
							$yielded_count++;
							if ($count > 0 && $yielded_count >= $count)
							{
								break 2;
							}
						}
						$current_offset++;
					}
					fclose($fp);
				}
			}
		}
	}

	/**
	 * Get the content of a file as a string, identified by its path within the archive.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function getFile($path)
	{
		if (preg_match('/^rdx:/', $path))
		{
			$path = substr($path, 4);
		}
		$path = preg_replace('!^\.?/!', '', $path);
		return $this->_zip->getFromName($path);
	}

	/**
	 * Get the content of a file as a stream, identified by its path within the archive.
	 *
	 * @param string $path
	 * @return resource|false
	 */
	public function getFileStream($path)
	{
		if (preg_match('/^rdx:/', $path))
		{
			$path = substr($path, 4);
		}
		$path = preg_replace('!^\.?/!', '', $path);
		return $this->_zip->getStream($path);
	}

	/**
	 * Save a file from the archive to a specified destination path.
	 *
	 * @param string $archive_path
	 * @param string $destination_path
	 * @throws Exception
	 * @return bool
	 */
	public function saveFile($archive_path, $destination_path)
	{
		if (preg_match('/^rdx:/', $archive_path))
		{
			$archive_path = substr($archive_path, 4);
		}
		$archive_path = preg_replace('!^\.?/!', '', $archive_path);
		$fp1 = $this->_zip->getStream($archive_path);
		if (!$fp1)
		{
			throw new Exception('Cannot open source file in RDX archive: ' . $archive_path);
		}
		$fp2 = fopen($destination_path, 'wb');
		if (!$fp2)
		{
			throw new Exception('Cannot open destination file: ' . $destination_path);
		}
		stream_copy_to_stream($fp1, $fp2);
		fclose($fp1);
		fclose($fp2);
		return true;
	}
}
