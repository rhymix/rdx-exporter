<?php

namespace Rhymix\DataExchange\Drivers;

abstract class AbstractDriver
{
	/**
	 * Validate the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	abstract public function validate($vars);

	/**
	 * Export data based on the provided configuration parameters.
	 *
	 * @param array $vars
	 * @return array
	 */
	abstract public function export($vars);
}
