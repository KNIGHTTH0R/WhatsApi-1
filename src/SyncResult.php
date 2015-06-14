<?php

namespace WhatsApi;

/**
 * Class SyncResult
 */
class SyncResult
{
	/**
	 * @var
	 */
	public $index;
	/**
	 * @var
	 */
	public $syncId;
	/** @var array $existing */
	public $existing;
	/** @var array $nonExisting */
	public $nonExisting;

	/**
	 * @param $index
	 * @param $syncId
	 * @param $existing
	 * @param $nonExisting
	 */
	public function __construct($index, $syncId, $existing, $nonExisting)
	{
		$this->index = $index;
		$this->syncId = $syncId;
		$this->existing = $existing;
		$this->nonExisting = $nonExisting;
	}
}