<?php

namespace Rhymix\DataExchange\Drivers;

interface DriverInterface
{
	/**
	 * Validate the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function validate($vars);

	/**
	 * Export data based on the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function export($vars);
}
