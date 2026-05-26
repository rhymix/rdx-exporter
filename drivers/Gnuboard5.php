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

class Gnuboard5 implements DriverInterface
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
			'options_html' => view('opt_gnuboard5', [
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
			if (!in_array($type, ['member', 'message', 'board']))
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
			if (preg_match('/[^a-z0-9_]/i', $board))
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
		$rdx->setSource('Gnuboard5');
		$rdx->setTimezone('Asia/Seoul');

		// Export members.
		if (in_array('member', $export_types))
		{
			$this->_exportMembers($rdx, $include_attachments === 'Y');
		}

		// Export messages.
		if (in_array('message', $export_types))
		{
			$this->_exportMessages($rdx, $include_attachments === 'Y');
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
	 * Get the configuration file from the specified path and check if it's a valid Gnuboard installation.
	 *
	 * @param array $vars
	 * @return array
	 */
	protected function _checkConfig(array $vars): array
	{
		// Check the installation path for config file.
		$install_path = rtrim($vars['install_path'] ?? '', '/');
		$config_file_path = '/data/dbconfig.php';
		if (!$install_path || !is_dir($install_path) || !file_exists($install_path . $config_file_path))
		{
			return [
				'success' => false,
				'message' => 'Cannot find a valid Gnuboard installation at the specified path',
			];
		}

		// Load the config file.
		$config = ['success' => true];
		$constants = get_constants_in_file($install_path . $config_file_path);
		foreach ($constants as $key => $value)
		{
			$config[$key] = $value;
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
		if ($db_password !== ($config['G5_MYSQL_PASSWORD'] ?? ''))
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
			'prefix' => $config['G5_TABLE_PREFIX'] ?? '',
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
		$host = $config['G5_MYSQL_HOST'] ?? 'localhost';
		$username = $config['G5_MYSQL_USER'] ?? null;
		$password = $config['G5_MYSQL_PASSWORD'] ?? null;
		$dbname = $config['G5_MYSQL_DB'] ?? '';
		$charset = 'utf8';

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
	protected function _exportMembers(RDXWriter $rdx, $include_attachments): void
	{
		// Preparation.
		$batch = 1000;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT * FROM {$prefix}member WHERE mb_no > ? ORDER BY mb_no ASC LIMIT $batch");

		// Get the admin users.
		$admin_users = [];
		$stmt2 = $this->db->query("SELECT cf_admin FROM {$prefix}config ORDER BY cf_id ASC LIMIT 1");
		while ($row = $stmt2->fetchObject())
		{
			$admin_users[$row->cf_admin] = true;
		}

		// Loop through members in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_mb_no = 0;
		while (true)
		{
			// Load a batch of members.
			$stmt1->execute([$last_mb_no]);
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
				$info->user_id = $row->mb_id;
				$info->password = $row->mb_password;
				$info->user_name = $row->mb_name;
				$info->nick_name = $row->mb_nick;
				$info->email_address = $row->mb_email;
				if ($row->mb_hp)
				{
					$info->phone_number = preg_replace('/[^0-9]/', '', $row->mb_hp);
					$info->phone_type = 'mobile';
				}
				elseif ($row->mb_tel)
				{
					$info->phone_number = preg_replace('/[^0-9]/', '', $row->mb_tel) ?: null;
					$info->phone_type = 'home';
				}

				// Dates and IP addresses
				$info->signup_date = !empty($row->mb_datetime) ? preg_replace('/[^0-9]/', '', $row->mb_datetime) : null;
				$info->signup_ipaddress = $row->mb_ip ?? null;
				$info->last_login_date = !empty($row->mb_today_login) ? preg_replace('/[^0-9]/', '', $row->mb_today_login) : null;
				$info->last_login_ipaddress = $row->mb_login_ip ?? null;

				// Admin flags and other properties
				$info->is_admin = isset($admin_users[$row->mb_id]) ? 'Y' : 'N';
				$info->admin_description = $row->mb_memo ?? '';
				$info->homepage = ($row->mb_homepage ?? null) ?: null;
				$info->birthday = !empty($row->mb_birth) ? preg_replace('/[^0-9]/', '', $row->mb_birth) : null;
				$info->allow_mailing = $row->mb_mailling ? 'Y' : 'N';
				$info->status = $row->mb_level > 1 ? 'APPROVED' : 'DENIED';

				// Profile image
				$profile_image = sprintf('data/member_image/%s/%s.gif', substr($row->mb_id, 0, 2), $row->mb_id);
				if (file_exists($this->install_path . '/' . $profile_image) && is_readable($this->install_path . '/' . $profile_image))
				{
					if ($include_attachments)
					{
						$rdx->addFile($profile_image, $this->install_path . '/' . $profile_image);
						$info->profile_image = 'rdx:' . $profile_image;
					}
					else
					{
						$info->profile_image = 'url:' . $profile_image;
					}
				}

				// Signature
				if ($row->mb_signature)
				{
					$info->signature = nl2br(escape($row->mb_signature, false));
				}

				// Extra vars
				if (!empty($row->mb_zip1) && !empty($row->mb_zip2))
				{
					$info->extra_vars['address'] = [
						$row->mb_zip1 . $row->mb_zip2,
						$row->mb_addr1 ?? '',
						$row->mb_addr2 ?? '',
						$row->mb_addr3 ?? '',
					];
				}
				for ($j = 1; $j <= 10; $j++)
				{
					if (isset($row->{"mb_{$j}"}) && $row->{"mb_{$j}"} !== '')
					{
						$info->extra_vars["mb_{$j}"] = $row->{"mb_{$j}"};
					}
				}

				// Points
				if (isset($row->mb_point))
				{
					$info->points = (int)$row->mb_point;
				}

				// Write the member info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last mb_no for the next batch.
				if ($row->mb_no > $last_mb_no)
				{
					$last_mb_no = (int)$row->mb_no;
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
	 * Export messages to the zip file.
	 *
	 * @param RDXWriter $rdx
	 * @param bool $include_attachments
	 * @return void
	 */
	protected function _exportMessages(RDXWriter $rdx, $include_attachments): void
	{
		// Preparation.
		$batch = 1000;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT * FROM {$prefix}memo WHERE me_id > ? ORDER BY me_id ASC LIMIT $batch");

		// Loop through messages in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_me_id = 0;
		while (true)
		{
			// Load a batch of messages.
			$stmt1->execute([$last_me_id]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_message_');
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Message instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Simple mapping
				$info = new MessageModel();
				$info->id = (int)$row->me_id;
				$info->sender_user_id = $row->me_send_mb_id ?? null;
				$info->recipient_user_id = $row->me_recv_mb_id ?? null;
				$info->content = nl2br(escape($row->me_memo, false));
				$info->sent_date = !empty($row->me_send_datetime) ? preg_replace('/[^0-9]/', '', $row->me_send_datetime) : null;
				$info->read_date = !empty($row->me_read_datetime) ? preg_replace('/[^0-9]/', '', $row->me_read_datetime) : null;
				if ($info->read_date && substr($info->read_date, 0, 4) === '0000')
				{
					$info->read_date = null;
				}
				$info->ipaddress = $row->me_send_ip ?? null;
				$info->folder = $row->me_type === 'send' ? 'Sent' : 'Inbox';
				if ($row->me_send_id)
				{
					$info->references[] = (int)$row->me_send_id;
				}

				// Write the message info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last me_id for the next batch.
				if ($row->me_id > $last_me_id)
				{
					$last_me_id = (int)$row->me_id;
				}
			}

			// Close the temporary file.
			fclose($fp);

			// Add the JSONL entry to the zip.
			$rdx->addEntry("messages/message_{$index}.jsonl", $tempname, 'message', '', "{$range_start}-{$range_end}", true);
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

		// Get board titles for the selected module_srls.
		$board_infos = [];
		foreach ($this->_getBoardList() as $board)
		{
			if (in_array($board['bo_table'], $boards))
			{
				$board_infos[$board['bo_table']] = $board;
			}
		}

		// Loop through boards.
		foreach ($boards as $board)
		{
			$stmt1 = $this->db->prepare("SELECT * FROM {$prefix}write_{$board} " .
				"WHERE wr_is_comment = 0 AND wr_id > ? ORDER BY wr_id ASC LIMIT $batch");
			$stmt2 = $this->db->prepare("SELECT * FROM {$prefix}write_{$board} " .
				"WHERE wr_is_comment = 1 AND wr_parent = ? ORDER BY wr_comment ASC, wr_comment_reply ASC");

			// Loop through documents in batches.
			$index = 1;
			$range_start = 1;
			$range_end = 0;
			$last_wr_id = 0;
			while (true)
			{
				// Load a batch of documents.
				$stmt1->execute([$last_wr_id]);
				$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
				if (!count($rows))
				{
					break;
				}

				// Open a temporary file for this batch.
				$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', "rdx_board_{$board}_");
				$fp = fopen($tempname, 'wb');

				// Convert each row to a Document instance and write as a JSON line.
				foreach ($rows as $row)
				{
					// Basic information
					$info = new DocumentModel();
					$info->id = (int)$row->wr_id;
					$info->parent_id = $row->wr_reply ? abs(intval($row->wr_num)) : null;
					$info->category = $row->ca_name ?? null;
					$info->title = $row->wr_subject;
					$info->content = $row->wr_content;
					$info->slug = $row->wr_seo_title ?? null;

					// Counters
					$info->read_count = intval($row->wr_hit ?? 0);
					$info->upvote_count = intval($row->wr_good ?? 0);
					$info->downvote_count = abs(intval($row->wr_nogood ?? 0));
					$info->comment_count = intval($row->wr_comment ?? 0);
					$info->file_count = intval($row->wr_file ?? 0);

					// Dates and IP addresses
					$info->regdate = !empty($row->wr_datetime) ? preg_replace('/[^0-9]/', '', $row->wr_datetime) : null;
					$info->last_update = !empty($row->wr_last) ? preg_replace('/[^0-9]/', '', $row->wr_last) : null;
					$info->ipaddress = $row->wr_ip ?? null;

					// Author information
					$info->user_id = $row->mb_id ?? null;
					$info->password = !empty($row->wr_password) ? $row->wr_password : null;
					$info->user_name = !empty($row->wr_name) ? $row->wr_name : null;
					$info->nick_name = !empty($row->wr_name) ? $row->wr_name : null;
					$info->email_address = !empty($row->wr_email) ? $row->wr_email : null;
					$info->homepage = !empty($row->wr_homepage) ? $row->wr_homepage : null;

					// Other properties
					$info->is_notice = in_array($row->wr_id, $board_infos[$board]['notices'] ?? []) ? 'Y' : 'N';
					$info->status = strpos($row->wr_option ?? '', 'secret') !== false ? 'SECRET' : 'PUBLIC';

					// Attachments
					$info->files = $this->_getFileList($board, $row->wr_id, $include_attachments ? $rdx : null);

					// Links
					if (!empty($row->wr_link1))
					{
						$info->links[] = $row->wr_link1;
					}
					if (!empty($row->wr_link2))
					{
						$info->links[] = $row->wr_link2;
					}

					// Extra vars
					for ($j = 1; $j <= 10; $j++)
					{
						if (isset($row->{"wr_{$j}"}) && $row->{"wr_{$j}"} !== '')
						{
							$info->extra_vars["wr_{$j}"] = $row->{"wr_{$j}"};
						}
					}

					// Comments
					$stmt2->execute([$row->wr_id]);
					$cmts = $stmt2->fetchAll(PDO::FETCH_OBJ);
					$reply_map = [];
					foreach ($cmts as $cmt)
					{
						$reply_key = $cmt->wr_comment_reply ? ord($cmt->wr_comment_reply) : 64;
						$reply_map[$cmt->wr_parent . '/' . $cmt->wr_comment . '/' . $reply_key] = intval($cmt->wr_id);
					}
					foreach ($cmts as $cmt)
					{
						// Basic information
						$comment_info = new CommentModel();
						$comment_info->id = (int)$cmt->wr_id;
						$comment_info->content = $cmt->wr_content;

						// Parent mapping for replies
						if ($cmt->wr_comment_reply)
						{
							$reply_key = ord($cmt->wr_comment_reply) - 1;
							$comment_info->parent_id = $reply_map[$cmt->wr_parent . '/' . $cmt->wr_comment . '/' . $reply_key] ?? null;
						}

						// Counters
						$comment_info->upvote_count = intval($cmt->wr_good ?? 0);
						$comment_info->downvote_count = abs(intval($cmt->wr_nogood ?? 0));
						$comment_info->file_count = intval($cmt->wr_file ?? 0);

						// Dates and IP addresses
						$comment_info->regdate = !empty($cmt->wr_datetime) ? preg_replace('/[^0-9]/', '', $cmt->wr_datetime) : null;
						$comment_info->last_update = !empty($cmt->wr_last) ? preg_replace('/[^0-9]/', '', $cmt->wr_last) : null;
						$comment_info->ipaddress = $cmt->wr_ip ?? null;

						// Author information
						$comment_info->user_id = $cmt->mb_id ?? null;
						$comment_info->password = !empty($cmt->wr_password) ? $cmt->wr_password : null;
						$comment_info->user_name = !empty($cmt->wr_name) ? $cmt->wr_name : null;
						$comment_info->nick_name = !empty($cmt->wr_name) ? $cmt->wr_name : null;
						$comment_info->email_address = !empty($cmt->wr_email) ? $cmt->wr_email : null;
						$comment_info->homepage = !empty($cmt->wr_homepage) ? $cmt->wr_homepage : null;

						// Other properties
						$comment_info->status = strpos($cmt->wr_option ?? '', 'secret') !== false ? 'SECRET' : 'PUBLIC';

						$info->comments[] = $comment_info;
					}

					// Write the document info as a JSON line.
					fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
					$range_end++;

					// Increment the last wr_id for the next batch.
					if ($row->wr_id > $last_wr_id)
					{
						$last_wr_id = (int)$row->wr_id;
					}
				}

				// Close the temporary file.
				fclose($fp);

				// Add the JSONL entry to the zip.
				$board_title = isset($board_infos[$board]) ? $board_infos[$board]['name'] : "Board $board";
				$board_safe_name = rawurlencode($board);
				$rdx->addEntry("boards/{$board_safe_name}_{$index}.jsonl", $tempname, 'board', $board_title, "{$range_start}-{$range_end}", true);
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
		$stmt = $this->db->query("SELECT bo_table, bo_subject, bo_notice FROM " . $this->prefix . "board");
		while ($row = $stmt->fetchObject())
		{
			$boards[] = [
				'bo_table' => $row->bo_table,
				'name' => $row->bo_subject,
				'notices' => array_map('intval', explode(',', $row->bo_notice)),
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
	 * @param string $bo_table
	 * @param int $wr_id
	 * @param ?RDXWriter $rdx
	 * @return array
	 */
	protected function _getFileList($bo_table, $wr_id, $rdx = null): array
	{
		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT * FROM " . $this->prefix . "board_file WHERE bo_table = ? AND wr_id = ? ORDER BY bf_no ASC");
		}

		// Convert each row to a File instance.
		$files = [];
		$stmt->execute([$bo_table, $wr_id]);
		while ($row = $stmt->fetchObject())
		{
			// Simple mapping
			$info = new FileModel();
			$info->id = $row->bf_no + 1;
			$info->filename = $row->bf_source;
			$info->url = 'data/file/' . $bo_table . '/' . $row->bf_file;
			$info->download_count = (int)$row->bf_download;
			$info->regdate = !empty($row->bf_datetime) ? preg_replace('/[^0-9]/', '', $row->bf_datetime) : null;
			$info->file_size = (int)$row->bf_filesize;
			$info->width = isset($row->bf_width) ? (int)$row->bf_width : null;
			$info->height = isset($row->bf_height) ? (int)$row->bf_height : null;

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
