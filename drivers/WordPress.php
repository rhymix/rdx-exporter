<?php

namespace Rhymix\DataExchange\Drivers;

use Rhymix\DataExchange\Libraries\RDXWriter;
use Rhymix\DataExchange\Models\Member as MemberModel;
use Rhymix\DataExchange\Models\Message as MessageModel;
use Rhymix\DataExchange\Models\Document as DocumentModel;
use Rhymix\DataExchange\Models\Comment as CommentModel;
use Rhymix\DataExchange\Models\File as FileModel;
use PDO;
use PDOException;

class WordPress extends AbstractDriver
{
	/*
	 * Attributes for internal caching.
	 */
	public $db;
	public $prefix = '';
	public $install_path = '';
	public $config = [];
	public $langs = [];

	/**
	 * Validate the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function validate($vars)
	{
		// Validate the installation path and get the config.
		$config = $this->_checkConfig($vars);
		if (!$config['success'])
		{
			return $config;
		}

		// Validate the DB password.
		$output = $this->_checkDbPassword($config, $vars);
		if (!$output['success'])
		{
			return $output;
		}

		// Set class attributes.
		$this->db = $output['db'];
		$this->prefix = $output['prefix'];
		$this->install_path = rtrim($vars['install_path'] ?? '', '/');
		$this->config = $config;

		// Return an options template with the list of boards.
		return [
			'success' => true,
			'options_html' => view('opt_wordpress', []),
		];
	}

	/**
	 * Export data based on the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function export($vars)
	{
		// Validate the installation path and get the config.
		$config = $this->_checkConfig($vars);
		if (!$config['success'])
		{
			return $config;
		}

		// Validate the DB password.
		$output = $this->_checkDbPassword($config, $vars);
		if (!$output['success'])
		{
			return $output;
		}

		// Set class attributes.
		$this->db = $output['db'];
		$this->prefix = $output['prefix'];
		$this->install_path = rtrim($vars['install_path'] ?? '', '/');
		$this->config = $config;

		// Check export types.
		$export_types = $vars['export_type'] ?? [];
		if (empty($export_types) || !is_array($export_types))
		{
			return [
				'success' => false,
				'message' => 'At least one export type must be selected',
			];
		}
		foreach ($export_types as $type)
		{
			if (!in_array($type, ['user', 'post']))
			{
				return [
					'success' => false,
					'message' => 'Invalid export type: ' . $type,
				];
			}
		}

		// Check the attachment option.
		$include_attachments = $vars['include_attachments'] ?? 'N';
		if (!in_array($include_attachments, ['Y', 'N']))
		{
			return [
				'success' => false,
				'message' => 'Invalid selection for attachments: ' . $include_attachments,
			];
		}

		// Open the output file for writing.
		$path = \RDX_EXPORTER_PATH . '/temp/' . 'rdx_export_' . time() . '.zip';
		$password = trim($vars['zip_password'] ?? '');
		$rdx = new RDXWriter($path, $password);
		$rdx->setSource('WordPress');
		$rdx->setTimezone('Asia/Seoul');

		// Export users.
		if (in_array('user', $export_types))
		{
			$this->_exportUsers($rdx, $include_attachments === 'Y');
		}

		// Export posts.
		if (in_array('post', $export_types))
		{
			$this->_exportPosts($rdx, $include_attachments === 'Y');
		}

		// Finalize the output file.
		$rdx->finalize();

		// Return the download URL for the generated file.
		return [
			'success' => true,
			'download_url' => './index.php?action=download&file=' . basename($path),
		];
	}

	/**
	 * Get the configuration file from the specified path and check if it's a valid WordPress installation.
	 *
	 * @param array $vars
	 * @return array
	 */
	protected function _checkConfig(array $vars): array
	{
		// Check the installation path for config file.
		$install_path = rtrim($vars['install_path'] ?? '', '/');
		$config_file_path = '/wp-config.php';
		if (!$install_path || !is_dir($install_path) || !file_exists($install_path . $config_file_path))
		{
			return [
				'success' => false,
				'message' => 'Cannot find a valid WordPress installation at the specified path',
			];
		}

		// Load the config file.
		$config = ['success' => true];
		$constants = get_constants_in_file($install_path . $config_file_path);
		foreach ($constants as $key => $value)
		{
			$config[$key] = $value;
		}
		$globals = get_global_vars_in_file($install_path . $config_file_path);
		foreach ($globals as $key => $value)
		{
			if (!isset($config[$key]))
			{
				$config[$key] = $value;
			}
		}

		return $config;
	}

