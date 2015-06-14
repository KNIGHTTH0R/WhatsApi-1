<?php

namespace WhatsApi;

/**
 * Interface MessageStoreInterface
 */
interface MessageStoreInterface
{
	/**
	 * @param $from
	 * @param $to
	 * @param $txt
	 * @param $id
	 * @param $t
	 */
	public function saveMessage($from, $to, $txt, $id, $t);
}