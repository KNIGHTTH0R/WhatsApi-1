<?php

namespace WhatsApi;

/**
 * Class SqliteMessageStore
 */
class SqliteMessageStore implements MessageStoreInterface
{
	/**
	 * @var \PDO
	 */
	private $db;

	/**
	 * @param $number
	 */
	public function __construct($number)
	{
		$fileName = __DIR__ . DIRECTORY_SEPARATOR . Constants::DATA_FOLDER . DIRECTORY_SEPARATOR . 'msgstore-' . $number . '.db';

		$this->db = new \PDO('sqlite:' . $fileName, null, null,
			[\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		if (!file_exists($fileName)) {
			$this->db->exec('CREATE TABLE messages (`from` TEXT, `to` TEXT, message TEXT, id TEXT, t TEXT)');
		}
	}

	/**
	 * @param $from
	 * @param $to
	 * @param $txt
	 * @param $id
	 * @param $t
	 */
	public function saveMessage($from, $to, $txt, $id, $t)
	{
		$sql = 'INSERT INTO messages (`from`, `to`, message, id, t) VALUES (:from, :to, :message, :messageId, :t)';
		$query = $this->db->prepare($sql);

		$query->execute([
				':from' => $from,
				':to' => $to,
				':message' => $txt,
				':messageId' => $id,
				':t' => $t
			]
		);
	}
}
