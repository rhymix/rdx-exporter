<?php

namespace Rhymix\DataExchange\Models;

class Comment
{
	// Basic information
	public $id;
	public $parent_id;
	public $content;

	// Counters
	public $upvote_count = 0;
	public $downvote_count = 0;
	public $comment_count = 0;
	public $trackback_count = 0;
	public $file_count = 0;

	// Dates (YmdHis) and IP addresses
	public $regdate;
	public $last_update;
	public $ipaddress;

	// Author information
	public $user_id;
	public $password;
	public $user_name;
	public $nick_name;
	public $email_address;
	public $homepage;

	// Other properties
	public $notify_message = 'N';
	public $status = 'PUBLIC';
	public $extra_vars = [];

	// Files
	public $files = [];
}
