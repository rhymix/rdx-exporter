<?php

namespace Rhymix\DataExchange\Models;

class File
{
	// Basic information
	public $id;
	public $filename;
	public $path;
	public $url;

	// Counters
	public $download_count = 0;

	// Dates (YmdHis) and IP addresses
	public $regdate;
	public $ipaddress;

	// File properties
	public $file_size;
	public $mime_type;
	public $original_type;
	public $width;
	public $height;
	public $duration;

	// Other properties
	public $is_valid = 'Y';
	public $is_cover_image = 'N';
	public $comment = '';
	public $extra_vars;

	// Constructor initializes extra_vars to an empty object
	public function __construct()
	{
		$this->extra_vars = new \stdClass();
	}
}
