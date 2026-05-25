<?php

namespace Rhymix\DataExchange\Models;

class Document
{
	// Basic information
	public $id;
	public $category;
	public $lang_code;
	public $title;
	public $content;
	public $tags = [];

	// Counters
	public $read_count = 0;
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
	public $allow_comment = 'Y';
	public $allow_trackback = 'Y';
	public $notify_message = 'N';
	public $is_notice = 'N';
	public $title_bold = 'N';
	public $title_color = '';
	public $status = 'PUBLIC';
	public $extra_vars = [];

	// Comments and files
	public $comments = [];
	public $files = [];
}
