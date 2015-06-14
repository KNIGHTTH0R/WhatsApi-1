<?php

namespace WhatsApi\exceptions;

/**
 * Class IncompleteMessageException
 */
class IncompleteMessageException extends CustomException
{
	/**
	 * @var
	 */
	private $input;

	/**
	 * @param null $message
	 * @param int $code
	 */
	public function __construct($message = null, $code = 0)
	{
		parent::__construct($message, $code);
	}

	/**
	 * @param $input
	 */
	public function setInput($input)
	{
		$this->input = $input;
	}

	/**
	 * @return mixed
	 */
	public function getInput()
	{
		return $this->input;
	}
}