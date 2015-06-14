<?php

namespace WhatsApi\events;

/**
 * Class MyEvents
 */
class MyEvents extends AllEvents
{
	/**
	 * This is a list of all current events. Uncomment the ones you wish to listen to.
	 * Every event that is uncommented - should then have a function below.
	 * @var array
	 */
	public $activeEvents = [
//        'onClose',
//        'onCodeRegister',
//        'onCodeRegisterFailed',
//        'onCodeRequest',
//        'onCodeRequestFailed',
//        'onCodeRequestFailedTooRecent',
		'onConnect',
//        'onConnectError',
//        'onCredentialsBad',
//        'onCredentialsGood',
		'onDisconnect',
//        'onDissectPhone',
//        'onDissectPhoneFailed',
//        'onGetAudio',
//        'onGetBroadcastLists',
//        'onGetError',
//        'onGetExtendAccount',
//        'onGetGroupMessage',
//        'onGetGroupParticipants',
//        'onGetGroups',
//        'onGetGroupsInfo',
//        'onGetGroupsSubject',
//        'onGetImage',
//        'onGetLocation',
//        'onGetMessage',
//        'onGetNormalizedJid',
//        'onGetPrivacyBlockedList',
//        'onGetProfilePicture',
//        'onGetReceipt',
//        'onGetRequestLastSeen',
//        'onGetServerProperties',
//        'onGetServicePricing',
//        'onGetStatus',
//        'onGetSyncResult',
//        'onGetVideo',
//        'onGetvCard',
//        'onGroupCreate',
//        'onGroupisCreated',
//        'onGroupsChatCreate',
//        'onGroupsChatEnd',
//        'onGroupsParticipantsAdd',
//        'onGroupsParticipantsPromote',
//        'onGroupsParticipantsRemove',
//        'onLogin',
//        'onLoginFailed',
//        'onAccountExpired',
//        'onMediaMessageSent',
//        'onMediaUploadFailed',
//        'onMessageComposing',
//        'onMessagePaused',
//        'onMessageReceivedClient',
//        'onMessageReceivedServer',
//        'onPaidAccount',
//        'onPing',
//        'onPresenceAvailable',
//        'onPresenceUnavailable',
//        'onProfilePictureChanged',
//        'onProfilePictureDeleted',
//        'onSendMessage',
//        'onSendMessageReceived',
//        'onSendPong',
//        'onSendPresence',
//        'onSendStatusUpdate',
//        'onStreamError',
//        'onUploadFile',
//        'onUploadFileFailed',
	];

	/**
	 * @param $mynumber
	 * @param $socket
	 */
	public function onConnect($mynumber, $socket)
	{
		echo "<p>WooHoo!, Phone number $mynumber connected successfully!</p>";
	}

	/**
	 * @param $mynumber
	 * @param $socket
	 */
	public function onDisconnect($mynumber, $socket)
	{
		echo "<p>Booo!, Phone number $mynumber is disconnected!</p>";
	}
}
