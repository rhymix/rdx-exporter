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

class Rhymix extends AbstractDriver
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
			'options_html' => view('opt_rhymix', [
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
		$module_srls = $vars['module_srls'] ?? [];
		if (in_array('board', $export_types) && (empty($module_srls) || !is_array($module_srls)))
		{
			return [
				'success' => false,
				'message' => 'At least one board must be selected for board export',
			];
		}
		if (!in_array('board', $export_types))
		{
			$module_srls = [];
		}
		foreach ($module_srls as $module_srl)
		{
			if (!is_numeric($module_srl))
			{
				return [
					'success' => false,
					'message' => 'Invalid board ID: ' . $module_srl,
				];
			}
		}

		// Open the output file for writing.
		$path = \RDX_EXPORTER_PATH . '/temp/' . 'rdx_export_' . time() . '.zip';
		$rdx = new RDXWriter($path);
		$rdx->setSource($this instanceof XE1 ? 'XE1' : 'Rhymix');
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
			$module_srls = array_values(array_map('intval', $module_srls));
			$this->_exportBoards($rdx, $module_srls, $include_attachments === 'Y');
		}

		// Finalize the output file.
		$rdx->finalize();

		// Return the download URL for the generated file.
		return [
			'success' => true,
			'download_url' => './temp/' . basename($path),
		];
	}

	/**
	 * Get the configuration file from the specified path and check if it's a valid Rhymix or XE1 installation.
	 *
	 * @param array $vars
	 * @return array
	 */
	protected function _checkConfig(array $vars): array
	{
		// Rhymix and XE1 have different config file paths.
		if ($this instanceof XE1)
		{
			$config_file_path = '/files/config/db.config.php';
		}
		else
		{
			$config_file_path = '/files/config/config.php';
		}

		// Check the installation path for config file.
		$install_path = rtrim($vars['install_path'] ?? '', '/');
		if (!$install_path || !is_dir($install_path) || !file_exists($install_path . $config_file_path))
		{
			return [
				'success' => false,
				'message' => 'Cannot find a valid ' . $vars['cms_type'] . ' installation at the specified path',
			];
		}

		// Load the config file.
		$config = call_user_func(function($filename) {
			define('__XE__', true);
			$db_info = null;
			$config = include $filename;
			return isset($db_info) ? get_object_vars($db_info) : $config;
		}, $install_path . $config_file_path);

		$config['success'] = true;
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
		if ($vars['cms_type'] === 'Rhymix' && $db_password !== ($config['db']['master']['pass'] ?? ''))
		{
			return [
				'success' => false,
				'message' => 'Incorrect database password',
			];
		}
		if ($vars['cms_type'] === 'XE1' && $db_password !== ($config['master_db']['db_password'] ?? ''))
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
		if (isset($config['db']['master']))
		{
			$host = $config['db']['master']['host'] ?? 'localhost';
			$port = $config['db']['master']['port'] ?? '3306';
			$username = $config['db']['master']['user'] ?? null;
			$password = $config['db']['master']['pass'] ?? null;
			$dbname = $config['db']['master']['database'] ?? '';
			$charset = $config['db']['master']['charset'] ?? 'utf8';
			$prefix = $config['db']['master']['prefix'] ?? '';
		}
		else
		{
			$host = $config['master_db']['db_hostname'] ?? 'localhost';
			$port = $config['master_db']['db_port'] ?? '3306';
			$username = $config['master_db']['db_userid'] ?? null;
			$password = $config['master_db']['db_password'] ?? null;
			$dbname = $config['master_db']['db_database'] ?? '';
			$charset = 'utf8';
			$prefix = $config['master_db']['db_table_prefix'] ?? '';
		}

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
		$stmt1 = $this->db->prepare("SELECT m.*, p.point FROM {$prefix}member AS m " .
			"LEFT JOIN {$prefix}point AS p ON m.member_srl = p.member_srl " .
			"WHERE m.member_srl > ? ORDER BY m.member_srl ASC LIMIT $batch");
		$skip_extra_vars = [
			'success_return_url' => true,
			'error_return_url' => true,
			'xe_validator_id' => true,
			'__profile_image_exist' => true,
			'__image_name_exist' => true,
			'__image_mark_exist' => true,
			'birthday_ui' => true,
		];

		// Pre-fetch the list of groups.
		$all_groups = [];
		$stmt = $this->db->query("SELECT group_srl, title FROM {$prefix}member_group");
		while ($row = $stmt->fetchObject())
		{
			if (preg_match('/^\$user_lang->(\w+)/', $row->title, $matches))
			{
				$row->title = $this->_translateLangCode($matches[1]);
			}
			else
			{
				$row->title = htmlspecialchars_decode($row->title, ENT_QUOTES);
			}
			$all_groups[$row->group_srl] = $row->title;
		}

		// Loop through members in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_member_srl = 0;
		while (true)
		{
			// Load a batch of members.
			$stmt1->execute([$last_member_srl]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Load their group information.
			$stmt2 = $this->db->query("SELECT member_srl, group_srl FROM {$prefix}member_group_member " .
				"WHERE member_srl IN (" . implode(', ', array_map(function($row) { return (int)$row->member_srl; }, $rows)) . ")");
			$groups = [];
			while ($row = $stmt2->fetchObject())
			{
				if (isset($all_groups[$row->group_srl]))
				{
					$groups[$row->member_srl][] = $all_groups[$row->group_srl];
				}
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_member_') . '.jsonl';
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Member instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Simple mapping
				$info = new MemberModel();
				$info->user_id = $row->user_id;
				$info->password = $row->password;
				$info->user_name = $row->user_name;
				$info->nick_name = $row->nick_name;
				$info->email_address = $row->email_address;
				$info->phone_number = ($row->phone_number ?? null) ?: null;
				$info->phone_country = ($row->phone_country ?? null) ?: null;
				$info->phone_type = ($row->phone_type ?? null) ?: null;
				$info->signup_date = $row->regdate ?? null;
				$info->signup_ipaddress = $row->ipaddress ?? null;
				$info->last_login_date = ($row->last_login ?? null) ?: null;
				$info->last_login_ipaddress = ($row->last_login_ipaddress ?? null) ?: null;
				$info->change_password_date = ($row->change_password_date ?? null) ?: null;
				$info->denied_until_date = ($row->limit_date ?? null) ?: null;
				$info->is_admin = $row->is_admin === 'Y' ? 'Y' : 'N';
				$info->admin_description = $row->description ?? '';
				$info->homepage = ($row->homepage ?? null) ?: null;
				$info->blog = ($row->blog ?? null) ?: null;
				$info->birthday = ($row->birthday ?? null) ?: null;
				$info->allow_mailing = $row->allow_mailing === 'Y' ? 'Y' : 'N';
				$info->allow_message = $row->allow_message === 'Y' ? 'Y' : 'N';

				// Status
				if (empty($row->status))
				{
					$info->status = $row->denied === 'Y' ? 'DENIED' : 'APPROVED';
				}
				else
				{
					$info->status = $row->status;
				}

				// Profile image
				foreach(['jpg', 'jpeg', 'gif', 'png'] as $ext)
				{
					$profile_image = sprintf('files/member_extra_info/profile_image/%s%d.%s', $this->_getNumberingPath($row->member_srl), $row->member_srl, $ext);
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
						break;
					}
				}

				// Signature
				$sig_filename = sprintf('files/member_extra_info/signature/%s%d.signature.php', $this->_getNumberingPath($row->member_srl), $row->member_srl);
				if (file_exists($this->install_path . '/' . $sig_filename) && is_readable($this->install_path . '/' . $sig_filename))
				{
					$signature = trim(preg_replace('/<\?.*\?>/', '', file_get_contents($this->install_path . '/' . $sig_filename)));
					if ($signature !== '')
					{
						$info->signature = $signature;
					}
				}

				// Extra vars
				if (isset($row->extra_vars) && $row->extra_vars)
				{
					foreach (unserialize($row->extra_vars) as $key => $value)
					{
						if (isset($skip_extra_vars[$key]))
						{
							continue;
						}
						$info->extra_vars[$key] = $value;
					}
				}

				// Groups
				if (isset($groups[$row->member_srl]))
				{
					$info->groups = $groups[$row->member_srl];
				}

				// Points
				if (isset($row->point))
				{
					$info->points = (int)$row->point;
				}

				// Write the member info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last member_srl for the next batch.
				if ($row->member_srl > $last_member_srl)
				{
					$last_member_srl = (int)$row->member_srl;
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
		$stmt1 = $this->db->prepare("SELECT msg.*, m1.user_id AS sender_user_id, m2.user_id AS receiver_user_id " .
			"FROM {$prefix}member_message AS msg " .
			"LEFT JOIN {$prefix}member AS m1 ON msg.sender_srl = m1.member_srl " .
			"LEFT JOIN {$prefix}member AS m2 ON msg.receiver_srl = m2.member_srl " .
			"WHERE msg.message_srl > ? ORDER BY msg.message_srl ASC LIMIT $batch");

		// Loop through messages in batches.
		$index = 1;
		$range_start = 1;
		$range_end = 0;
		$last_message_srl = 0;
		while (true)
		{
			// Load a batch of messages.
			$stmt1->execute([$last_message_srl]);
			$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
			if (!count($rows))
			{
				break;
			}

			// Open a temporary file for this batch.
			$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_message_') . '.jsonl';
			$fp = fopen($tempname, 'wb');

			// Convert each row to a Message instance and write as a JSON line.
			foreach ($rows as $row)
			{
				// Simple mapping
				$info = new MessageModel();
				$info->id = (int)$row->message_srl;
				$info->sender_user_id = $row->sender_user_id ?? null;
				$info->recipient_user_id = $row->receiver_user_id ?? null;
				$info->title = $row->title;
				$info->content = $row->content;
				$info->sent_date = $row->regdate ?? null;
				$info->read_date = $row->readed_date ?? null;
				if (!$info->read_date && $row->readed === 'Y')
				{
					$info->read_date = $info->sent_date;
				}
				$info->folder = $row->message_type === 'S' ? 'Sent' : 'Inbox';
				if ($row->related_srl)
				{
					$info->references[] = (int)$row->related_srl;
				}

				// Attachments
				$info->files = $this->_getFileList($row->message_srl, $include_attachments ? $rdx : null);

				// Write the message info as a JSON line.
				fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
				$range_end++;

				// Increment the last message_srl for the next batch.
				if ($row->message_srl > $last_message_srl)
				{
					$last_message_srl = (int)$row->message_srl;
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
	 * @param array $module_srls
	 * @param bool $include_attachments
	 * @return void
	 */
	protected function _exportBoards(RDXWriter $rdx, $module_srls, $include_attachments)
	{
		// Preparation.
		$batch = 100;
		$prefix = $this->prefix;
		$stmt1 = $this->db->prepare("SELECT d.*, m.user_id AS author_user_id " .
			"FROM {$prefix}documents AS d " .
			"LEFT JOIN {$prefix}member AS m ON d.member_srl = m.member_srl " .
			"WHERE d.module_srl = ? AND d.list_order > ? ORDER BY d.list_order ASC LIMIT $batch");
		$stmt2 = $this->db->prepare("SELECT MIN(list_order) FROM {$prefix}documents WHERE module_srl = ?");
		$stmt3 = $this->db->prepare("SELECT * FROM {$prefix}document_extra_keys WHERE module_srl = ? ORDER BY var_idx ASC");
		$stmt4 = $this->db->prepare("SELECT * FROM {$prefix}document_extra_vars WHERE module_srl = ? AND document_srl = ? ORDER BY var_idx ASC");
		$stmt5 = $this->db->prepare("SELECT c.*, m.user_id AS author_user_id " .
			"FROM {$prefix}comments AS c " .
			"LEFT JOIN {$prefix}member AS m ON c.member_srl = m.member_srl " .
			"WHERE c.document_srl = ? ORDER BY c.comment_srl ASC");
		$array_ev_types = [
			'checkbox' => true,
			'radio' => true,
			'select' => true,
			'kr_zip' => true,
			'tel' => true,
			'tel_v2' => true,
			'tel_intl' => true,
			'tel_intl_v2' => true,
		];

		// Get board titles for the selected module_srls.
		$board_infos = [];
		foreach ($this->_getBoardList() as $board)
		{
			if (in_array($board['module_srl'], $module_srls))
			{
				$board_infos[$board['module_srl']] = $board;
			}
		}

		// Loop through boards.
		foreach ($module_srls as $module_srl)
		{
			// Get categories for this board.
			$categories = $this->_getCategoryList($module_srl);

			// Get the minimum list_order for this board.
			$stmt2->execute([$module_srl]);
			$min_list_order = $stmt2->fetchColumn();
			$stmt2->closeCursor();
			if ($min_list_order)
			{
				$min_list_order = $min_list_order - 1;
			}
			else
			{
				$min_list_order = -2147483648;
			}

			// Get the extra keys for this board.
			$extra_keys = [];
			$stmt3->execute([$module_srl]);
			while ($row = $stmt3->fetchObject())
			{
				$extra_keys[$row->eid] = $row;
			}

			// Loop through documents in batches.
			$index = 1;
			$range_start = 1;
			$range_end = 0;
			$last_list_order = $min_list_order;
			while (true)
			{
				// Load a batch of documents.
				$stmt1->execute([$module_srl, $last_list_order]);
				$rows = $stmt1->fetchAll(PDO::FETCH_OBJ);
				if (!count($rows))
				{
					break;
				}

				// Open a temporary file for this batch.
				$tempname = tempnam(\RDX_EXPORTER_PATH . '/temp', 'rdx_document_') . '.jsonl';
				$fp = fopen($tempname, 'wb');

				// Convert each row to a Document instance and write as a JSON line.
				foreach ($rows as $row)
				{
					// Basic information
					$info = new DocumentModel();
					$info->id = (int)$row->document_srl;
					if ($row->category_srl)
					{
						$info->category = $categories[$row->category_srl] ?? "Category {$row->category_srl}";
					}
					$info->lang_code = isset($row->lang_code) ? strtolower($row->lang_code) : null;
					if ($info->lang_code === 'jp')
					{
						$info->lang_code = 'ja';
					}
					$info->title = htmlspecialchars_decode($row->title, ENT_QUOTES);
					$info->content = $row->content;
					if (isset($row->tags) && $row->tags)
					{
						$info->tags = array_map('trim', explode(',', $row->tags));
					}

					// Counters
					$info->read_count = intval($row->readed_count ?? 0);
					$info->upvote_count = intval($row->voted_count ?? 0);
					$info->downvote_count = intval($row->blamed_count ?? 0);
					$info->comment_count = intval($row->comment_count ?? 0);
					$info->trackback_count = intval($row->trackback_count ?? 0);
					$info->file_count = intval($row->uploaded_count ?? 0);

					// Dates and IP addresses
					$info->regdate = $row->regdate ?? null;
					$info->last_update = $row->last_update ?? null;
					$info->ipaddress = $row->ipaddress ?? null;

					// Author information
					$info->user_id = $row->author_user_id ?? ($row->user_id ?: null);
					$info->password = !empty($row->password) ? $row->password : null;
					$info->user_name = !empty($row->user_name) ? $row->user_name : null;
					$info->nick_name = !empty($row->nick_name) ? $row->nick_name : null;
					$info->email_address = !empty($row->email_address) ? $row->email_address : null;
					$info->homepage = !empty($row->homepage) ? $row->homepage : null;

					// Other properties
					$info->allow_comment = $row->comment_status === 'ALLOW' ? 'Y' : 'N';
					$info->allow_trackback = $row->allow_trackback === 'Y' ? 'Y' : 'N';
					$info->notify_message = $row->notify_message === 'Y' ? 'Y' : 'N';
					$info->is_notice = $row->is_notice ?? 'N';
					$info->title_bold = $row->title_bold === 'Y' ? 'Y' : 'N';
					$info->title_color = !empty($row->title_color) ? $row->title_color : null;
					$info->status = $row->status ?? 'PUBLIC';

					// Attachments
					$info->files = $this->_getFileList($row->document_srl, $include_attachments ? $rdx : null);

					// Extra vars
					if (count($extra_keys))
					{
						$stmt4->execute([$module_srl, $row->document_srl]);
						while ($ev = $stmt4->fetchObject())
						{
							$ev_type = $extra_keys[$ev->eid]->var_type ?? null;
							if ($ev_type && isset($array_ev_types[$ev_type]))
							{
								if (preg_match('/^[\[\{].*[\]\}]$/', $ev->value))
								{
									$info->extra_vars[$ev->eid] = @json_decode($ev->value, true) ?: [];
								}
								elseif (str_contains($ev->value, '|@|'))
								{
									$info->extra_vars[$ev->eid] = explode('|@|', $ev->value);
								}
								else
								{
									$info->extra_vars[$ev->eid] = [$ev->value];
								}
							}
							else
							{
								$info->extra_vars[$ev->eid] = $ev->value;
							}
						}
					}

					// Comments
					$stmt5->execute([$row->document_srl]);
					while ($cmt = $stmt5->fetchObject())
					{
						// Basic information
						$comment_info = new CommentModel();
						$comment_info->id = (int)$cmt->comment_srl;
						$comment_info->document_id = (int)$cmt->document_srl;
						$comment_info->parent_id = $cmt->parent_srl ? (int)$cmt->parent_srl : null;
						$comment_info->content = $cmt->content;

						// Counters
						$comment_info->upvote_count = intval($cmt->voted_count ?? 0);
						$comment_info->downvote_count = intval($cmt->blamed_count ?? 0);
						$comment_info->file_count = intval($cmt->uploaded_count ?? 0);

						// Dates and IP addresses
						$comment_info->regdate = $cmt->regdate ?? null;
						$comment_info->last_update = $cmt->last_update ?? null;
						$comment_info->ipaddress = $cmt->ipaddress ?? null;

						// Author information
						$comment_info->user_id = $cmt->author_user_id ?? ($cmt->user_id ?: null);
						$comment_info->password = !empty($cmt->password) ? $cmt->password : null;
						$comment_info->user_name = !empty($cmt->user_name) ? $cmt->user_name : null;
						$comment_info->nick_name = !empty($cmt->nick_name) ? $cmt->nick_name : null;
						$comment_info->email_address = !empty($cmt->email_address) ? $cmt->email_address : null;
						$comment_info->homepage = !empty($cmt->homepage) ? $cmt->homepage : null;

						// Other properties
						$comment_info->notify_message = $cmt->notify_message === 'Y' ? 'Y' : 'N';
						$comment_info->status = $cmt->status > 0 ? 'PUBLIC' : 'SECRET';

						// Comment attachments
						$comment_info->files = $this->_getFileList($cmt->comment_srl, $include_attachments ? $rdx : null);

						$info->comments[] = $comment_info;
					}

					// Write the document info as a JSON line.
					fwrite($fp, json_encode($info, \JSON_UNESCAPED_SLASHES) . "\n");
					$range_end++;

					// Increment the last list_order for the next batch.
					if ($row->list_order > $last_list_order)
					{
						$last_list_order = (int)$row->list_order;
					}
				}

				// Close the temporary file.
				fclose($fp);

				// Add the JSONL entry to the zip.
				$board_title = isset($board_infos[$module_srl]) ? $board_infos[$module_srl]['name'] : "Board $module_srl";
				$board_mid = $board_infos[$module_srl]['mid'] ?? $module_srl;
				$rdx->addEntry("boards/{$board_mid}_{$index}.jsonl", $tempname, 'board', $board_title, "{$range_start}-{$range_end}", true);
				$index++;
				$range_start = $range_end + 1;
			}
		}
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
			$stmt = $this->db->prepare("SELECT lang_code, value FROM " . $this->prefix . "lang WHERE name = ?");
		}

		$langs = [];
		$stmt->execute([$lang_code]);
		while ($lang_row = $stmt->fetchObject())
		{
			$langs[$lang_row->lang_code] = $lang_row->value;
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

	/**
	 * Get the list of boards to export.
	 *
	 * @return array
	 */
	protected function _getBoardList(): array
	{
		// Fetch the list of boards.
		$boards = [];
		$stmt = $this->db->query("SELECT module_srl, mid, browser_title FROM " . $this->prefix . "modules WHERE module = 'board'");
		while ($row = $stmt->fetchObject())
		{
			// Convert any user lang code in the board title.
			if (preg_match('/^\$user_lang->(\w+)/', $row->browser_title, $matches))
			{
				$row->browser_title = $this->_translateLangCode($matches[1]);
			}

			$boards[] = [
				'module_srl' => $row->module_srl,
				'mid' => $row->mid,
				'name' => $row->browser_title,
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
	 * Get the list of categores in a board.
	 *
	 * @param int $module_srl
	 * @return array
	 */
	protected function _getCategoryList($module_srl): array
	{
		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT category_srl, title FROM " . $this->prefix . "document_categories WHERE module_srl = ? ORDER BY category_srl ASC");
		}

		// Convert each row to a File instance.
		$categories = [];
		$stmt->execute([$module_srl]);
		while ($row = $stmt->fetchObject())
		{
			if (preg_match('/^\$user_lang->(\w+)/', $row->title, $matches))
			{
				$row->title = $this->_translateLangCode($matches[1]);
			}

			$categories[$row->category_srl] = $row->title;
		}

		return $categories;
	}

	/**
	 * Get the list of files.
	 *
	 * @param int $target_srl
	 * @param ?RDXWriter $rdx
	 * @return array
	 */
	protected function _getFileList($target_srl, $rdx = null): array
	{
		// Prepare a statement.
		static $stmt = null;
		if (!$stmt)
		{
			$stmt = $this->db->prepare("SELECT * FROM " . $this->prefix . "files WHERE upload_target_srl = ? ORDER BY file_srl ASC");
		}

		// Convert each row to a File instance.
		$files = [];
		$stmt->execute([$target_srl]);
		while ($row = $stmt->fetchObject())
		{
			// Simple mapping
			$info = new FileModel();
			$info->id = (int)$row->file_srl;
			$info->filename = $row->source_filename;
			$info->url = ltrim($row->uploaded_filename, './');
			$info->download_count = (int)$row->download_count;
			$info->regdate = $row->regdate ?? null;
			$info->ipaddress = $row->ipaddress ?? null;
			$info->file_size = (int)$row->file_size;
			$info->mime_type = $row->mime_type ?? null;
			$info->original_type = $row->original_type ?? null;
			$info->width = isset($row->width) ? (int)$row->width : null;
			$info->height = isset($row->height) ? (int)$row->height : null;
			$info->duration = isset($row->duration) ? (int)$row->duration : null;
			$info->is_valid = $row->isvalid === 'Y' ? 'Y' : 'N';
			$info->is_cover_image = $row->cover_image === 'Y' ? 'Y' : 'N';
			$info->comment = trim($row->comment ?? '');

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
	 * Get a numbering path for a given number.
	 *
	 * @param int $no
	 * @param int $size
	 * @return string
	 */
	protected function _getNumberingPath($no, $size = 3)
	{
		$no = intval($no);
		$mod = pow(10, $size);
		$output = sprintf('%0' . $size . 'd/', intval($no % $mod));
		if($no >= $mod)
		{
			$output .= $this->_getNumberingPath(intval($no / $mod), $size);
		}
		return $output;
	}
}
