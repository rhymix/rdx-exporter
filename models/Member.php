<?php

namespace Rhymix\DataExchange\Models;

class Member
{
	// Basic information
	public $user_id;
	public $password;
	public $user_name;
	public $nick_name;
	public $email_address;

	// Phone number
	public $phone_number;
	public $phone_country;
	public $phone_type;

	// Dates (YmdHis) and IP addresses
	public $signup_date;
	public $signup_ipaddress;
	public $last_login_date;
	public $last_login_ipaddress;
	public $change_password_date;
	public $denied_until_date;

	// Admin flags
	public $is_admin = 'N';
	public $admin_description = '';
	public $status = 'APPROVED';

	// Other properties
	public $homepage;
	public $blog;
	public $birthday;
	public $profile_image;
	public $signature;
	public $allow_mailing = 'Y';
	public $allow_message = 'Y';
	public $extra_vars;

	// List of groups
	public $groups = [];

	// Points
	public $points = 0;

	// Constructor initializes extra_vars to an empty object
	public function __construct()
	{
		$this->extra_vars = new \stdClass();
	}
}
