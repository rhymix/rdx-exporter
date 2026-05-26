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

class XE3 extends AbstractDriver
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
			'options_html' => view('opt_xe3', [
				'boards' => $this->_getBoardList(),
			]),
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
			if (!in_array($type, ['member', 'board']))
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

		// Check the boards.
		$boards = $vars['boards'] ?? [];
		if (in_array('board', $export_types) && (empty($boards) || !is_array($boards)))
		{
			return [
				'success' => false,
				'message' => 'At least one board must be selected for board export',
			];
		}
		if (!in_array('board', $export_types))
		{
			$boards = [];
		}
		foreach ($boards as $board)
		{
			if (preg_match('/[^a-z0-9_-]/i', $board))
			{
				return [
					'success' => false,
					'message' => 'Invalid board ID: ' . $board,
				];
			}
		}

		// Open the output file for writing.
		$path = \RDX_EXPORTER_PATH . '/temp/' . 'rdx_export_' . time() . '.zip';
		$password = trim($vars['zip_password'] ?? '');
		$rdx = new RDXWriter($path, $password);
		$rdx->setSource('XE3');
		$rdx->setTimezone('Asia/Seoul');

		// Export members.
		if (in_array('member', $export_types))
		{
			$this->_exportMembers($rdx, $include_attachments === 'Y');
		}

		// Export boards.
		if (in_array('board', $export_types))
		{
			$this->_exportBoards($rdx, $boards, $include_attachments === 'Y');
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
	 * Get the configuration file from the specified path and check if it's a valid XE3 installation.
	 *
	 * @param array $vars
	 * @return array
	 */
	protected function _checkConfig(array $vars): array
	{
		// Check the installation path for config file.
		$config_file_path = '/config/production/database.php';
		$install_path = rtrim($vars['install_path'] ?? '', '/');
		if (!$install_path || !is_dir($install_path) || !file_exists($install_path . $config_file_path))
		{
			return [
				'success' => false,
				'message' => 'Cannot find a valid XE3 installation at the specified path',
			];
		}

		// Load the config file.
		$config = call_user_func(function($filename) {
			$config = include $filename;
			$default = $config['default'] ?? 'mysql';
			$config = $config['connections'][$default] ?? null;
			return $config;
		}, $install_path . $config_file_path);

		if (is_array($config))
		{
			$config['success'] = true;
		}
		else
		{
			$config = [
				'success' => false,
				'message' => 'Failed to load configuration file',
			];
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
		if ($db_password !== ($config['password'] ?? ''))
		{
			return [
				'success' => false,
				'message' => 'Incorrect database password',
			];
		}

		try
		{
			$db = $this->_getDbConnection($config, $prefix);
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
			'prefix' => $prefix,
		];
	}

	/**
	 * Get a database connection.
	 *
	 * @param array $config
	 * @param string $prefix (by reference)
	 * @return PDO
	 */
	protected function _getDbConnection($config, &$prefix = '')
	{
		$host = $config['host'] ?? 'localhost';
		$port = $config['port'] ?? '3306';
		$username = $config['username'] ?? null;
		$password = $config['password'] ?? null;
		$dbname = $config['database'] ?? '';
		$charset = $config['charset'] ?? 'utf8';
		$prefix = $config['prefix'] ?? '';

		$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
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
	protected function _exportMembers(RDXWriter $rdx, $include_attachments): void
	{
		// Preparation.
		$batch = 1000;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT u.*, p.point FROM {$prefix}user AS u " .
			"LEFT JOIN {$prefix}point AS p ON u.id = p.user_id " .
			"WHERE u.id > ? ORDER BY u.id ASC LIMIT $batch");

		// Pre-fetch the list of groups.
		$all_groups = [];
		$stmt = $this->db->query("SELECT id, name FROM {$prefix}user_group");
		while ($row = $stmt->fetchObject())
		{
			if (preg_match('/^(user::[0-9a-f-]+)$/', $row->name, $matches))
			{
				$row->name = $this->_translateLangCode($matches[1]);
			}
			$all_groups[$row->id] = $row->name;
		}

		// Loop through members in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_id = '';
		while (true)
		{
			// Load a batch of members.
			$stmt1->execute([$last_id]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Load their group information.
			$groups = [];
			$stmt2 = $this->db->query("SELECT user_id, group_id FROM {$prefix}user_group_user " .
				"WHERE user_id IN ('" . implode("','", array_map(function($row) { return $row->id; }, $rows)) . "')");
			while ($row = $stmt2->fetchObject())
			{
				if (isset($all_groups[$row->group_id]))
				{
					$groups[$row->user_id][] = $all_groups[$row->group_id];
				}
			}

			// Load their profile image information.
			$profile_images = [];
			$profile_image_ids = [];
			foreach ($rows as $row)
			{
				if (!empty($row->profile_image_id))
				{
					$profile_image_ids[] = $row->profile_image_id;
				}
			}
			if (count($profile_image_ids))
			{
				$stmt2 = $this->db->query("SELECT * FROM {$prefix}files " .
					"WHERE id IN ('" . implode("','", $profile_image_ids) . "')");
				while ($row = $stmt2->fetchObject())
				{
					$profile_images[$row->id] = $row;
				}
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_member_');
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Member instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Simple mapping
				$info = new MemberModel();
				$info->user_id = $row->login_id;
				$info->password = $row->password;
				$info->user_name = $row->display_name;
				$info->nick_name = $row->display_name;
				$info->email_address = $row->email;
				$info->signup_date = !empty($row->created_at) ? preg_replace('/[^0-9]/', '', $row->created_at) : null;
				$info->last_login_date = !empty($row->login_at) ? preg_replace('/[^0-9]/', '', $row->login_at) : null;
				$info->change_password_date = !empty($row->password_updated_at) ? preg_replace('/[^0-9]/', '', $row->password_updated_at) : null;
				$info->is_admin = $row->rating === 'super' ? 'Y' : 'N';
				$info->status = $row->status === 'activated' ? 'APPROVED' : 'DENIED';

				// Profile image
				if (!empty($row->profile_image_id) && isset($profile_images[$row->profile_image_id]))
				{
					$profile_image = $profile_images[$row->profile_image_id];
					$path = 'storage/app/' . $profile_image->path . '/' . $profile_image->filename;
					if ($include_attachments)
					{
						$rdx->addFile($path, $this->install_path . '/' . $path);
						$info->profile_image = 'rdx:' . $path;
					}
					else
					{
						$info->profile_image = 'url:' . $path;
					}
				}

				// Signature
				$info->signature = !empty($row->introduction) ? nl2br(escape($row->introduction, false)) : null;

				// Groups
				if (isset($groups[$row->id]))
				{
					$info->groups = $groups[$row->id];
				}

				// Points
				if (isset($row->point))
				{
					$info->points = (int)$row->point;
				}

				// Write the member info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last ID for the next batch.
				$last_id = $row->id;
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
	 * @param array $boards
	 * @param bool $include_attachments
	 * @return void
	 */
	protected function _exportBoards(RDXWriter $rdx, $boards, $include_attachments)
	{
		// Preparation.
		$batch = 100;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT d.*, u.login_id AS author_login_id " .
			"FROM {$prefix}documents AS d " .
			"LEFT JOIN {$prefix}user AS u ON d.user_id = u.id " .
			"WHERE d.instance_id = ? AND d.type = ? AND d.created_at > ? ORDER BY d.created_at ASC LIMIT $batch");
		$stmt2 = $this->db->prepare("SELECT d.*, u.login_id AS author_login_id " .
			"FROM {$prefix}comment_target AS c, {$prefix}documents AS d " .
			"LEFT JOIN {$prefix}user AS u ON d.user_id = u.id " .
			"WHERE c.target_id = ? AND c.doc_id = d.id ORDER BY d.created_at ASC");

		// Get board titles for the selected module_srls.
		$board_infos = [];
		foreach ($this->_getBoardList() as $board)
		{
			if (in_array($board['id'], $boards))
			{
				$board_infos[$board['id']] = $board;
			}
		}

		// Loop through boards.
		foreach ($boards as $board_id)
		{
			// Loop through documents in batches.
			$index = 1;
			$dummy_id = 1;
			$range_start = 1;
			$range_end = 0;
			$last_date = '1901-01-01 00:00:00';
			while (true)
			{
				// Load a batch of documents.
				$stmt1->execute([$board_id, 'module/board@board', $last_date]);
				$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
				if (!count($rows))
				{
					break;
				}

				// Open a temporary file for this batch.
				$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_document_');
				$fp = fopen($tempname, 'wb');

				// Convert each row to a Document instance and write as a JSON line.
				foreach ($rows as $row)
				{
					// Basic information
					$info = new DocumentModel();
					$info->id = $dummy_id++;
					$info->title = $row->title;
					$info->content = $row->content;
					$info->lang_code = $row->locale ?? null;

					// Counters
					$info->read_count = intval($row->read_count ?? 0);
					$info->upvote_count = intval($row->assent_count ?? 0);
					$info->downvote_count = abs(intval($row->dissent_count ?? 0));
					$info->comment_count = intval($row->comment_count ?? 0);

					// Dates and IP addresses
					$info->regdate = !empty($row->created_at) ? preg_replace('/[^0-9]/', '', $row->created_at) : null;
					$info->last_update = !empty($row->updated_at) ? preg_replace('/[^0-9]/', '', $row->updated_at) : null;
					$info->ipaddress = $row->ipaddress ?? null;

					// Author information
					$info->user_id = !empty($row->author_login_id) ? $row->author_login_id : null;
					$info->password = !empty($row->certify_key) ? $row->certify_key : null;
					$info->user_name = !empty($row->writer) ? $row->writer : null;
					$info->nick_name = !empty($row->writer) ? $row->writer : null;
					$info->email_address = !empty($row->email) ? $row->email : null;

					// Other properties
					$info->is_notice = $row->status == 50 ? 'Y' : 'N';
					$info->status = ($row->status >= 30 && $row->display >= 20) ? 'PUBLIC' : 'SECRET';

					// Attachments
					$info->files = $this->_getFileList($row->id, $include_attachments ? $rdx : null);
					$info->file_count = count($info->files);

					// Comments
					$comment_id_map = [];
					$stmt2->execute([$row->id]);
					$cmts = $stmt2->fetchAll(PDO::FETCH_OBJ);
					foreach ($cmts as $cmt)
					{
						$comment_id_map[$cmt->id] = $dummy_id++;
					}
					foreach ($cmts as $cmt)
					{
						// Basic information
						$comment_info = new CommentModel();
						$comment_info->id = $comment_id_map[$cmt->id] ?? $dummy_id++;
						$comment_info->parent_id = $comment_id_map[$cmt->parent_id] ?? null;
						$comment_info->content = $cmt->content;

						// Counters
						$comment_info->upvote_count = intval($cmt->assent_count ?? 0);
						$comment_info->downvote_count = abs(intval($cmt->dissent_count ?? 0));

						// Dates and IP addresses
						$comment_info->regdate = !empty($cmt->created_at) ? preg_replace('/[^0-9]/', '', $cmt->created_at) : null;
						$comment_info->last_update = !empty($cmt->updated_at) ? preg_replace('/[^0-9]/', '', $cmt->updated_at) : null;
						$comment_info->ipaddress = $cmt->ipaddress ?? null;

						// Author information
						$comment_info->user_id = !empty($cmt->author_login_id) ? $cmt->author_login_id : null;
						$comment_info->password = !empty($cmt->certify_key) ? $cmt->certify_key : null;
						$comment_info->user_name = !empty($cmt->writer) ? $cmt->writer : null;
						$comment_info->nick_name = !empty($cmt->writer) ? $cmt->writer : null;
						$comment_info->email_address = !empty($cmt->email) ? $cmt->email : null;

						// Other properties
						$comment_info->status = ($cmt->status >= 30 && $cmt->display >= 20) ? 'PUBLIC' : 'SECRET';

						// Attachments
						$comment_info->files = $this->_getFileList($cmt->id, $include_attachments ? $rdx : null);
						$comment_info->file_count = count($comment_info->files);

						$info->comments[] = $comment_info;
					}

					// Write the document info as a JSON line.
					fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
					$range_end++;

					// Increment the last list_order for the next batch.
					$last_date = $row->created_at;
				}

				// Close the temporary file.
				fclose($fp);

				// Add the JSONL entry to the zip.
				$board_title = isset($board_infos[$board_id]) ? $board_infos[$board_id]['name'] : "Board $board_id";
				$board_url = rawurlencode($board_infos[$board_id]['url'] ?? $board_id);
				$rdx->addEntry("boards/{$board_url}_{$index}.jsonl", $tempname, 'board', $board_title, "{$range_start}-{$range_end}", true);
				$index++;
				$range_start = $range_end + 1;
			}
		}
	}

	/**
	 * Get the list of boards to export.
	 *
	 * @return array
	 */
	protected function _getBoardList(): array
	{
		// Fetch the list of boards.
		$boards = [];
		$stmt = $this->db->query("SELECT * FROM " . $this->prefix . "menu_item WHERE type = 'board@board'");
		while ($row = $stmt->fetchObject())
		{
			// Convert any user lang code in the board title.
			if (preg_match('/^(user::[0-9a-f-]+)$/', $row->title, $matches))
			{
				$row->title = $this->_translateLangCode($matches[1]);
			}

			$boards[] = [
				'id' => $row->id,
				'url' => $row->url,
				'name' => $row->title,
			];
		}

		/*
		usort($boards, function($a, $b) {
			return strnatcasecmp($a['name'], $b['name']);
		});
		*/

		return $boards;
	}

	/**
	 * Get the list of files.
	 *
	 * @param string $parent_id
	 * @param ?RDXWriter $rdx
	 * @return array
	 */
	protected function _getFileList($parent_id, $rdx = null): array
	{
		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT f1.* FROM {$this->prefix}files AS f1, {$this->prefix}fileables AS f2 " .
				"WHERE f1.id = f2.file_id AND f2.fileable_id = ? ORDER BY f1.created_at ASC");
		}

		// Convert each row to a File instance.
		static $dummy_id = 1;
		$files = [];
		$stmt->execute([$parent_id]);
		while ($row = $stmt->fetchObject())
		{
			// Simple mapping
			$info = new FileModel();
			$info->id = $dummy_id++;
			$info->filename = $row->clientname;
			$info->url = 'storage/app/public/' . $row->disk . '/' . $row->path . '/' . $row->filename;
			$info->download_count = (int)$row->download_count;
			$info->regdate = !empty($row->created_at) ? preg_replace('/[^0-9]/', '', $row->created_at) : null;
			$info->ipaddress = $row->ipaddress ?? null;
			$info->file_size = (int)$row->size;
			$info->mime_type = $row->mime ?? null;

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

	/**
	 * Translate a user lang code.
	 *
	 * @param string $lang_code
	 * @return string
	 */
	protected function _translateLangCode($lang_code)
	{
		// Keep a cache of loaded lang codes.
		if (isset($this->langs[$lang_code]))
		{
			return $this->langs[$lang_code];
		}

		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT * FROM " . $this->prefix . "translation WHERE `namespace` = ? AND `item` = ?");
		}

		$langs = [];
		$stmt->execute(explode('::', $lang_code));
		while ($lang_row = $stmt->fetchObject())
		{
			$langs[$lang_row->locale] = $lang_row->value;
		}

		if (isset($langs['ko']))
		{
			return $this->langs[$lang_code] = $langs['ko'];
		}
		if (isset($langs['en']))
		{
			return $this->langs[$lang_code] = $langs['en'];
		}

		return $this->langs[$lang_code] = $lang_code;
	}
}