	/**
	 * Check the DB password.
	 *
	 * @param array $config
	 * @param array $vars
	 * @return array
	 */
	protected function _checkDbPassword($config, $vars)
	{
		$db_password = trim($vars['db_password'] ?? '');
		if ($db_password === '')
		{
			return [
				'success' => false,
				'message' => 'Database password is required',
			];
		}
		if ($db_password !== ($config['DB_PASSWORD'] ?? ''))
		{
			return [
				'success' => false,
				'message' => 'Incorrect database password',
			];
		}

		try
		{
			$db = $this->_getDbConnection($config);
		}
		catch (PDOException $e)
		{
			return [
				'success' => false,
				'message' => 'Failed to connect to the database: ' . $e->getMessage(),
			];
		}

		return [
			'success' => true,
			'db' => $db,
			'prefix' => $config['table_prefix'] ?? '',
		];
	}

	/**
	 * Get a database connection.
	 *
	 * @param array $config
	 * @return PDO
	 */
	protected function _getDbConnection($config)
	{
		$host = $config['DB_HOST'] ?? 'localhost';
		$username = $config['DB_USER'] ?? null;
		$password = $config['DB_PASSWORD'] ?? null;
		$dbname = $config['DB_NAME'] ?? '';
		$charset = $config['DB_CHARSET'] ?? '';

		$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
		return new PDO($dsn, $username, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
		]);
	}

	/**
	 * Export members to the zip file.
	 *
	 * @param RDXWriter $rdx
	 * @param bool $include_attachments
	 * @return void
	 */
	protected function _exportUsers(RDXWriter $rdx, $include_attachments): void
	{
		// Preparation.
		$batch = 1000;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT * FROM {$prefix}users WHERE `ID` > ? ORDER BY `ID` ASC LIMIT $batch");
		$stmt2 = $this->db->prepare("SELECT meta_key, meta_value FROM {$prefix}usermeta WHERE user_id = ?");

		// Loop through members in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_user_id = 0;
		while (true)
		{
			// Load a batch of members.
			$stmt1->execute([$last_user_id]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_member_');
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Member instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Basic information
				$info = new MemberModel();
				$info->user_id = $row->user_login;
				$info->password = $row->user_pass;
				$info->nick_name = $row->display_name;
				$info->email_address = $row->user_email;
				$info->homepage = ($row->user_url ?? null) ?: null;
				$info->signup_date = !empty($row->user_registered) ? preg_replace('/[^0-9]/', '', $row->user_registered) : null;

				// Get usermeta
				$stmt2->execute([$row->ID]);
				$meta = [];
				while ($m = $stmt2->fetchObject())
				{
					$meta[$m->meta_key] = $m->meta_value;
				}
				if (!empty($meta['first_name']) || !empty($meta['last_name']))
				{
					$info->user_name = trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
				}

				// Capabilities (administrator)
				$capabilities = !empty($meta['wp_capabilities']) ? @unserialize($meta['wp_capabilities']) : [];
				if (isset($capabilities['administrator']))
				{
					$info->is_admin = 'Y';
				}

				// Signature
				if (!empty($meta['description']))
				{
					$info->signature = nl2br(escape($meta['description'], false));
				}

				// Write the member info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last user_id for the next batch.
				if ($row->ID > $last_user_id)
				{
					$last_user_id = (int)$row->ID;
				}
			}

			// Close the temporary file.
			fclose($fp);

			// Add the JSONL entry to the zip.
			$rdx->addEntry("members/member_{$index}.jsonl", $tempname, 'member', '', "{$range_start}-{$range_end}", true);
			$index++;
			$range_start = $range_end + 1;
		}
	}

	/**
	 * Export boards to the zip file.
	 *
	 * @param RDXWriter $rdx
	 * @param bool $include_attachments
	 * @return void
	 */
	protected function _exportPosts(RDXWriter $rdx, $include_attachments)
	{
		// Preparation.
		$batch = 100;
		$prefix = $this->prefix;

		$stmt1 = $this->db->prepare("SELECT p.*, u.user_login, u.display_name FROM {$prefix}posts AS p " .
			"LEFT JOIN {$prefix}users AS u ON p.post_author = u.ID " .
			"WHERE p.post_type = 'post' AND p.ID > ? ORDER BY p.ID ASC LIMIT $batch");
		$stmt2 = $this->db->prepare("SELECT c.*, u.user_login, u.display_name FROM {$prefix}comments AS c " .
			"LEFT JOIN {$prefix}users AS u ON c.user_id = u.ID " .
			"WHERE c.comment_post_ID = ? ORDER BY c.comment_ID ASC");

		// Loop through documents in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_post_id = 0;
		while (true)
		{
			// Load a batch of documents.
			$stmt1->execute([$last_post_id]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_post_');
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Document instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Basic information
				$info = new DocumentModel();
				$info->id = (int)$row->ID;
				$info->title = $row->post_title;
				$info->content = $row->post_content;
				$info->slug = $row->post_name ?? null;

				// Dates and IP addresses
				$info->regdate = !empty($row->post_date) ? preg_replace('/[^0-9]/', '', $row->post_date) : null;
				$info->last_update = !empty($row->post_modified) ? preg_replace('/[^0-9]/', '', $row->post_modified) : null;

				// Author information
				$info->user_id = $row->user_login ?? null;
				$info->nick_name = !empty($row->display_name) ? $row->display_name : null;

				// Other properties
				$info->allow_comment = $row->comment_status === 'open' ? 'Y' : 'N';
				$info->allow_trackback = $row->ping_status === 'open' ? 'Y' : 'N';
				$info->status = $row->post_status === 'publish' ? 'PUBLIC' : 'SECRET';

				// Attachments
				$info->files = $this->_getFileList($row->ID, $include_attachments ? $rdx : null);

				// Comments
				$stmt2->execute([$row->ID]);
				while ($cmt = $stmt2->fetchObject())
				{
					// Basic information
					$comment_info = new CommentModel();
					$comment_info->id = (int)$cmt->comment_ID;
					$comment_info->content = $cmt->comment_content;

					// Dates and IP addresses
					$comment_info->regdate = !empty($cmt->comment_date) ? preg_replace('/[^0-9]/', '', $cmt->comment_date) : null;
					$comment_info->ipaddress = $cmt->comment_author_IP ?? null;

					// Author information
					$comment_info->user_id = $cmt->user_login ?? null;
					$comment_info->user_name = !empty($cmt->display_name) ? $cmt->display_name : null;
					$comment_info->nick_name = !empty($cmt->comment_author) ? $cmt->comment_author : null;
					$comment_info->email_address = !empty($cmt->comment_author_email) ? $cmt->comment_author_email : null;
					$comment_info->homepage = !empty($cmt->comment_author_url) ? $cmt->comment_author_url : null;

					// Other properties
					switch (strval($cmt->comment_approved ?? ''))
					{
						case 'spam':
						case 'trash':
							$comment_info->status = 'TRASH';
							break;
						case '0':
							$comment_info->status = 'SECRET';
							break;
						case '1':
						default:
							$comment_info->status = 'PUBLIC';
					}

					$info->comments[] = $comment_info;
				}

				// Update counters.
				$info->comment_count = count($info->comments);
				$info->file_count = count($info->files);

				// Write the document info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last post_id for the next batch.
				if ($row->ID > $last_post_id)
				{
					$last_post_id = (int)$row->ID;
				}
			}

			// Close the temporary file.
			fclose($fp);

			// Add the JSONL entry to the zip.
			$rdx->addEntry("posts/post_{$index}.jsonl", $tempname, 'board', 'Posts', "{$range_start}-{$range_end}", true);
			$index++;
			$range_start = $range_end + 1;
		}
	}

	/**
	 * Get the list of files.
	 *
	 * @param int $post_id
	 * @param ?RDXWriter $rdx
	 * @return array
	 */
	protected function _getFileList($post_id, $rdx = null): array
	{
		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT p.*, pm.meta_value AS metadata FROM " . $this->prefix . "posts AS p " .
				"LEFT JOIN " . $this->prefix . "postmeta AS pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_metadata' " .
				"WHERE p.post_parent = ? AND p.post_type = 'attachment' " .
				"ORDER BY p.ID ASC");
		}

		// Convert each row to a File instance.
		$files = [];
		$stmt->execute([$post_id]);
		while ($row = $stmt->fetchObject())
		{
			// Simple mapping
			$info = new FileModel();
			$info->id = $row->ID;
			$info->filename = basename($row->guid);
			$info->url = strstr($row->guid, 'wp-content/');
			$info->regdate = !empty($row->post_date) ? preg_replace('/[^0-9]/', '', $row->post_date) : null;
			if (file_exists($this->install_path . '/' . $info->url) && is_readable($this->install_path . '/' . $info->url))
			{
				$info->file_size = filesize($this->install_path . '/' . $info->url);
			}
			else
			{
				$info->file_size = 0;
			}

			// Use metadata if available.
			if ($row->metadata)
			{
				$metadata = @unserialize($row->metadata);
				if ($metadata && !empty($metadata['file']))
				{
					$info->filename = basename($metadata['file'] ?? '');
				}
				if ($metadata && !empty($metadata['width']))
				{
					$info->width = (int)$metadata['width'];
				}
				if ($metadata && !empty($metadata['height']))
				{
					$info->height = (int)$metadata['height'];
				}
			}

			// Attach file to zip if RDXWriter is provided.
			if ($rdx && file_exists($this->install_path . '/' . $info->url) && is_readable($this->install_path . '/' . $info->url))
			{
				$rdx->addFile($info->url, $this->install_path . '/' . $info->url);
				$info->path = 'rdx:' . $info->url;
			}
			else
			{
				$info->path = 'url:' . $info->url;
			}

			$files[] = $info;
		}

		return $files;
	}
}
