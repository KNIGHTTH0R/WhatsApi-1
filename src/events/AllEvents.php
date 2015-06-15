<?php

namespace WhatsApi\events;

use WhatsApi\Client;

/**
 * Class AllEvents
 */
abstract class AllEvents
{
	/**
	 * @var array
	 */
	protected $eventsToListenFor = [];
	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @param Client $client
	 */
	public function __construct(Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Register the events you want to listen for.
	 *
	 * @param array $eventList
	 * @return AllEvents
	 */
	public function setEventsToListenFor(array $eventList)
	{
		$this->eventsToListenFor = $eventList;
		return $this->startListening();
	}

	/**
	 * Binds the requested events to the event manager.
	 * @return $this
	 */
	protected function startListening()
	{
		foreach ($this->eventsToListenFor as $event) {
			if (is_callable([$this, $event])) {
				$this->client->eventsManager()->bind($event, [$this, $event]);
			}
		}

		return $this;
	}

	/**
	 * Adding to this list? Please put them in alphabetical order!
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $notify
	 * @param $time
	 * @param $callId
	 */
	public function onCallReceived($mynumber, $from, $id, $notify, $time, $callId)
	{
	}

	/**
	 * @param $mynumber
	 * @param $error
	 */
	public function onClose($mynumber, $error)
	{
	}

	/**
	 * @param $mynumber
	 * @param $login
	 * @param $password
	 * @param $type
	 * @param $expiration
	 * @param $kind
	 * @param $price
	 * @param $cost
	 * @param $currency
	 * @param $price_expiration
	 */
	public function onCodeRegister(
		$mynumber,
		$login,
		$password,
		$type,
		$expiration,
		$kind,
		$price,
		$cost,
		$currency,
		$price_expiration
	) {
	}

	/**
	 * @param $mynumber
	 * @param $status
	 * @param $reason
	 * @param $retry_after
	 */
	public function onCodeRegisterFailed($mynumber, $status, $reason, $retry_after)
	{
	}

	/**
	 * @param $mynumber
	 * @param $method
	 * @param $length
	 */
	public function onCodeRequest($mynumber, $method, $length)
	{
	}

	/**
	 * @param $mynumber
	 * @param $method
	 * @param $reason
	 * @param $param
	 */
	public function onCodeRequestFailed($mynumber, $method, $reason, $param)
	{
	}

	/**
	 * @param $mynumber
	 * @param $method
	 * @param $reason
	 * @param $retry_after
	 */
	public function onCodeRequestFailedTooRecent($mynumber, $method, $reason, $retry_after)
	{
	}

	/**
	 * @param $mynumber
	 * @param $method
	 * @param $reason
	 * @param $retry_after
	 */
	public function onCodeRequestFailedTooManyGuesses($mynumber, $method, $reason, $retry_after)
	{
	}

	/**
	 * @param $mynumber
	 * @param $socket
	 */
	public function onConnect($mynumber, $socket)
	{
	}

	/**
	 * @param $mynumber
	 * @param $socket
	 */
	public function onConnectError($mynumber, $socket)
	{
	}

	/**
	 * @param $mynumber
	 * @param $status
	 * @param $reason
	 */
	public function onCredentialsBad($mynumber, $status, $reason)
	{
	}

	/**
	 * @param $mynumber
	 * @param $login
	 * @param $password
	 * @param $type
	 * @param $expiration
	 * @param $kind
	 * @param $price
	 * @param $cost
	 * @param $currency
	 * @param $price_expiration
	 */
	public function onCredentialsGood(
		$mynumber,
		$login,
		$password,
		$type,
		$expiration,
		$kind,
		$price,
		$cost,
		$currency,
		$price_expiration
	) {
	}

	/**
	 * @param $mynumber
	 * @param $socket
	 */
	public function onDisconnect($mynumber, $socket)
	{
	}

	/**
	 * @param $mynumber
	 * @param $phonecountry
	 * @param $phonecc
	 * @param $phone
	 * @param $phonemcc
	 * @param $phoneISO3166
	 * @param $phoneISO639
	 * @param $phonemnc
	 */
	public function onDissectPhone(
		$mynumber,
		$phonecountry,
		$phonecc,
		$phone,
		$phonemcc,
		$phoneISO3166,
		$phoneISO639,
		$phonemnc
	) {
	}

	/**
	 * @param $mynumber
	 */
	public function onDissectPhoneFailed($mynumber)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $size
	 * @param $url
	 * @param $file
	 * @param $mimeType
	 * @param $fileHash
	 * @param $duration
	 * @param $acodec
	 * @param null $fromJID_ifGroup
	 */
	public function onGetAudio(
		$mynumber,
		$from,
		$id,
		$type,
		$time,
		$name,
		$size,
		$url,
		$file,
		$mimeType,
		$fileHash,
		$duration,
		$acodec,
		$fromJID_ifGroup = null
	) {
	}

	/**
	 * @param $mynumber
	 * @param $broadcastLists
	 */
	public function onGetBroadcastLists($mynumber, $broadcastLists)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $data
	 * @param null $errorType
	 */
	public function onGetError($mynumber, $from, $id, $data, $errorType = null)
	{
	}

	/**
	 * @param $mynumber
	 * @param $kind
	 * @param $status
	 * @param $creation
	 * @param $expiration
	 */
	public function onGetExtendAccount($mynumber, $kind, $status, $creation, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $encrypt
	 */
	public function onGetFeature($mynumber, $from, $encrypt)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from_group_jid
	 * @param $from_user_jid
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $body
	 */
	public function onGetGroupMessage($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time, $name, $body)
	{
	}

	/**
	 * @param $mynumber
	 * @param $groupList
	 */
	public function onGetGroups($mynumber, $groupList)
	{
	}

	/**
	 * @param $mynumber
	 * @param $group_id
	 * @param $creator
	 * @param $creation
	 * @param $subject
	 * @param $participants
	 * @param $admins
	 * @param $fromGetGroup
	 */
	public function onGetGroupV2Info(
		$mynumber,
		$group_id,
		$creator,
		$creation,
		$subject,
		$participants,
		$admins,
		$fromGetGroup
	) {
	}

	/**
	 * @param $mynumber
	 * @param $group_jid
	 * @param $time
	 * @param $author
	 * @param $name
	 * @param $subject
	 */
	public function onGetGroupsSubject($mynumber, $group_jid, $time, $author, $name, $subject)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $size
	 * @param $url
	 * @param $file
	 * @param $mimeType
	 * @param $fileHash
	 * @param $width
	 * @param $height
	 * @param $preview
	 * @param $caption
	 */
	public function onGetImage(
		$mynumber,
		$from,
		$id,
		$type,
		$time,
		$name,
		$size,
		$url,
		$file,
		$mimeType,
		$fileHash,
		$width,
		$height,
		$preview,
		$caption
	) {
	}

	/**
	 * @param $mynumber
	 * @param $from_group_jid
	 * @param $from_user_jid
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $size
	 * @param $url
	 * @param $file
	 * @param $mimeType
	 * @param $fileHash
	 * @param $width
	 * @param $height
	 * @param $preview
	 * @param $caption
	 */
	public function onGetGroupImage(
		$mynumber,
		$from_group_jid,
		$from_user_jid,
		$id,
		$type,
		$time,
		$name,
		$size,
		$url,
		$file,
		$mimeType,
		$fileHash,
		$width,
		$height,
		$preview,
		$caption
	) {
	}

	/**
	 * @param $mynumber
	 * @param $from_group_jid
	 * @param $from_user_jid
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $url
	 * @param $file
	 * @param $size
	 * @param $mimeType
	 * @param $fileHash
	 * @param $duration
	 * @param $vcodec
	 * @param $acodec
	 * @param $preview
	 * @param $caption
	 */
	public function onGetGroupVideo(
		$mynumber,
		$from_group_jid,
		$from_user_jid,
		$id,
		$type,
		$time,
		$name,
		$url,
		$file,
		$size,
		$mimeType,
		$fileHash,
		$duration,
		$vcodec,
		$acodec,
		$preview,
		$caption
	) {
	}

	/**
	 * @param $mynumber
	 * @param $keysLeft
	 */
	public function onGetKeysLeft($mynumber, $keysLeft)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $author
	 * @param $longitude
	 * @param $latitude
	 * @param $url
	 * @param $preview
	 * @param null $fromJID_ifGroup
	 */
	public function onGetLocation(
		$mynumber,
		$from,
		$id,
		$type,
		$time,
		$name,
		$author,
		$longitude,
		$latitude,
		$url,
		$preview,
		$fromJID_ifGroup = null
	) {
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $body
	 */
	public function onGetMessage($mynumber, $from, $id, $type, $time, $name, $body)
	{
	}

	/**
	 * @param $mynumber
	 * @param $data
	 */
	public function onGetNormalizedJid($mynumber, $data)
	{
	}

	/**
	 * @param $mynumber
	 * @param $data
	 */
	public function onGetPrivacyBlockedList($mynumber, $data)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $type
	 * @param $data
	 */
	public function onGetProfilePicture($mynumber, $from, $type, $data)
	{
	}

	/**
	 * @param $from
	 * @param $id
	 * @param $offline
	 * @param $retry
	 */
	public function onGetReceipt($from, $id, $offline, $retry)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $seconds
	 */
	public function onGetRequestLastSeen($mynumber, $from, $id, $seconds)
	{
	}

	/**
	 * @param $mynumber
	 * @param $version
	 * @param $props
	 */
	public function onGetServerProperties($mynumber, $version, $props)
	{
	}

	/**
	 * @param $mynumber
	 * @param $price
	 * @param $cost
	 * @param $currency
	 * @param $expiration
	 */
	public function onGetServicePricing($mynumber, $price, $cost, $currency, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $requested
	 * @param $id
	 * @param $time
	 * @param $data
	 */
	public function onGetStatus($mynumber, $from, $requested, $id, $time, $data)
	{
	}

	/**
	 * @param $result
	 */
	public function onGetSyncResult($result)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $url
	 * @param $file
	 * @param $size
	 * @param $mimeType
	 * @param $fileHash
	 * @param $duration
	 * @param $vcodec
	 * @param $acodec
	 * @param $preview
	 * @param $caption
	 */
	public function onGetVideo($mynumber, $from, $id, $type, $time, $name, $url, $file, $size, $mimeType, $fileHash, $duration, $vcodec, $acodec, $preview, $caption)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $name
	 * @param $vcardname
	 * @param $vcard
	 * @param null $fromJID_ifGroup
	 */
	public function onGetvCard($mynumber, $from, $id, $type, $time, $name, $vcardname, $vcard, $fromJID_ifGroup = null)
	{
	}

	/**
	 * @param $mynumber
	 * @param $groupId
	 */
	public function onGroupCreate($mynumber, $groupId)
	{
	}

	/**
	 * @param $mynumber
	 * @param $creator
	 * @param $gid
	 * @param $subject
	 * @param $admin
	 * @param $creation
	 * @param array $members
	 */
	public function onGroupisCreated($mynumber, $creator, $gid, $subject, $admin, $creation, $members = [])
	{
	}

	/**
	 * @param $mynumber
	 * @param $gid
	 */
	public function onGroupsChatCreate($mynumber, $gid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $gid
	 */
	public function onGroupsChatEnd($mynumber, $gid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $groupId
	 * @param $jid
	 */
	public function onGroupsParticipantsAdd($mynumber, $groupId, $jid)
	{
	}

	/**
	 * @param $myNumber
	 * @param $groupJID
	 * @param $time
	 * @param $issuerJID
	 * @param $issuerName
	 * @param array $promotedJIDs
	 */
	public function onGroupsParticipantsPromote(
		$myNumber,
		$groupJID,
		$time,
		$issuerJID,
		$issuerName,
		$promotedJIDs = []
	) {
	}

	/**
	 * @param $mynumber
	 * @param $groupId
	 * @param $jid
	 */
	public function onGroupsParticipantsRemove($mynumber, $groupId, $jid)
	{
	}

	/**
	 * @param $mynumber
	 */
	public function onLogin($mynumber)
	{
	}

	/**
	 * @param $mynumber
	 * @param $data
	 */
	public function onLoginFailed($mynumber, $data)
	{
	}

	/**
	 * @param $mynumber
	 * @param $kind
	 * @param $status
	 * @param $creation
	 * @param $expiration
	 */
	public function onLoginSuccess($mynumber, $kind, $status, $creation, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $kind
	 * @param $status
	 * @param $creation
	 * @param $expiration
	 */
	public function onAccountExpired($mynumber, $kind, $status, $creation, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $to
	 * @param $id
	 * @param $filetype
	 * @param $url
	 * @param $filename
	 * @param $filesize
	 * @param $filehash
	 * @param $caption
	 * @param $icon
	 */
	public function onMediaMessageSent(
		$mynumber,
		$to,
		$id,
		$filetype,
		$url,
		$filename,
		$filesize,
		$filehash,
		$caption,
		$icon
	) {
	}

	/**
	 * @param $mynumber
	 * @param $id
	 * @param $node
	 * @param $messageNode
	 * @param $statusMessage
	 */
	public function onMediaUploadFailed($mynumber, $id, $node, $messageNode, $statusMessage)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 */
	public function onMessageComposing($mynumber, $from, $id, $type, $time)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 */
	public function onMessagePaused($mynumber, $from, $id, $type, $time)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 * @param $participant
	 */
	public function onMessageReceivedClient($mynumber, $from, $id, $type, $time, $participant)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $type
	 * @param $time
	 */
	public function onMessageReceivedServer($mynumber, $from, $id, $type, $time)
	{
	}

	/**
	 * @param $mynumber
	 * @param $jid
	 */
	public function onNumberWasAdded($mynumber, $jid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $jid
	 */
	public function onNumberWasRemoved($mynumber, $jid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $jid
	 */
	public function onNumberWasUpdated($mynumber, $jid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $author
	 * @param $kind
	 * @param $status
	 * @param $creation
	 * @param $expiration
	 */
	public function onPaidAccount($mynumber, $author, $kind, $status, $creation, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $kind
	 * @param $status
	 * @param $creation
	 * @param $expiration
	 */
	public function onPaymentRecieved($mynumber, $kind, $status, $creation, $expiration)
	{
	}

	/**
	 * @param $mynumber
	 * @param $id
	 */
	public function onPing($mynumber, $id)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 */
	public function onPresenceAvailable($mynumber, $from)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $last
	 */
	public function onPresenceUnavailable($mynumber, $from, $last)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $time
	 */
	public function onProfilePictureChanged($mynumber, $from, $id, $time)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $time
	 */
	public function onProfilePictureDeleted($mynumber, $from, $id, $time)
	{
	}

	/**
	 * @param $mynumber
	 * @param $target
	 * @param $messageId
	 * @param $node
	 */
	public function onSendMessage($mynumber, $target, $messageId, $node)
	{
	}

	/**
	 * @param $mynumber
	 * @param $id
	 * @param $from
	 * @param $type
	 */
	public function onSendMessageReceived($mynumber, $id, $from, $type)
	{
	}

	/**
	 * @param $mynumber
	 * @param $msgid
	 */
	public function onSendPong($mynumber, $msgid)
	{
	}

	/**
	 * @param $mynumber
	 * @param $type
	 * @param $name
	 */
	public function onSendPresence($mynumber, $type, $name)
	{
	}

	/**
	 * @param $mynumber
	 * @param $txt
	 */
	public function onSendStatusUpdate($mynumber, $txt)
	{
	}

	/**
	 * @param $data
	 */
	public function onStreamError($data)
	{
	}

	/**
	 * @param $mynumber
	 * @param $from
	 * @param $id
	 * @param $syncData
	 * @param $code
	 * @param $name
	 */
	public function onWebSync($mynumber, $from, $id, $syncData, $code, $name)
	{
	}
}
