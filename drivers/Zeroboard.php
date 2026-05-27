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

class Zeroboard implements DriverInterface
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
			'options_html' => view('opt_zeroboard', [
				'boards' => $this->_getBoardList(),
				'charset' => $config['charset'],
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
		$rdx->setSource('Zeroboard');
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
	 * Get the configuration file from the specified path and check if it's a valid Zeroboard installation.
	 *
	 * @param array $vars
	 * @return array
	 */
	protected function _checkConfig(array $vars): array
	{
		// Check the installation path for config file.
		$install_path = rtrim($vars['install_path'] ?? '', '/');
		$config_file_path = '/config.php';
		if (!$install_path || !is_dir($install_path) || !file_exists($install_path . $config_file_path))
		{
			return [
				'success' => false,
				'message' => 'Cannot find a valid Zeroboard installation at the specified path',
			];
		}

		// Load the config file.
		$lines = explode("\n", file_get_contents($install_path . $config_file_path));
		if (count($lines) < 6)
		{
			return [
				'success' => false,
				'message' => 'Invalid Zeroboard configuration file',
			];
		}
		$config = [
			'success' => true,
			'host' => trim($lines[1]),
			'user' => trim($lines[2]),
			'password' => trim($lines[3]),
			'dbname' => trim($lines[4]),
		];

		// Check the character set.
		if (isset($vars['db_charset']) && in_array(strtolower(preg_replace('/[^a-z0-9]/i', '', $vars['db_charset'])), ['euckr', 'utf8']))
		{
			$config['charset'] = strtolower(preg_replace('/[^a-z0-9]/i', '', $vars['db_charset']));
		}
		else
		{
			$check_file_path = '/zboard.php';
			$content = file_get_contents($install_path . $check_file_path);
			if (mb_check_encoding($content, 'CP949'))
			{
				$config['charset'] = 'euckr';
			}
			else
			{
				$config['charset'] = 'utf8';
			}
		}

		// Set the default zb4 prefix.
		$config['prefix'] = 'zetyx_';

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
			'prefix' => $config['prefix'] ?? '',
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
		$host = $config['host'] ?? 'localhost';
		$username = $config['user'] ?? null;
		$password = $config['password'] ?? null;
		$dbname = $config['dbname'] ?? '';
		$charset = $config['charset'] ?? 'euckr';

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
		$stmt1 = $this->db->prepare("SELECT * FROM {$prefix}member_table WHERE `no` > ? ORDER BY `no` ASC LIMIT $batch");

		// Loop through members in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_no = 0;
		while (true)
		{
			// Load a batch of members.
			$stmt1->execute([$last_no]);
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
				if ($this->config['charset'] === 'euckr')
				{
					$this->_convertEncoding($row);
				}

				// Basic information
				$info = new MemberModel();
				$info->user_id = $row->user_id;
				$info->password = $row->password;
				$info->user_name = $row->name;
				$info->nick_name = $row->name;
				$info->email_address = $row->email;
				if ($row->handphone)
				{
					$info->phone_number = preg_replace('/[^0-9]/', '', $row->handphone);
				}

				// Dates and IP addresses
				$info->signup_date = !empty($row->reg_date) ? date('YmdHis', $row->reg_date) : null;
				$info->last_login_date = !empty($row->reg_m_date) ? date('YmdHis', $row->reg_m_date) : null;

				// Admin flags and other properties
				$info->is_admin = $row->is_admin ? 'Y' : 'N';
				$info->homepage = ($row->homepage ?? null) ?: null;
				if ($info->homepage && !preg_match('!^https?://!i', $info->homepage))
				{
					$info->homepage = 'http://' . $info->homepage;
				}
				$info->blog = ($row->blog ?? null) ?: null;
				if ($info->blog && !preg_match('!^https?://!i', $info->blog))
				{
					$info->blog = 'http://' . $info->blog;
				}
				$info->birthday = !empty($row->birth) ? date('Ymd', $row->birth) : null;
				$info->allow_mailing = $row->mailing ? 'Y' : 'N';
				$info->allow_message = $row->mailing ? 'Y' : 'N';
				$info->status = 'APPROVED';

				// Signature
				if ($row->comment)
				{
					$info->signature = nl2br(escape($row->comment, false));
				}

				// Points
				$info->points = (intval($row->point1) * 10) + intval($row->point2);

				// Extra vars
				foreach (['icq', 'aol', 'msn', 'job', 'hobby', 'home_address', 'home_tel', 'office_address', 'office_tel'] as $field)
				{
					if (!empty($row->{$field}))
					{
						$info->extra_vars->{$field} = trim($row->{$field});
					}
				}

				// Write the member info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last number for the next batch.
				if ($row->no > $last_no)
				{
					$last_no = (int)$row->no;
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
		$stmt1 = $this->db->prepare("SELECT memo.*, m1.user_id AS from_id, m2.user_id AS to_id FROM {$prefix}send_memo AS memo " .
			"LEFT JOIN {$prefix}member_table AS m1 ON memo.member_no = m1.no " .
			"LEFT JOIN {$prefix}member_table AS m2 ON memo.member_to = m2.no " .
			"WHERE memo.no > ? ORDER BY memo.no ASC LIMIT $batch");
		$stmt2 = $this->db->prepare("SELECT memo.*, m1.user_id AS to_id, m2.user_id AS from_id FROM {$prefix}get_memo AS memo " .
			"LEFT JOIN {$prefix}member_table AS m1 ON memo.member_no = m1.no " .
			"LEFT JOIN {$prefix}member_table AS m2 ON memo.member_from = m2.no " .
			"WHERE memo.no > ? ORDER BY memo.no ASC LIMIT $batch");

		// Loop through messages in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$loop = [
			['stmt' => $stmt1, 'type' => 'Sent'],
			['stmt' => $stmt2, 'type' => 'Inbox'],
		];
		foreach ($loop as $loop_index => $params)
		{
			$last_no = 0;
			while (true)
			{
				// Load a batch of messages.
				$params['stmt']->execute([$last_no]);
				$rows = $params['stmt']->fetchAll(PDO::FETCH_OBJ);
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
					if ($this->config['charset'] === 'euckr')
					{
						$this->_convertEncoding($row);
					}

					// Simple mapping
					$info = new MessageModel();
					$info->id = (($row->no - 1) * 2) + $loop_index + 1;
					$info->sender_user_id = $row->from_id ?? null;
					$info->recipient_user_id = $row->to_id ?? null;
					$info->content = nl2br(escape($row->memo, false));
					$info->sent_date = !empty($row->reg_date) ? date('YmdHis', $row->reg_date) : null;
					if ($row->readed == 0)
					{
						$info->read_date = $info->sent_date;
					}
					$info->folder = $params['type'];

					// Write the message info as a JSON line.
					fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
					$range_end++;

					// Increment the last number for the next batch.
					if ($row->no > $last_no)
					{
						$last_no = (int)$row->no;
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
			if (in_array($board['table_name'], $boards))
			{
				$board_infos[$board['table_name']] = $board;
			}
		}

		// Loop through boards.
		foreach ($boards as $board)
		{
			$stmt1 = $this->db->prepare("SELECT b.*, m.user_id FROM {$prefix}board_{$board} AS b " .
				"LEFT JOIN {$prefix}member_table AS m ON b.ismember = m.no " .
				"WHERE b.no > ? ORDER BY b.no ASC LIMIT $batch");
			$stmt2 = $this->db->prepare("SELECT c.*, m.user_id FROM {$prefix}board_comment_{$board} AS c " .
				"LEFT JOIN {$prefix}member_table AS m ON c.ismember = m.no " .
				"WHERE c.parent = ? ORDER BY c.no ASC");
			$stmt3 = $this->db->prepare("SELECT * FROM {$prefix}board_category_{$board}");

			// Get categories for this board.
			$categories = [];
			$stmt3->execute();
			while ($cat = $stmt3->fetchObject())
			{
				if ($this->config['charset'] === 'euckr')
				{
					$this->_convertEncoding($cat);
				}

				$categories[$cat->no] = $cat->name;
			}

			// Loop through documents in batches.
			$index = 1;
			$range_start = 1;
			$range_end = 0;
			$last_no = 0;
			while (true)
			{
				// Load a batch of documents.
				$stmt1->execute([$last_no]);
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
					if ($this->config['charset'] === 'euckr')
					{
						$this->_convertEncoding($row);
					}

					// Basic information
					$info = new DocumentModel();
					$info->id = (int)$row->no;
					$info->category = $categories[$row->category] ?? null;
					$info->title = $row->subject;
					if ($row->use_html === 2)
					{
						$info->content = $row->memo;
					}
					else
					{
						$info->content = nl2br(escape($row->memo, false));
					}

					// Counters
					$info->read_count = intval($row->hit ?? 0);
					$info->upvote_count = intval($row->vote ?? 0);
					$info->comment_count = intval($row->total_comment ?? 0);

					// Dates and IP addresses
					$info->regdate = !empty($row->reg_date) ? date('YmdHis', $row->reg_date) : null;
					$info->ipaddress = $row->ip ?? null;

					// Author information
					$info->user_id = $row->user_id ?? null;
					if (!$row->ismember)
					{
						$info->password = !empty($row->password) ? $row->password : null;
					}
					$info->user_name = !empty($row->name) ? $row->name : null;
					$info->nick_name = !empty($row->name) ? $row->name : null;
					$info->email_address = !empty($row->email) ? $row->email : null;
					$info->homepage = !empty($row->homepage) ? $row->homepage : null;
					if ($info->homepage && !preg_match('!^https?://!i', $info->homepage))
					{
						$info->homepage = 'http://' . $info->homepage;
					}

					// Other properties
					$info->is_notice = $row->headnum < -1000000000 ? 'Y' : 'N';
					$info->status = $row->is_secret ? 'SECRET' : 'PUBLIC';

					// Attachments
					$info->files = $this->_getFileList($row, $include_attachments ? $rdx : null);
					$info->file_count = count($info->files);

					// Links
					if (!empty($row->sitelink1))
					{
						$info->links[] = $row->sitelink1;
					}
					if (!empty($row->sitelink2))
					{
						$info->links[] = $row->sitelink2;
					}

					// Extra fields
					if (!empty($row->x))
					{
						$info->extra_vars->x = trim($row->x);
					}
					if (!empty($row->y))
					{
						$info->extra_vars->y = trim($row->y);
					}
					if (!empty($row->z))
					{
						$info->extra_vars->z = trim($row->z);
					}

					// Comments
					$stmt2->execute([$row->no]);
					while ($cmt = $stmt2->fetchObject())
					{
						if ($this->config['charset'] === 'euckr')
						{
							$this->_convertEncoding($cmt);
						}

						// Basic information
						$comment_info = new CommentModel();
						$comment_info->id = (int)$cmt->no;
						$comment_info->content = nl2br(escape($cmt->memo, false));

						// Dates and IP addresses
						$comment_info->regdate = !empty($cmt->reg_date) ? date('YmdHis', $cmt->reg_date) : null;
						$comment_info->ipaddress = $cmt->ip ?? null;

						// Author information
						$comment_info->user_id = $cmt->user_id ?? null;
						if (!$cmt->ismember)
						{
							$comment_info->password = !empty($cmt->password) ? $cmt->password : null;
						}
						$comment_info->user_name = !empty($cmt->name) ? $cmt->name : null;
						$comment_info->nick_name = !empty($cmt->name) ? $cmt->name : null;

						$info->comments[] = $comment_info;
					}

					// Write the document info as a JSON line.
					fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
					$range_end++;

					// Increment the last number for the next batch.
					if ($row->no > $last_no)
					{
						$last_no = (int)$row->no;
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
		$stmt = $this->db->query("SELECT no, name, title FROM " . $this->prefix . "admin_table ORDER BY no ASC");
		while ($row = $stmt->fetchObject())
		{
			if ($this->config['charset'] === 'euckr')
			{
				$this->_convertEncoding($row);
			}

			$boards[] = [
				'table_name' => $row->name,
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
	 * @param object $row
	 * @param ?RDXWriter $rdx
	 * @return array
	 */
	protected function _getFileList($row, $rdx = null): array
	{
		$files = [];

		// Support up to 20 files like the official XE migration tool.
		for ($file_index = 1; $file_index <= 20; $file_index++)
		{
			// Extract file info
			$filename = $row->{"s_file_name{$file_index}"} ?? null;
			$path = $row->{"file_name{$file_index}"} ?? null;
			$download_count = intval($row->{"download{$file_index}"} ?? 0);
			if (!$filename || !$path)
			{
				continue;
			}

			// Simple mapping
			$info = new FileModel();
			$info->id = ($row->no * 100) + $file_index;
			$info->filename = $filename;
			$info->url = $path;
			$info->download_count = $download_count;
			$info->regdate = !empty($row->reg_date) ? date('YmdHis', $row->reg_date) : null;
			$info->ipaddress = $row->ip ?? null;

			// Zeroboard does not store file size, so we must detect it with the actual file.
			$exists = file_exists($this->install_path . '/' . $path) && is_readable($this->install_path . '/' . $path);
			if ($exists)
			{
				$info->file_size = filesize($this->install_path . '/' . $path);
			}

			// Attach file to zip if RDXWriter is provided.
			if ($rdx && $exists)
			{
				$rdx->addFile($path, $this->install_path . '/' . $path);
				$info->path = 'rdx:' . $path;
			}
			else
			{
				$info->path = 'url:' . $path;
			}

			$files[] = $info;
		}

		return $files;
	}

	/**
	 * Convert an object encoded in EUC-KR/CP949to UTF-8.
	 *
	 * @param object $obj
	 * @return object
	 */
	protected function _convertEncoding($obj)
	{
		foreach ($obj as $key => $value)
		{
			if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8'))
			{
				$obj->{$key} = mb_convert_encoding($value, 'UTF-8', 'CP949');
			}
		}
		return $obj;
	}
}
