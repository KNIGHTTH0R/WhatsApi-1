<?php

namespace WhatsApi;

/**
 * Interface IException
 */
interface IException
{
	/**
	 * @return mixed
	 */
	public function getMessage();

	/**
	 * @return mixed
	 */
	public function getCode();

	/**
	 * @return mixed
	 */
	public function getFile();

	/**
	 * @return mixed
	 */
	public function getLine();

	/**
	 * @return mixed
	 */
	public function getTrace();

	/**
	 * @return mixed
	 */
	public function getTraceAsString();

	/**
	 * @return mixed
	 */
	public function __toString();

	/**
	 * @param null $message
	 * @param int $code
	 */
	public function __construct($message = null, $code = 0);
}