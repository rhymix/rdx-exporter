<?php

namespace Rhymix\DataExchange\Models;

class Message
{
	// Basic information
	public $id;
	public $title;
	public $content;

	// Sender and recipient
	public $sender_user_id;
	public $recipient_user_id;

	// Dates (YmdHis) and IP addresses
	public $sent_date;
	public $read_date;
	public $ipaddress;

	// Other properties
	public $folder = 'Inbox';
	public $references = [];
	public $extra_vars;

	// Files
	public $files = [];

	// Constructor initializes extra_vars to an empty object
	public function __construct()
	{
		$this->extra_vars = new \stdClass();
	}
}
