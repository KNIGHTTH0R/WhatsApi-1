<?php

namespace WhatsApi;

use WhatsApi\events\EventsManager;
use WhatsApi\exceptions\ConnectionException;
use WhatsApi\exceptions\LoginFailureException;

/**
 * Class Client
 */
class Client
{
    /**
     * @var BinTreeNodeReader
     */
    public $reader;
    /**
     * Property declarations.
     */
    protected $accountInfo;
    /**
     * @var string
     */
    protected $challengeFilename;
    /**
     * @var
     */
    protected $challengeData;
    /**
     * @var bool
     */
    protected $debug = false;
    /**
     * @var
     */
    protected $event;
    /**
     * @var array
     */
    protected $groupList = [];
    /**
     * @var string
     */
    protected $identity;
    /**
     * @var
     */
    protected $inputKey;
    /**
     * @var
     */
    protected $outputKey;
    /**
     * @var bool
     */
    protected $groupId = false;
    /**
     * @var bool
     */
    protected $lastId = false;
    /**
     * @var string
     */
    protected $loginStatus;
    /**
     * @var array
     */
    protected $mediaFileInfo = [];
    /**
     * @var array
     */
    protected $mediaQueue = [];
    /**
     * @var int
     */
    protected $messageCounter = 1;
    /**
     * @var int
     */
    protected $iqCounter = 1;
    /**
     * @var array
     */
    protected $messageQueue = [];
    /**
     * @var string
     */
    protected $name;
    /**
     * @var bool
     */
    protected $newMsgBind = false;
    /**
     * @var array
     */
    protected $outQueue = [];
    /**
     * @var
     */
    protected $password;
    /**
     * @var string
     */
    protected $phoneNumber;
    /**
     * @var
     */
    protected $serverReceivedId;
    /**
     * @var
     */
    protected $socket;
    /**
     * @var BinTreeNodeWriter
     */
    protected $writer;
    /**
     * @var
     */
    protected $messageStore;
    /**
     * @var array
     */
    protected $nodeId = [];
    /**
     * @var
     */
    protected $loginTime;
    /**
     * @var events\EventsManager
     */
    private $eventsManager;

    /**
     * Default class constructor.
     *
     * @param string $number The user phone number including the country code without '+' or '00'.
     * @param string $nickname The user name. Debug on or off, false by default.
     * @param mixed $identityFile Path to identity file, overrides default path
     */
    public function __construct($number, $nickname, $identityFile = false)
    {
        $this->writer = new BinTreeNodeWriter();
        $this->reader = new BinTreeNodeReader();
        $this->phoneNumber = $number;

        //e.g. ./cache/nextChallenge.12125557788.dat
        $this->challengeFilename = sprintf('%snextChallenge.%s.dat', DATA_FOLDER . DS, $number);
        $this->identity = $this->buildIdentity($identityFile);

        $this->name = $nickname;
        $this->loginStatus = DISCONNECTED_STATUS;
    }

    /**
     * Create an identity string
     *
     * @param  mixed $identity_file IdentityFile (optional).
     * @return string           Correctly formatted identity
     *
     * @throws \RuntimeException Error when cannot write identity data to file.
     */
    protected function buildIdentity($identity_file = false)
    {
        if ($identity_file === false) {
            $identity_file = sprintf('%sid.%s.dat', DATA_FOLDER . DS, $this->phoneNumber);
        }

        if (is_readable($identity_file)) {
            $data = urldecode(file_get_contents($identity_file));
            $length = strlen($data);

            if ($length == 20 || $length == 16) {
                return $data;
            }
        }

        $bytes = strtolower(openssl_random_pseudo_bytes(20));

        if (file_put_contents($identity_file, urlencode($bytes)) === false) {
            throw new \RuntimeException('Unable to write identity file to ' . $identity_file);
        }

        return $bytes;
    }

    /**
     * @param string $jid
     *
     * @return string
     */
    public static function parseJID($jid)
    {
        $parts = explode('@', $jid);
        $parts = reset($parts);

        return $parts;
    }

    /**
     * Enable debug mode
     */
    public function enableDebugMode()
    {
        $this->debug = true;
    }

    /**
     * If you need use different challenge fileName you can use this
     *
     * @param string $filename
     */
    public function setChallengeName($filename)
    {
        $this->challengeFilename = $filename;
    }

    /**
     * Add message to the outgoing queue.
     *
     * @param $node
     */
    public function addMsgOutQueue($node)
    {
        $this->outQueue[] = $node;
    }

    /**
     * Register account on WhatsApp using the provided code.
     *
     * @param integer $code
     *   Numeric code value provided on requestCode().
     *
     * @return object
     *   An object with server response.
     *   - status: Account status.
     *   - login: Phone number with country code.
     *   - pw: Account password.
     *   - type: Type of account.
     *   - expiration: Expiration date in UNIX TimeStamp.
     *   - kind: Kind of account.
     *   - price: Formatted price of account.
     *   - cost: Decimal amount of account.
     *   - currency: Currency price of account.
     *   - price_expiration: Price expiration in UNIX TimeStamp.
     *
     * @throws \InvalidArgumentException
     */
    public function codeRegister($code)
    {
        if (!$phone = $this->dissectPhone()) {
            throw new \InvalidArgumentException('The provided phone number is not valid.');
        }

        // Build the url.
        $host = 'https://' . WHATSAPP_REGISTER_HOST;
        $query = [
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'code' => $code
        ];

        $response = $this->getResponse($host, $query);

        if ($response->status != 'ok') {
            $this->eventsManager()->fire('onCodeRegisterFailed', [
                $this->phoneNumber,
                $response->status,
                $response->reason,
                isset($response->retry_after) ? $response->retry_after : null
            ]);

            $this->debugPrint($query);
            $this->debugPrint($response);

            if ($response->reason == 'old_version') {
                $this->update();
            }

            throw new \InvalidArgumentException('An error occurred registering the registration code from WhatsApp. Reason: ' . $response->reason);
        } else {
            $this->eventsManager()->fire('onCodeRegister', [
                $this->phoneNumber,
                $response->login,
                $response->pw,
                $response->type,
                $response->expiration,
                $response->kind,
                $response->price,
                $response->cost,
                $response->currency,
                $response->price_expiration
            ]);
        }

        return $response;
    }

    /**
     * Dissect country code from phone number.
     *
     * @return array
     *   An associative array with country code and phone number.
     *   - country: The detected country name.
     *   - cc: The detected country code (phone prefix).
     *   - phone: The phone number.
     *   - ISO3166: 2-Letter country code
     *   - ISO639: 2-Letter language code
     *   Return false if country code is not found.
     */
    protected function dissectPhone()
    {
        if (($handle = fopen(DATA_FOLDER . '/countries.csv', 'rb')) !== false) {
            while (($data = fgetcsv($handle, 1000)) !== false) {
                if (strpos($this->phoneNumber, $data[1]) === 0) {
                    // Return the first appearance.
                    fclose($handle);

                    $mcc = explode('|', $data[2]);
                    $mcc = $mcc[0];

                    //hook:
                    //fix country code for North America
                    if ($data[1][0] == 1) {
                        $data[1] = 1;
                    }

                    $phone = [
                        'country' => $data[0],
                        'cc' => $data[1],
                        'phone' => substr($this->phoneNumber, strlen($data[1]), strlen($this->phoneNumber)),
                        'mcc' => $mcc,
                        'ISO3166' => @$data[3],
                        'ISO639' => @$data[4],
                        'mnc' => $data[5]
                    ];

                    $this->eventsManager()->fire('onDissectPhone', [
                            $this->phoneNumber,
                            $phone['country'],
                            $phone['cc'],
                            $phone['phone'],
                            $phone['mcc'],
                            $phone['ISO3166'],
                            $phone['ISO639'],
                            $phone['mnc']
                        ]
                    );

                    return $phone;
                }
            }
            fclose($handle);
        }

        $this->eventsManager()->fire('onDissectPhoneFailed', [
            $this->phoneNumber
        ]);

        return false;
    }

    /**
     * Return events manager
     * @return events\EventsManager
     */
    public function eventsManager()
    {
        if ($this->eventsManager === null) {
            $this->eventsManager = new EventsManager();
        }

        return $this->eventsManager;
    }

    /**
     * Get a decoded JSON response from Whatsapp server
     *
     * @param  string $host The host URL
     * @param  array $query A associative array of keys and values to send to server.
     *
     * @return null|object   NULL if the json cannot be decoded or if the encoded data is deeper than the recursion limit
     */
    protected function getResponse($host, $query)
    {
        // Build the url.
        $url = $host . '?' . http_build_query($query);

        // Open connection.
        $ch = curl_init();

        // Configure the connection.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, WHATSAPP_USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/json']);
        // This makes CURL accept any peer!
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Get the response.
        $response = curl_exec($ch);

        // Close the connection.
        curl_close($ch);

        return json_decode($response);
    }

    /**
     * Print a message to the debug console.
     *
     * @param  mixed $debugMsg The debug message.
     * @return bool
     */
    protected function debugPrint($debugMsg)
    {
        if ($this->debug) {
            if (is_array($debugMsg) || is_object($debugMsg)) {
                print_r($debugMsg);
            } else {
                echo $debugMsg;
            }

            return true;
        }

        return false;
    }

    /**
     *
     */
    public function update()
    {
        $WAData = json_decode(file_get_contents(WHATSAPP_VER_CHECKER), true);

        if (WHATSAPP_VER != $WAData['e']) {
            updateData('bootstrap.php', null, $WAData['h']);
            updateData('bootstrap.php', $WAData['e']);
        }
    }

    /**
     * Request a registration code from WhatsApp.
     *
     * @param string $method Accepts only 'sms' or 'voice' as a value.
     * @param string $carrier
     *
     * @return object
     *   An object with server response.
     *   - status: Status of the request (sent/fail).
     *   - length: Registration code length.
     *   - method: Used method.
     *   - reason: Reason of the status (e.g. too_recent/missing_param/bad_param).
     *   - param: The missing_param/bad_param.
     *   - retry_after: Waiting time before requesting a new code.
     *
     * @throws \InvalidArgumentException
     */
    public function codeRequest($method = 'sms', $carrier = 'T-Mobile5')
    {
        if (!$phone = $this->dissectPhone()) {
            throw new \InvalidArgumentException('The provided phone number is not valid.');
        }

        $countryCode = (!empty($phone['ISO3166'])) ? $phone['ISO3166'] : 'US';
        $langCode = (!empty($phone['ISO639'])) ? $phone['ISO639'] : 'en';

        if ($carrier != null) {
            $mnc = $this->detectMnc(strtolower($countryCode), $carrier);
        } else {
            $mnc = $phone['mnc'];
        }

        // Build the token.
        $token = generateRequestToken($phone['country'], $phone['phone']);

        // Build the url.
        $host = 'https://' . WHATSAPP_REQUEST_HOST;
        $query = [
            'in' => $phone['phone'],
            'cc' => $phone['cc'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
            'sim_mcc' => $phone['mcc'],
            'sim_mnc' => $mnc,
            'method' => $method,
            'token' => $token
        ];

        $this->debugPrint($query);
        $response = $this->getResponse($host, $query);
        $this->debugPrint($response);

        if ($response->status == 'ok') {
            $this->eventsManager()->fire('onCodeRegister', [
                $this->phoneNumber,
                $response->login,
                $response->pw,
                $response->type,
                $response->expiration,
                $response->kind,
                $response->price,
                $response->cost,
                $response->currency,
                $response->price_expiration
            ]);
        } else {
            if ($response->status != 'sent') {
                if (isset($response->reason)) {
                    if ($response->reason == 'too_recent') {
                        $this->eventsManager()->fire('onCodeRequestFailedTooRecent', [
                            $this->phoneNumber,
                            $method,
                            $response->reason,
                            $response->retry_after
                        ]);
                        $minutes = round($response->retry_after / 60);
                        throw new \InvalidArgumentException("Code already sent. Retry after $minutes minutes.");
                    } else {
                        if ($response->reason == 'too_many_guesses') {
                            $this->eventsManager()->fire('onCodeRequestFailedTooManyGuesses', [
                                $this->phoneNumber,
                                $method,
                                $response->reason,
                                $response->retry_after
                            ]);
                            $minutes = round($response->retry_after / 60);
                            throw new \InvalidArgumentException("Too many guesses. Retry after $minutes minutes.");
                        } else {
                            $this->eventsManager()->fire('onCodeRequestFailed', [
                                $this->phoneNumber,
                                $method,
                                $response->reason,
                                isset($response->param) ? $response->param : null
                            ]);
                            throw new \InvalidArgumentException('There was a problem trying to request the code.');
                        }
                    }
                }
            } else {
                $this->eventsManager()->fire('onCodeRequest', [
                    $this->phoneNumber,
                    $method,
                    $response->length
                ]);
            }
        }

        return $response;
    }

    /**
     * Detects mnc from specified carrier.
     *
     * @param string $lc LangCode
     * @param string $carrierName Name of the carrier
     * @return string
     *
     * Returns mnc value
     */
    protected function detectMnc($lc, $carrierName)
    {
        $fp = fopen(DATA_FOLDER . '/networkinfo.csv', 'r');
        $mnc = null;

        while ($data = fgetcsv($fp, 0, ',')) {
            if ($data[4] === $lc && $data[7] === $carrierName) {
                $mnc = $data[2];
                break;
            }
        }

        if ($mnc == null) {
            $mnc = '000';
        }

        fclose($fp);

        return $mnc;
    }

    /**
     * Connect (create a socket) to the WhatsApp network.
     *
     * @return bool
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return true;
        }

        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket !== false) {
            $result = socket_connect($socket, 'e' . rand(1, 16) . '.whatsapp.net', PORT);
            if ($result === false) {
                $socket = false;
            }
        }

        if ($socket !== false) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => TIMEOUT_SEC, 'usec' => TIMEOUT_USEC]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => TIMEOUT_SEC, 'usec' => TIMEOUT_USEC]);

            $this->socket = $socket;
            $this->eventsManager()->fire('onConnect', [
                    $this->phoneNumber,
                    $this->socket
                ]
            );

            return true;
        } else {
            $this->eventsManager()->fire('onConnectError', [
                    $this->phoneNumber,
                    $this->socket
                ]
            );

            return false;
        }
    }

    /**
     * Do we have an active socket connection to WhatsApp?
     *
     * @return bool
     */
    public function isConnected()
    {
        return ($this->socket !== null);
    }

    /**
     * Drain the message queue for application processing.
     *
     * @return ProtocolNode[]
     *   Return the message queue list.
     */
    public function getMessages()
    {
        $ret = $this->messageQueue;
        $this->messageQueue = [];

        return $ret;
    }

    /**
     * Log into the WhatsApp server.
     *
     * ###Warning### using this method will generate a new password
     * from the WhatsApp servers each time.
     *
     * If you know your password and wish to use it without generating
     * a new password - use the loginWithPassword() method instead.
     */
    public function login()
    {
        $this->accountInfo = (array)$this->checkCredentials();
        if ($this->accountInfo['status'] == 'ok') {
            $this->debugPrint('New password received: ' . $this->accountInfo['pw'] . "\n");
            $this->password = $this->accountInfo['pw'];
        }
        $this->doLogin();
    }

    /**
     * Check if account credentials are valid.
     *
     * WARNING: WhatsApp now changes your password everytime you use this.
     * Make sure you update your config file if the output informs about
     * a password change.
     *
     * @return object
     *   An object with server response.
     *   - status: Account status.
     *   - login: Phone number with country code.
     *   - pw: Account password.
     *   - type: Type of account.
     *   - expiration: Expiration date in UNIX TimeStamp.
     *   - kind: Kind of account.
     *   - price: Formatted price of account.
     *   - cost: Decimal amount of account.
     *   - currency: Currency price of account.
     *   - price_expiration: Price expiration in UNIX TimeStamp.
     *
     * @throws \InvalidArgumentException
     */
    public function checkCredentials()
    {
        if (!$phone = $this->dissectPhone()) {
            throw new \InvalidArgumentException('The provided phone number is not valid.');
        }

        $countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        $langCode = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        if ($phone['cc'] == '77' || $phone['cc'] == '79') {
            $phone['cc'] = '7';
        }

        // Build the url.
        $host = 'https://' . WHATSAPP_CHECK_HOST;
        $query = [
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
            //  'network_radio_type' => "1"
        ];

        $response = $this->getResponse($host, $query);

        if ($response->status != 'ok') {
            $this->eventsManager()->fire('onCredentialsBad', [
                $this->phoneNumber,
                $response->status,
                $response->reason
            ]);

            $this->debugPrint($query);
            $this->debugPrint($response);

            throw new \InvalidArgumentException('There was a problem trying to request the code.');
        } else {
            $this->eventsManager()->fire('onCredentialsGood', [
                $this->phoneNumber,
                $response->login,
                $response->pw,
                $response->type,
                $response->expiration,
                $response->kind,
                $response->price,
                $response->cost,
                $response->currency,
                $response->price_expiration
            ]);
        }

        return $response;
    }

    /**
     * Send the nodes to the WhatsApp server to log in.
     * @return bool
     * @throws ConnectionException
     * @throws LoginFailureException
     */
    protected function doLogin()
    {
        if ($this->isLoggedIn()) {
            return true;
        }

        $this->writer->resetKey();
        $this->reader->resetKey();
        $resource = WHATSAPP_DEVICE . '-' . WHATSAPP_VER . '-' . PORT;
        $data = $this->writer->StartStream(WHATSAPP_SERVER, $resource);
        $feat = $this->createFeaturesNode();
        $auth = $this->createAuthNode();
        $this->sendData($data);
        $this->sendNode($feat);
        $this->sendNode($auth);

        $this->pollMessage();
        $this->pollMessage();
        $this->pollMessage();

        if ($this->challengeData != null) {
            $data = $this->createAuthResponseNode();
            $this->sendNode($data);
            $this->reader->setKey($this->inputKey);
            $this->writer->setKey($this->outputKey);
            while (!$this->pollMessage()) {
            };
        }

        if ($this->loginStatus === DISCONNECTED_STATUS) {
            throw new LoginFailureException();
        }

        $this->eventsManager()->fire('onLogin', [
            $this->phoneNumber
        ]);
        $this->sendAvailableForChat();
        $this->loginTime = time();

        return true;
    }

    /**
     * Have we an active connection with WhatsAPP AND a valid login already?
     *
     * @return bool
     */
    protected function isLoggedIn()
    {
        //If you aren't connected you can't be logged in! ($this->isConnected())
        //We are connected - but are we logged in? (the rest)
        return ($this->isConnected() && !empty($this->loginStatus) && $this->loginStatus === CONNECTED_STATUS);
    }

    /**
     * Add stream features.
     *
     * @return ProtocolNode Return itself.
     */
    protected function createFeaturesNode()
    {
        $readreceipts = new ProtocolNode('readreceipts');
        $groupsv2 = new ProtocolNode('groups_v2');
        $privacy = new ProtocolNode('privacy');
        $presencev2 = new ProtocolNode('presence');
        $parent = new ProtocolNode('stream:features', null, [$readreceipts, $groupsv2, $privacy, $presencev2]);

        return $parent;
    }

    /**
     * Add the authentication nodes.
     *
     * @return ProtocolNode Returns an authentication node.
     */
    protected function createAuthNode()
    {
        $data = $this->createAuthBlob();
        $node = new ProtocolNode('auth', [
            'mechanism' => 'WAUTH-2',
            'user' => $this->phoneNumber
        ], null, $data);

        return $node;
    }

    /**
     * @return null|string
     */
    protected function createAuthBlob()
    {
        if ($this->challengeData) {
            $key = wa_pbkdf2('sha1', base64_decode($this->password), $this->challengeData, 16, 20, true);
            $this->inputKey = new KeyStream($key[2], $key[3]);
            $this->outputKey = new KeyStream($key[0], $key[1]);
            $this->reader->setKey($this->inputKey);
            //$this->writer->setKey($this->outputKey);
            $array = "\0\0\0\0" . $this->phoneNumber . $this->challengeData . time();
            $this->challengeData = null;

            return $this->outputKey->EncodeMessage($array, 0, strlen($array), false);
        }

        return null;
    }

    /**
     * Send data to the WhatsApp server.
     * @param string $data
     *
     * @throws ConnectionException
     */
    protected function sendData($data)
    {
        if ($this->socket != null) {
            if (socket_write($this->socket, $data, strlen($data)) === false) {
                $this->disconnect();
                throw new ConnectionException('Connection Closed!');
            }
        }
    }

    /**
     * Disconnect from the WhatsApp network.
     */
    public function disconnect()
    {
        if (is_resource($this->socket)) {
            @socket_shutdown($this->socket, 2);
            @socket_close($this->socket);
            $this->socket = null;
            $this->loginStatus = DISCONNECTED_STATUS;
            $this->eventsManager()->fire('onDisconnect', [
                    $this->phoneNumber,
                    $this->socket
                ]
            );
        }
    }

    /**
     * Send node to the WhatsApp server.
     * @param ProtocolNode $node
     * @param bool $encrypt
     */
    protected function sendNode($node, $encrypt = true)
    {
        $this->debugPrint($node->nodeString('tx  ') . "\n");
        $this->sendData($this->writer->write($node, $encrypt));
    }

    /**
     * Fetch a single message node
     * @param  bool $autoReceipt
     * @param  string $type
     * @return bool
     *
     * @throws ConnectionException
     */
    public function pollMessage($autoReceipt = true, $type = 'read')
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection Closed!');
        }

        $r = [$this->socket];
        $w = [];
        $e = [];

        if (socket_select($r, $w, $e, TIMEOUT_SEC, TIMEOUT_USEC) && $stanza = $this->readStanza()) {
            // Something to read
            $this->processInboundData($stanza, $autoReceipt, $type);

            return true;
        }

        return false;
    }

    /**
     * Read 1024 bytes from the whatsapp server.
     *
     * @throws ConnectionException
     */
    public function readStanza()
    {
        $buff = '';
        if ($this->socket != null) {
            $header = @socket_read($this->socket, 3);//read stanza header
            if ($header === false) {
                $error = 'socket EOF, closing socket...';
                socket_close($this->socket);
                $this->socket = null;
                $this->eventsManager()->fire('onClose', [
                        $this->phoneNumber,
                        $error
                    ]
                );
            }

            if (strlen($header) == 0) {
                //no data received
                return;
            }
            if (strlen($header) != 3) {
                throw new ConnectionException('Failed to read stanza header');
            }
            $treeLength = (ord($header[0]) & 0x0F) << 16;
            $treeLength |= ord($header[1]) << 8;
            $treeLength |= ord($header[2]) << 0;

            //read full length
            $buff = socket_read($this->socket, $treeLength);
            //$trlen = $treeLength;
            $len = strlen($buff);
            //$prev = 0;
            while (strlen($buff) < $treeLength) {
                $toRead = $treeLength - strlen($buff);
                $buff .= socket_read($this->socket, $toRead);
                if ($len == strlen($buff)) {
                    //no new data read, fuck it
                    break;
                }
                $len = strlen($buff);
            }

            if (strlen($buff) != $treeLength) {
                throw new ConnectionException('Tree length did not match received length (buff = ' . strlen($buff) . " & treeLength = $treeLength)");
            }
            $buff = $header . $buff;
        } else {
            $this->eventsManager()->fire('onDisconnect', [
                $this->phoneNumber,
                $this->socket
            ]);
        }

        return $buff;
    }

    /**
     * Process inbound data.
     *
     * @param $data
     * @param bool $autoReceipt
     * @param string $type
     */
    protected function processInboundData($data, $autoReceipt = true, $type = 'read')
    {
        $node = $this->reader->nextTree($data);
        if ($node != null) {
            $this->processInboundDataNode($node, $autoReceipt, $type);
        }
    }

    /**
     * Will process the data from the server after it's been decrypted and parsed.
     *
     * This also provides a convenient method to use to unit test the event framework.
     * @param ProtocolNode $node
     * @param bool $autoReceipt
     * @param $type
     *
     * @throws \Exception
     */
    protected function processInboundDataNode(ProtocolNode $node, $autoReceipt = true, $type = 'read')
    {
        $this->debugPrint($node->nodeString('rx  ') . "\n");
        $this->serverReceivedId = $node->getAttribute('id');

        if ($node->getTag() == 'challenge') {
            $this->processChallenge($node);
        } elseif ($node->getTag() == 'failure') {
            $this->loginStatus = DISCONNECTED_STATUS;
            $this->eventsManager()->fire('onLoginFailed', [
                $this->phoneNumber,
                $node->getChild(0)->getTag()
            ]);
        } elseif ($node->getTag() == 'success') {
            if ($node->getAttribute('status') == 'active') {
                $this->loginStatus = CONNECTED_STATUS;
                $challengeData = $node->getData();
                file_put_contents($this->challengeFilename, $challengeData);
                $this->writer->setKey($this->outputKey);

                $this->eventsManager()->fire('onLoginSuccess', [
                    $this->phoneNumber,
                    $node->getAttribute('kind'),
                    $node->getAttribute('status'),
                    $node->getAttribute('creation'),
                    $node->getAttribute('expiration')
                ]);
            } elseif ($node->getAttribute('status') == 'expired') {
                $this->eventsManager()->fire('onAccountExpired', [
                    $this->phoneNumber,
                    $node->getAttribute('kind'),
                    $node->getAttribute('status'),
                    $node->getAttribute('creation'),
                    $node->getAttribute('expiration')
                ]);
            }
        } elseif ($node->getTag() == 'ack' && $node->getAttribute('class') == 'message') {
            $this->eventsManager()->fire('onMessageReceivedServer', [
                $this->phoneNumber,
                $node->getAttribute('from'),
                $node->getAttribute('id'),
                $node->getAttribute('class'),
                $node->getAttribute('t')
            ]);
        } elseif ($node->getTag() == 'receipt') {
            if ($node->hasChild('list')) {
                foreach ($node->getChild('list')->getChildren() as $child) {
                    $this->eventsManager()->fire('onMessageReceivedClient', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $child->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('participant')
                    ]);
                }
            }

            $this->eventsManager()->fire('onMessageReceivedClient', [
                $this->phoneNumber,
                $node->getAttribute('from'),
                $node->getAttribute('id'),
                $node->getAttribute('type'),
                $node->getAttribute('t'),
                $node->getAttribute('participant')
            ]);

            $this->sendAck($node, 'receipt');
        }
        if ($node->getTag() == 'message') {
            array_push($this->messageQueue, $node);

            if ($node->hasChild('x') && $this->lastId == $node->getAttribute('id')) {
                $this->sendNextMessage();
            }
            if ($this->newMsgBind && ($node->getChild('body') || $node->getChild('media'))) {
                $this->newMsgBind->process($node);
            }
            if ($node->getAttribute('type') == 'text' && $node->getChild('body') != null) {
                $author = $node->getAttribute('participant');
                if ($autoReceipt) {
                    $this->sendReceipt($node, $type, $author);
                }
                if ($author == '') {
                    //private chat message
                    $this->eventsManager()->fire('onGetMessage', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('notify'),
                        $node->getChild('body')->getData()
                    ]);

                    if ($this->messageStore !== null) {
                        $this->messageStore->saveMessage(ExtractNumber($node->getAttribute('from')), $this->phoneNumber,
                            $node->getChild('body')->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
                    }
                } else {
                    //group chat message
                    $this->eventsManager()->fire('onGetGroupMessage', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $author,
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('notify'),
                        $node->getChild('body')->getData()
                    ]);
                    if ($this->messageStore !== null) {
                        $this->messageStore->saveMessage($author, $node->getAttribute('from'),
                            $node->getChild('body')->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
                    }
                }

            }
            if ($node->getAttribute('type') == 'text' && $node->getChild(0)->getTag() == 'enc') {
                // TODO
                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getAttribute('type') == 'media' && $node->getChild('media') != null) {
                if ($node->getChild('media')->getAttribute('type') == 'image') {

                    if ($node->getAttribute('participant') == null) {
                        $this->eventsManager()->fire('onGetImage', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild('media')->getAttribute('size'),
                            $node->getChild('media')->getAttribute('url'),
                            $node->getChild('media')->getAttribute('file'),
                            $node->getChild('media')->getAttribute('mimetype'),
                            $node->getChild('media')->getAttribute('filehash'),
                            $node->getChild('media')->getAttribute('width'),
                            $node->getChild('media')->getAttribute('height'),
                            $node->getChild('media')->getData(),
                            $node->getChild('media')->getAttribute('caption')
                        ]);
                    } else {
                        $this->eventsManager()->fire('onGetGroupImage', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('participant'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild('media')->getAttribute('size'),
                            $node->getChild('media')->getAttribute('url'),
                            $node->getChild('media')->getAttribute('file'),
                            $node->getChild('media')->getAttribute('mimetype'),
                            $node->getChild('media')->getAttribute('filehash'),
                            $node->getChild('media')->getAttribute('width'),
                            $node->getChild('media')->getAttribute('height'),
                            $node->getChild('media')->getData(),
                            $node->getChild('media')->getAttribute('caption')
                        ]);
                    }
                } elseif ($node->getChild('media')->getAttribute('type') == 'video') {
                    if ($node->getAttribute('participant') == null) {
                        $this->eventsManager()->fire('onGetVideo', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild('media')->getAttribute('url'),
                            $node->getChild('media')->getAttribute('file'),
                            $node->getChild('media')->getAttribute('size'),
                            $node->getChild('media')->getAttribute('mimetype'),
                            $node->getChild('media')->getAttribute('filehash'),
                            $node->getChild('media')->getAttribute('duration'),
                            $node->getChild('media')->getAttribute('vcodec'),
                            $node->getChild('media')->getAttribute('acodec'),
                            $node->getChild('media')->getData(),
                            $node->getChild('media')->getAttribute('caption')
                        ]);
                    } else {
                        $this->eventsManager()->fire('onGetGroupVideo', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('participant'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild('media')->getAttribute('url'),
                            $node->getChild('media')->getAttribute('file'),
                            $node->getChild('media')->getAttribute('size'),
                            $node->getChild('media')->getAttribute('mimetype'),
                            $node->getChild('media')->getAttribute('filehash'),
                            $node->getChild('media')->getAttribute('duration'),
                            $node->getChild('media')->getAttribute('vcodec'),
                            $node->getChild('media')->getAttribute('acodec'),
                            $node->getChild('media')->getData(),
                            $node->getChild('media')->getAttribute('caption')
                        ]);
                    }
                } elseif ($node->getChild('media')->getAttribute('type') == 'audio') {
                    $author = $node->getAttribute('participant');
                    $this->eventsManager()->fire('onGetAudio', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('notify'),
                        $node->getChild('media')->getAttribute('size'),
                        $node->getChild('media')->getAttribute('url'),
                        $node->getChild('media')->getAttribute('file'),
                        $node->getChild('media')->getAttribute('mimetype'),
                        $node->getChild('media')->getAttribute('filehash'),
                        $node->getChild('media')->getAttribute('seconds'),
                        $node->getChild('media')->getAttribute('acodec'),
                        $author
                    ]);
                } elseif ($node->getChild('media')->getAttribute('type') == 'vcard') {
                    if ($node->getChild('media')->hasChild('vcard')) {
                        $name = $node->getChild('media')->getChild('vcard')->getAttribute('name');
                        $data = $node->getChild('media')->getChild('vcard')->getData();
                    } else {
                        $name = 'NO_NAME';
                        $data = $node->getChild('media')->getData();
                    }
                    $author = $node->getAttribute('participant');

                    $this->eventsManager()->fire('onGetvCard', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('notify'),
                        $name,
                        $data,
                        $author
                    ]);
                } elseif ($node->getChild('media')->getAttribute('type') == 'location') {
                    $url = $node->getChild('media')->getAttribute('url');
                    $name = $node->getChild('media')->getAttribute('name');
                    $author = $node->getAttribute('participant');

                    $this->eventsManager()->fire('onGetLocation', [
                        $this->phoneNumber,
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('notify'),
                        $name,
                        $node->getChild('media')->getAttribute('longitude'),
                        $node->getChild('media')->getAttribute('latitude'),
                        $url,
                        $node->getChild('media')->getData(),
                        $author
                    ]);
                }

                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getChild('received') != null) {
                $this->eventsManager()->fire('onMessageReceivedClient', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('type'),
                    $node->getAttribute('t'),
                    $node->getAttribute('participant')
                ]);
            }
        }
        if ($node->getTag() == 'presence' && $node->getAttribute('status') == 'dirty') {
            //clear dirty
            $categories = [];
            if (count($node->getChildren()) > 0) {
                foreach ($node->getChildren() as $child) {
                    if ($child->getTag() == 'category') {
                        $categories[] = $child->getAttribute('name');
                    }
                }
            }
            $this->sendClearDirty($categories);
        }
        if (strcmp($node->getTag(), 'presence') == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), '-') === false
        ) {
            $presence = [];
            if ($node->getAttribute('type') == null) {
                $this->eventsManager()->fire('onPresenceAvailable', [
                    $this->phoneNumber,
                    $node->getAttribute('from')
                ]);
            } else {
                $this->eventsManager()->fire('onPresenceUnavailable', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('last')
                ]);
            }
        }
        if ($node->getTag() == 'presence'
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), '-') !== false
            && $node->getAttribute('type') != null
        ) {
            $groupId = parseJID($node->getAttribute('from'));
            if ($node->getAttribute('add') != null) {
                $this->eventsManager()->fire('onGroupsParticipantsAdd', [
                    $this->phoneNumber,
                    $groupId,
                    parseJID($node->getAttribute('add'))
                ]);
            } elseif ($node->getAttribute('remove') != null) {
                $this->eventsManager()->fire('onGroupsParticipantsRemove', [
                    $this->phoneNumber,
                    $groupId,
                    parseJID($node->getAttribute('remove'))
                ]);
            }
        }
        if (strcmp($node->getTag(), 'chatstate') == 0
            && strncmp($node->getAttribute('from'), $this->phoneNumber, strlen($this->phoneNumber)) != 0
            && strpos($node->getAttribute('from'), '-') === false
        ) {
            if ($node->getChild(0)->getTag() == 'composing') {
                $this->eventsManager()->fire('onMessageComposing', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    'composing',
                    $node->getAttribute('t')
                ]);
            } else {
                $this->eventsManager()->fire('onMessagePaused', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    'paused',
                    $node->getAttribute('t')
                ]);
            }
        }
        if ($node->getTag() == 'iq'
            && $node->getAttribute('type') == 'get'
            && $node->getAttribute('xmlns') == 'urn:xmpp:ping'
        ) {
            $this->eventsManager()->fire('onPing', [
                $this->phoneNumber,
                $node->getAttribute('id')
            ]);
            $this->sendPong($node->getAttribute('id'));
        }

        if ($node->getTag() == 'iq' && $node->getChild('sync') != null) {
            //sync result
            $sync = $node->getChild('sync');
            $existing = $sync->getChild('in');
            $nonexisting = $sync->getChild('out');

            //process existing first
            $existingUsers = [];
            if (!empty($existing)) {
                foreach ($existing->getChildren() as $child) {
                    $existingUsers[$child->getData()] = $child->getAttribute('jid');
                }
            }

            //now process failed numbers
            $failedNumbers = [];
            if (!empty($nonexisting)) {
                foreach ($nonexisting->getChildren() as $child) {
                    $failedNumbers[] = str_replace('+', '', $child->getData());
                }
            }

            $index = $sync->getAttribute('index');
            $result = new SyncResult($index, $sync->getAttribute('sid'), $existingUsers, $failedNumbers);

            $this->eventsManager()->fire('onGetSyncResult', [
                $result
            ]);
        }

        if ($node->getTag() == 'receipt') {
            $this->eventsManager()->fire('onGetReceipt', [
                $node->getAttribute('from'),
                $node->getAttribute('id'),
                $node->getAttribute('offline'),
                $node->getAttribute('retry')
            ]);
        }

        if ($node->getTag() == 'iq' && $node->getAttribute('type') == 'result') {
            if ($node->getChild('query') != null) {
                if (isset($this->nodeId['privacy']) && ($this->nodeId['privacy'] == $node->getAttribute('id'))) {
                    $listChild = $node->getChild(0)->getChild(0);
                    foreach ($listChild->getChildren() as $child) {
                        $blockedJids[] = $child->getAttribute('value');
                    }
                    $this->eventsManager()->fire('onGetPrivacyBlockedList', [
                        $this->phoneNumber,
                        $blockedJids
                    ]);

                    return;
                }
                $this->eventsManager()->fire('onGetRequestLastSeen', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getChild(0)->getAttribute('seconds')
                ]);
            }
            if ($node->getChild('props') != null) {
                //server properties
                $props = [];
                foreach ($node->getChild(0)->getChildren() as $child) {
                    $props[$child->getAttribute('name')] = $child->getAttribute('value');
                }
                $this->eventsManager()->fire('onGetServerProperties', [
                    $this->phoneNumber,
                    $node->getChild(0)->getAttribute('version'),
                    $props
                ]);
            }

            if ($node->getChild('picture') != null) {
                $this->eventsManager()->fire('onGetProfilePicture', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getChild('picture')->getAttribute('type'),
                    $node->getChild('picture')->getData()
                ]);
            }

            if ($node->getChild('media') != null || $node->getChild('duplicate') != null) {
                $this->processUploadResponse($node);
            }

            if (strpos($node->getAttribute('from'), WHATSAPP_GROUP_SERVER) !== false) {
                //There are multiple types of Group reponses. Also a valid group response can have NO children.
                //Events fired depend on text in the ID field.
                $groupList = [];
                $groupNodes = [];
                if ($node->getChild(0) != null && $node->getChild(0)->getChildren() != null) {
                    foreach ($node->getChild(0)->getChildren() as $child) {
                        $groupList[] = $child->getAttributes();
                        $groupNodes[] = $child;
                    }
                }

                if (isset($this->nodeId['groupcreate']) && ($this->nodeId['groupcreate'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getAttribute('id');
                    $this->eventsManager()->fire('onGroupsChatCreate', [
                        $this->phoneNumber,
                        $this->groupId
                    ]);
                }

                if (isset($this->nodeId['leavegroup']) && ($this->nodeId['leavegroup'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getChild(0)->getAttribute('id');
                    $this->eventsManager()->fire('onGroupsChatEnd', [
                        $this->phoneNumber,
                        $this->groupId
                    ]);
                }

                if (isset($this->nodeId['getgroups']) && ($this->nodeId['getgroups'] == $node->getAttribute('id'))) {
                    $this->eventsManager()->fire('onGetGroups', [
                        $this->phoneNumber,
                        $groupList
                    ]);
                    //getGroups returns a array of nodes which are exactly the same as from getGroupV2Info
                    //so lets call this event, we have all data at hand, no need to call getGroupV2Info for every
                    //group we are interested
                    foreach ($groupNodes AS $groupNode) {
                        $this->handleGroupV2InfoResponse($groupNode, true);
                    }

                }

                if (isset($this->nodeId['get_groupv2_info']) && ($this->nodeId['get_groupv2_info'] == $node->getAttribute('id'))) {
                    $groupChild = $node->getChild(0);
                    if ($groupChild != null) {
                        $this->handleGroupV2InfoResponse($groupChild);
                    }
                }
            }

            if (isset($this->nodeId['get_lists']) && ($this->nodeId['get_lists'] == $node->getAttribute('id'))) {
                $broadcastLists = [];
                if ($node->getChild(0) != null) {
                    $childArray = $node->getChildren();
                    foreach ($childArray as $list) {
                        if ($list->getChildren() != null) {
                            foreach ($list->getChildren() as $sublist) {
                                $id = $sublist->getAttribute('id');
                                $name = $sublist->getAttribute('name');
                                $broadcastLists[$id]['name'] = $name;
                                $recipients = [];
                                foreach ($sublist->getChildren() as $recipient) {
                                    array_push($recipients, $recipient->getAttribute('jid'));
                                }
                                $broadcastLists[$id]['recipients'] = $recipients;
                            }
                        }
                    }
                }

                $this->eventsManager()->fire('onGetBroadcastLists', [
                    $this->phoneNumber,
                    $broadcastLists
                ]);
            }

            if ($node->getChild('pricing') != null) {
                $this->eventsManager()->fire('onGetServicePricing', [
                    $this->phoneNumber,
                    $node->getChild(0)->getAttribute('price'),
                    $node->getChild(0)->getAttribute('cost'),
                    $node->getChild(0)->getAttribute('currency'),
                    $node->getChild(0)->getAttribute('expiration')
                ]);
            }

            if ($node->getChild('extend') != null) {
                $this->eventsManager()->fire('onGetExtendAccount', [
                    $this->phoneNumber,
                    $node->getChild('account')->getAttribute('kind'),
                    $node->getChild('account')->getAttribute('status'),
                    $node->getChild('account')->getAttribute('creation'),
                    $node->getChild('account')->getAttribute('expiration')
                ]);
            }

            if ($node->getChild('normalize') != null) {
                $this->eventsManager()->fire('onGetNormalizedJid', [
                    $this->phoneNumber,
                    $node->getChild(0)->getAttribute('result')
                ]);
            }

            if ($node->getChild('status') != null) {
                $child = $node->getChild('status');
                foreach ($child->getChildren() as $status) {
                    $this->eventsManager()->fire('onGetStatus', [
                        $this->phoneNumber,
                        $status->getAttribute('jid'),
                        'requested',
                        $node->getAttribute('id'),
                        $status->getAttribute('t'),
                        $status->getData()
                    ]);
                }
            }
        }

        if ($node->getTag() == 'iq' && $node->getAttribute('type') == 'error') {
            $errorType = null;
            foreach ($this->nodeId AS $type => $nodeID) {
                if ($nodeID == $node->getAttribute('id')) {
                    $errorType = $type;
                    break;
                }
            }

            $this->eventsManager()->fire('onGetError', [
                $this->phoneNumber,
                $node->getAttribute('from'),
                $node->getAttribute('id'),
                $node->getChild(0),
                $errorType
            ]);
        }

        if ($node->getTag() == 'message' && $node->getAttribute('type') == 'media' && $node->getChild(0)->getAttribute('type') == 'image') {
            $msgId = $this->createIqId();

            $ackNode = new ProtocolNode('ack', [
                'url' => $node->getChild(0)->getAttribute('url')
            ]);

            $iqNode = new ProtocolNode('iq', [
                'id' => $msgId,
                'xmlns' => 'w:m',
                'type' => 'set',
                'to' => WHATSAPP_SERVER
            ], [$ackNode]);

            $this->sendNode($iqNode);
        }

        $children = $node->getChild(0);
        if ($node->getTag() == 'stream:error' && !empty($children) && $node->getChild(0)->getTag() == 'system-shutdown') {
            $this->eventsManager()->fire('onStreamError', [
                $node->getChild(0)->getTag()
            ]);
        }

        if ($node->getTag() == 'stream:error') {
            $this->eventsManager()->fire('onStreamError', [
                $node->getChild(0)->getTag()
            ]);
        }

        if ($node->getTag() == 'notification') {
            $name = $node->getAttribute('notify');
            $type = $node->getAttribute('type');
            switch ($type) {
                case 'status':
                    $this->eventsManager()->fire('onGetStatus', [
                        $this->phoneNumber, //my number
                        $node->getAttribute('from'),
                        $node->getChild(0)->getTag(),
                        $node->getAttribute('id'),
                        $node->getAttribute('t'),
                        $node->getChild(0)->getData()
                    ]);
                    break;
                case 'picture':
                    if ($node->hasChild('set')) {
                        $this->eventsManager()->fire('onProfilePictureChanged', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('t')
                        ]);
                    } else {
                        if ($node->hasChild('delete')) {
                            $this->eventsManager()->fire('onProfilePictureDeleted', [
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('t')
                            ]);
                        }
                    }
                    //TODO
                    break;
                case 'contacts':
                    $notification = $node->getChild(0)->getTag();
                    if ($notification == 'add') {
                        $this->eventsManager()->fire('onNumberWasAdded', [
                            $this->phoneNumber,
                            $node->getChild(0)->getAttribute('jid')
                        ]);
                    } elseif ($notification == 'remove') {
                        $this->eventsManager()->fire('onNumberWasRemoved', [
                            $this->phoneNumber,
                            $node->getChild(0)->getAttribute('jid')
                        ]);
                    } elseif ($notification == 'update') {
                        $this->eventsManager()->fire('onNumberWasUpdated', [
                            $this->phoneNumber,
                            $node->getChild(0)->getAttribute('jid')
                        ]);
                    }
                    break;
                case 'encrypt':
                    $value = $node->getChild(0)->getAttribute('value');
                    if (is_numeric($value)) {
                        $this->eventsManager()->fire('onGetKeysLeft', [
                            $this->phoneNumber,
                            $node->getChild(0)->getAttribute('value')
                        ]);
                    } else {
                        echo 'Corrupt Stream: value ' . $value . 'is not numeric';
                    }
                    break;
                case 'w:gp2':
                    if ($node->hasChild('remove')) {
                        if ($node->getChild(0)->hasChild('participant')) {
                            $this->eventsManager()->fire('onGroupsParticipantsRemove', [
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('jid')
                            ]);
                        }
                    } else {
                        if ($node->hasChild('add')) {
                            $this->eventsManager()->fire('onGroupsParticipantsAdd', [
                                $this->phoneNumber,
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('jid')
                            ]);
                        } else {
                            if ($node->hasChild('create')) {
                                $groupMembers = [];
                                foreach ($node->getChild(0)->getChild(0)->getChildren() AS $cn) {
                                    $groupMembers[] = $cn->getAttribute('jid');
                                }

                                $this->eventsManager()->fire('onGroupisCreated', [
                                    $this->phoneNumber,
                                    $node->getChild(0)->getChild(0)->getAttribute('creator'),
                                    $node->getChild(0)->getChild(0)->getAttribute('id'),
                                    $node->getChild(0)->getChild(0)->getAttribute('subject'),
                                    $node->getAttribute('participant'),
                                    $node->getChild(0)->getChild(0)->getAttribute('creation'),
                                    $groupMembers
                                ]);
                            } else {
                                if ($node->hasChild('subject')) {
                                    $this->eventsManager()->fire('onGetGroupsSubject', [
                                        $this->phoneNumber,
                                        $node->getAttribute('from'),
                                        $node->getAttribute('t'),
                                        $node->getAttribute('participant'),
                                        $node->getAttribute('notify'),
                                        $node->getChild(0)->getAttribute('subject')
                                    ]);
                                } else {
                                    if ($node->hasChild('promote')) {
                                        $promotedJIDs = [];
                                        foreach ($node->getChild(0)->getChildren() AS $cn) {
                                            $promotedJIDs[] = $cn->getAttribute('jid');
                                        }

                                        $this->eventsManager()->fire('onGroupsParticipantsPromote', [
                                                $this->phoneNumber,
                                                $node->getAttribute('from'),        //Group-JID
                                                $node->getAttribute('t'),           //Time
                                                $node->getAttribute('participant'), //Issuer-JID
                                                $node->getAttribute('notify'),      //Issuer-Name
                                                $promotedJIDs
                                            ]
                                        );
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'account':
                    if (($node->getChild(0)->getAttribute('author')) == '') {
                        $author = 'Paypal';
                    } else {
                        $author = $node->getChild(0)->getAttribute('author');
                    }

                    $this->eventsManager()->fire('onPaidAccount', [
                        $this->phoneNumber,
                        $author,
                        $node->getChild(0)->getChild(0)->getAttribute('kind'),
                        $node->getChild(0)->getChild(0)->getAttribute('status'),
                        $node->getChild(0)->getChild(0)->getAttribute('creation'),
                        $node->getChild(0)->getChild(0)->getAttribute('expiration')
                    ]);
                    break;
                case 'features':
                    if ($node->getChild(0)->getChild(0) == 'encrypt') {
                        $this->eventsManager()->fire('onGetFeature', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getChild(0)->getChild(0)->getAttribute('value')
                        ]);
                    }
                    break;
                case 'web':
                    if (($node->getChild(0)->getTag() == 'action') && ($node->getChild(0)->getAttribute('type') == 'sync')) {
                        $data = $node->getChild(0)->getChildren();
                        $this->eventsManager()->fire('onWebSync', [
                            $this->phoneNumber,
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $data[0]->getData(),
                            $data[1]->getData(),
                            $data[2]->getData()
                        ]);
                    }
                    break;
                default:
                    throw new \Exception('Method $type not implemented');
            }
            $this->sendAck($node, 'notification');
        }
        if ($node->getTag() == 'call') {
            if ($node->getChild(0)->getTag() == 'offer') {
                $callId = $node->getChild(0)->getAttribute('call-id');
                $this->sendReceipt($node, null, null, $callId);

                $this->eventsManager()->fire('onCallReceived', [
                    $this->phoneNumber,
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('notify'),
                    $node->getAttribute('t'),
                    $node->getChild(0)->getAttribute('call-id')
                ]);
            } else {
                $this->sendAck($node, 'call');
            }

        }
        if ($node->getTag() == 'ib') {
            foreach ($node->getChildren() as $child) {
                switch ($child->getTag()) {
                    case 'dirty':
                        $this->sendClearDirty([$child->getAttribute('type')]);
                        break;
                    case 'account':
                        $this->eventsManager()->fire('onPaymentRecieved', [
                            $this->phoneNumber,
                            $child->getAttribute('kind'),
                            $child->getAttribute('status'),
                            $child->getAttribute('creation'),
                            $child->getAttribute('expiration')
                        ]);
                        break;
                    case 'offline':

                        break;
                    default:
                        throw new \Exception('ib handler for ' . $child->getTag() . ' not implemented');
                }
            }
        }

        // Disconnect socket on stream error.
        if ($node->getTag() == 'stream:error') {
            $this->disconnect();
        }
    }

    /**
     * Process the challenge.
     *
     * @param ProtocolNode $node The node that contains the challenge.
     */
    protected function processChallenge($node)
    {
        $this->challengeData = $node->getData();
    }

    /**
     * @param $node  ProtocolNode
     * @param $class string
     */
    protected function sendAck($node, $class)
    {
        $from = $node->getAttribute('from');
        $to = $node->getAttribute('to');
        $participant = $node->getAttribute('participant');
        $id = $node->getAttribute('id');
        $type = $node->getAttribute('type');

        $attributes = [];
        if ($to) {
            $attributes['from'] = $to;
        }

        if ($participant) {
            $attributes['participant'] = $participant;
        }

        $attributes['to'] = $from;
        $attributes['class'] = $class;
        $attributes['id'] = $id;
        if ($type != null) {
            $attributes['type'] = $type;
        }

        $this->sendNode(new ProtocolNode('ack', $attributes));
    }

    /**
     * Send the next message.
     */
    public function sendNextMessage()
    {
        if (count($this->outQueue) > 0) {
            $msgnode = array_shift($this->outQueue);
            $msgnode->refreshTimes();
            $this->lastId = $msgnode->getAttribute('id');
            $this->sendNode($msgnode);
        } else {
            $this->lastId = false;
        }
    }

    /**
     * Tell the server we received the message.
     *
     * @param ProtocolNode $node The ProtocolTreeNode that contains the message.
     * @param string $type
     * @param string $participant
     * @param string $callId
     */
    protected function sendReceipt($node, $type = 'read', $participant = null, $callId = null)
    {
        $messageHash = [];
        if ($type == 'read') {
            $messageHash['type'] = $type;
        }
        if ($participant != null) {
            $messageHash['participant'] = $participant;
        }
        $messageHash['to'] = $node->getAttribute('from');
        $messageHash['id'] = $node->getAttribute('id');

        if ($callId != null) {
            $offerNode = new ProtocolNode('offer', ['call-id' => $callId]);
            $messageNode = new ProtocolNode('receipt', $messageHash, [$offerNode]);
        } else {
            $messageNode = new ProtocolNode('receipt', $messageHash);
        }
        $this->sendNode($messageNode);
        $this->eventsManager()->fire('onSendMessageReceived', [
            $this->phoneNumber,
            $node->getAttribute('id'),
            $node->getAttribute('from'),
            $type
        ]);
    }

    /**
     * Clears the "dirty" status on your account
     *
     * @param  array $categories
     */
    protected function sendClearDirty($categories)
    {
        $msgId = $this->createIqId();

        $catnodes = [];
        foreach ($categories as $category) {
            $catnode = new ProtocolNode('clean', ['type' => $category]);
            $catnodes[] = $catnode;
        }
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'type' => 'set',
            'to' => WHATSAPP_SERVER,
            'xmlns' => 'urn:xmpp:whatsapp:dirty'
        ], $catnodes);

        $this->sendNode($node);
    }

    /**
     * iq id
     *
     * @return string
     *    Iq id
     */
    protected function createIqId()
    {
        $iqId = $this->iqCounter;
        $this->iqCounter++;

        return dechex($iqId);
    }

    /**
     * Send a pong to the WhatsApp server. I'm alive!
     *
     * @param string $msgid The id of the message.
     */
    public function sendPong($msgid)
    {
        $messageNode = new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'id' => $msgid,
            'type' => 'result'
        ]);

        $this->sendNode($messageNode);
        $this->eventsManager()->fire('onSendPong', [
            $this->phoneNumber,
            $msgid
        ]);
    }

    /**
     * Process media upload response
     *
     * @param ProtocolNode $node Message node
     * @return bool
     */
    protected function processUploadResponse($node)
    {
        $id = $node->getAttribute('id');
        $messageNode = @$this->mediaQueue[$id];
        if ($messageNode == null) {
            //message not found, can't send!
            $this->eventsManager()->fire('onMediaUploadFailed', [
                $this->phoneNumber,
                $id,
                $node,
                $messageNode,
                'Message node not found in queue'
            ]);

            return false;
        }

        $duplicate = $node->getChild('duplicate');
        if ($duplicate != null) {
            //file already on whatsapp servers
            $url = $duplicate->getAttribute('url');
            $filesize = $duplicate->getAttribute('size');
//          $mimetype = $duplicate->getAttribute('mimetype');
            $filehash = $duplicate->getAttribute('filehash');
            $filetype = $duplicate->getAttribute('type');
//          $width = $duplicate->getAttribute('width');
//          $height = $duplicate->getAttribute('height');
            $exploded = explode('/', $url);
            $filename = array_pop($exploded);
        } else {
            //upload new file
            $json = MediaUploader::pushFile($node, $messageNode, $this->mediaFileInfo, $this->phoneNumber);

            if (!$json) {
                //failed upload
                $this->eventsManager()->fire('onMediaUploadFailed', [
                    $this->phoneNumber,
                    $id,
                    $node,
                    $messageNode,
                    'Failed to push file to server'
                ]);

                return false;
            }

            $url = $json->url;
            $filesize = $json->size;
//          $mimetype = $json->mimetype;
            $filehash = $json->filehash;
            $filetype = $json->type;
//          $width = $json->width;
//          $height = $json->height;
            $filename = $json->name;
        }

        $mediaAttribs = [];
        $mediaAttribs['type'] = $filetype;
        $mediaAttribs['url'] = $url;
        $mediaAttribs['encoding'] = 'raw';
        $mediaAttribs['file'] = $filename;
        $mediaAttribs['size'] = $filesize;
        if ($this->mediaQueue[$id]['caption'] != '') {
            $mediaAttribs['caption'] = $this->mediaQueue[$id]['caption'];
        }

        $filepath = $this->mediaQueue[$id]['filePath'];
        $to = $this->mediaQueue[$id]['to'];

        switch ($filetype) {
            case 'image':
                $caption = $this->mediaQueue[$id]['caption'];
                $icon = createIcon($filepath);
                break;
            case 'video':
                $caption = $this->mediaQueue[$id]['caption'];
                $icon = createVideoIcon($filepath);
                break;
            default:
                $caption = '';
                $icon = '';
                break;
        }
        //Retrieve Message ID
        $message_id = $messageNode['message_id'];

        $mediaNode = new ProtocolNode('media', $mediaAttribs, null, $icon);
        if (is_array($to)) {
            $this->sendBroadcast($to, $mediaNode, 'media');
        } else {
            $this->sendMessageNode($to, $mediaNode, $message_id);
        }

        $this->eventsManager()->fire('onMediaMessageSent', [
            $this->phoneNumber,
            $to,
            $id,
            $filetype,
            $url,
            $filename,
            $filesize,
            $filehash,
            $caption,
            $icon
        ]);

        return true;
    }

    /**
     * Send a broadcast
     * @param array $targets Array of numbers to send to
     * @param object $node
     * @param        $type
     * @return string
     */
    protected function sendBroadcast($targets, $node, $type)
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        $toNodes = [];
        foreach ($targets as $target) {
            $jid = $this->getJID($target);
            $hash = ['jid' => $jid];
            $toNode = new ProtocolNode('to', $hash);
            $toNodes[] = $toNode;
        }

        $broadcastNode = new ProtocolNode('broadcast', null, $toNodes);

        $msgId = $this->createMsgId();
        $messageNode = new ProtocolNode('message', [
            'to' => time() . '@broadcast',
            'type' => $type,
            'id' => $msgId
        ], [$node, $broadcastNode]);

        $this->sendNode($messageNode);
        $this->waitForServer($msgId);
        //listen for response
        $this->eventsManager()->fire('onSendMessage', [
            $this->phoneNumber,
            $targets,
            $msgId,
            $node
        ]);

        return $msgId;
    }

    /**
     * Process number/jid and turn it into a JID if necessary
     *
     * @param string $number
     *  Number to process
     * @return string
     */
    protected function getJID($number)
    {
        if (!stristr($number, '@')) {
            //check if group message
            if (stristr($number, '-')) {
                //to group
                $number .= '@' . WHATSAPP_GROUP_SERVER;
            } else {
                //to normal user
                $number .= '@' . WHATSAPP_SERVER;
            }
        }

        return $number;
    }

    /**
     * Create a unique msg id.
     *
     * @return string
     *   A message id string.
     */
    protected function createMsgId()
    {
        $msgid = $this->messageCounter;
        $this->messageCounter++;

        return $this->loginTime . '-' . $msgid;
    }

    /**
     * Wait for WhatsApp server to acknowledge *it* has received message.
     * @param string $id The id of the node sent that we are awaiting acknowledgement of.
     * @param int $timeout
     */
    public function waitForServer($id, $timeout = 5)
    {
        $time = time();
        $this->serverReceivedId = false;
        do {
            $this->pollMessage();
        } while ($this->serverReceivedId !== $id && time() - $time < $timeout);
    }

    /**
     * Send node to the servers.
     *
     * @param string $to
     * @param ProtocolNode $node
     * @param null $id
     *
     * @return string            Message ID.
     */
    protected function sendMessageNode($to, $node, $id = null)
    {
        $msgId = ($id == null) ? $this->createMsgId() : $id;
        $to = $this->getJID($to);

        $messageNode = new ProtocolNode('message', [
            'to' => $to,
            'type' => ($node->getTag() == 'body') ? 'text' : 'media',
            'id' => $msgId,
            't' => time()
        ], [$node], '');

        $this->sendNode($messageNode);

        $this->eventsManager()->fire('onSendMessage', [
            $this->phoneNumber,
            $to,
            $msgId,
            $node
        ]);

        $this->waitForServer($msgId);

        return $msgId;
    }

    /**
     * @param ProtocolNode $groupNode
     * @param mixed $fromGetGroups
     */
    protected function handleGroupV2InfoResponse(ProtocolNode $groupNode, $fromGetGroups = false)
    {
        $creator = $groupNode->getAttribute('creator');
        $creation = $groupNode->getAttribute('creation');
        $subject = $groupNode->getAttribute('subject');
        $groupID = $groupNode->getAttribute('id');
        $participants = [];
        $admins = [];
        if ($groupNode->getChild(0) != null) {
            foreach ($groupNode->getChildren() as $child) {
                $participants[] = $child->getAttribute('jid');
                if ($child->getAttribute('type') == 'admin') {
                    $admins[] = $child->getAttribute('jid');
                }
            }
        }
        $this->eventsManager()->fire('onGetGroupV2Info', [
                $this->phoneNumber,
                $groupID,
                $creator,
                $creation,
                $subject,
                $participants,
                $admins,
                $fromGetGroups
            ]
        );
    }

    /**
     * Add the auth response to protocoltreenode.
     *
     * @return ProtocolNode Returns a response node.
     */
    protected function createAuthResponseNode()
    {
        return new ProtocolNode('response', null, null, $this->authenticate());
    }

    /**
     * Authenticate with the WhatsApp Server.
     *
     * @return string Returns binary string
     */
    protected function authenticate()
    {
        $keys = KeyStream::GenerateKeys(base64_decode($this->password), $this->challengeData);
        $this->inputKey = new KeyStream($keys[2], $keys[3]);
        $this->outputKey = new KeyStream($keys[0], $keys[1]);
        $array = "\0\0\0\0" . $this->phoneNumber . $this->challengeData; // . time() . WHATSAPP_USER_AGENT . " MccMnc/" . str_pad($phone["mcc"], 3, "0", STR_PAD_LEFT) . "001";
        $response = $this->outputKey->EncodeMessage($array, 0, 4, strlen($array) - 4);

        return $response;
    }

    /**
     * @param null $nickname
     */
    public function sendAvailableForChat($nickname = null)
    {
        $presence = [];
        if ($nickname) {
            //update nickname
            $this->name = $nickname;
        }

        $presence['name'] = $this->name;
        $node = new ProtocolNode('presence', $presence);
        $this->sendNode($node);
    }

    /**
     * Login to the WhatsApp server with your password
     *
     * If you already know your password you can log into the Whatsapp server
     * using this method.
     *
     * @param  string $password Your whatsapp password. You must already know this!
     */
    public function loginWithPassword($password)
    {
        $this->password = $password;
        if (is_readable($this->challengeFilename)) {
            $challengeData = file_get_contents($this->challengeFilename);
            if ($challengeData) {
                $this->challengeData = $challengeData;
            }
        }
        $this->doLogin();
    }

    /**
     * Send the active status. User will show up as "Online" (as long as socket is connected).
     */
    public function sendActiveStatus()
    {
        $messageNode = new ProtocolNode('presence', ['type' => 'active']);
        $this->sendNode($messageNode);
    }

    /**
     * Send a request to get cipher keys from an user
     *
     * @param $number
     *    Phone number of the user you want to get the cipher keys.
     */
    public function sendGetCipherKeysFromUser($number)
    {
        $msgId = $this->createIqId();

        $userNode = new ProtocolNode('user', [
            'jid' => $this->getJID($number)
        ]);
        $keyNode = new ProtocolNode('key', null, [$userNode]);
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'encrypt',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [$keyNode]);

        $this->sendNode($node);
    }

    /**
     * Send a Broadcast Message with audio.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array $targets An array of numbers to send to.
     * @param string $path URL or local path to the audio file to send
     * @param bool $storeURLmedia Keep a copy of the audio file on your server
     * @param int $fsize
     * @param string $fhash
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastAudio($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        // Return message ID. Make pull request for this.
        return $this->sendMessageAudio($targets, $path, $storeURLmedia, $fsize, $fhash);
    }

    /**
     * Send audio to the user/group.
     *
     * @param string $to The recipient.
     * @param string $filepath The url/uri to the audio file.
     * @param bool $storeURLmedia Keep copy of file
     * @param int $fsize
     * @param string $fhash *
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageAudio($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '')
    {
        if ($fsize == 0 || $fhash == '') {
            $allowedExtensions = ['3gp', 'caf', 'wav', 'mp3', 'wma', 'ogg', 'aif', 'aac', 'm4a'];
            $size = 10 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'audio', $allowedExtensions, $storeURLmedia);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'audio', $fsize, $filepath, $to);
        }
    }

    /**
     * Checks that the media file to send is of allowable filetype and within size limits.
     *
     * @param string $filepath The URL/URI to the media file
     * @param int $maxSize Maximum filesize allowed for media type
     * @param string $to Recipient ID/number
     * @param string $type media filetype. 'audio', 'video', 'image'
     * @param array $allowedExtensions An array of allowable file types for the media file
     * @param bool $storeURLmedia Keep a copy of the media file
     * @param string $caption *
     * @return string|null              Message ID if successfully, null if not.
     */
    protected function sendCheckAndSendMedia(
        $filepath,
        $maxSize,
        $to,
        $type,
        $allowedExtensions,
        $storeURLmedia,
        $caption = ''
    ) {
        if ($this->getMediaFile($filepath, $maxSize) == true) {
            if (in_array($this->mediaFileInfo['fileextension'], $allowedExtensions)) {
                $b64hash = base64_encode(hash_file('sha256', $this->mediaFileInfo['filepath'], true));
                //request upload and get Message ID
                $id = $this->sendRequestFileUpload($b64hash, $type, $this->mediaFileInfo['filesize'],
                    $this->mediaFileInfo['filepath'], $to, $caption);
                $this->processTempMediaFile($storeURLmedia);

                // Return message ID. Make pull request for this.
                return $id;
            } else {
                //Not allowed file type.
                $this->processTempMediaFile($storeURLmedia);

                return null;
            }
        } else {
            //Didn't get media file details.
            return null;
        }
    }

    /**
     * Retrieves media file and info from either a URL or localpath
     *
     * @param string $filepath The URL or path to the mediafile you wish to send
     * @param integer $maxsizebytes The maximum size in bytes the media file can be. Default 5MB
     *
     * @return bool  false if file information can not be obtained.
     */
    protected function getMediaFile($filepath, $maxsizebytes = 5242880)
    {
        if (filter_var($filepath, FILTER_VALIDATE_URL) !== false) {
            $this->mediaFileInfo = [];
            $this->mediaFileInfo['url'] = $filepath;

            $media = file_get_contents($filepath);
            $this->mediaFileInfo['filesize'] = strlen($media);

            if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                $this->mediaFileInfo['filepath'] = tempnam(DATA_FOLDER . DS . MEDIA_FOLDER,
                    'WHA');
                file_put_contents($this->mediaFileInfo['filepath'], $media);
                $this->mediaFileInfo['filemimetype'] = get_mime($this->mediaFileInfo['filepath']);
                $this->mediaFileInfo['fileextension'] = getExtensionFromMime($this->mediaFileInfo['filemimetype']);

                return true;
            }
        } else {
            if (file_exists($filepath)) {
                //Local file
                $this->mediaFileInfo['filesize'] = filesize($filepath);
                if ($this->mediaFileInfo['filesize'] < $maxsizebytes) {
                    $this->mediaFileInfo['filepath'] = $filepath;
                    $this->mediaFileInfo['fileextension'] = pathinfo($filepath, PATHINFO_EXTENSION);
                    $this->mediaFileInfo['filemimetype'] = get_mime($filepath);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Send request to upload file
     *
     * @param string $b64hash A base64 hash of file
     * @param string $type File type
     * @param string $size File size
     * @param string $filepath Path to image file
     * @param mixed $to Recipient(s)
     * @param string $caption
     * @return string          Message ID
     */
    protected function sendRequestFileUpload($b64hash, $type, $size, $filepath, $to, $caption = '')
    {
        $id = $this->createIqId();

        if (!is_array($to)) {
            $to = $this->getJID($to);
        }

        $mediaNode = new ProtocolNode('media', [
            'hash' => $b64hash,
            'type' => $type,
            'size' => $size
        ]);

        $node = new ProtocolNode('iq', [
            'id' => $id,
            'to' => WHATSAPP_SERVER,
            'type' => 'set',
            'xmlns' => 'w:m'
        ], [$mediaNode]);

        //add to queue
        $messageId = $this->createMsgId();
        $this->mediaQueue[$id] = [
            'messageNode' => $node,
            'filePath' => $filepath,
            'to' => $to,
            'message_id' => $messageId,
            'caption' => $caption
        ];

        $this->sendNode($node);
        $this->waitForServer($id);

        // Return message ID. Make pull request for this.
        return $messageId;
    }

    /**
     * If the media file was originally from a URL, this function either deletes it
     * or renames it depending on the user option.
     *
     * @param bool $storeURLmedia Save or delete the media file from local server
     */
    protected function processTempMediaFile($storeURLmedia)
    {
        if (isset($this->mediaFileInfo['url'])) {
            if ($storeURLmedia) {
                if (is_file($this->mediaFileInfo['filepath'])) {
                    rename($this->mediaFileInfo['filepath'],
                        $this->mediaFileInfo['filepath'] . '.' . $this->mediaFileInfo['fileextension']);
                }
            } else {
                if (is_file($this->mediaFileInfo['filepath'])) {
                    unlink($this->mediaFileInfo['filepath']);
                }
            }
        }
    }

    /**
     * Send a Broadcast Message with an image.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array $targets An array of numbers to send to.
     * @param string $path URL or local path to the image file to send
     * @param bool $storeURLmedia Keep a copy of the audio file on your server
     * @param int $fsize
     * @param string $fhash
     * @param string $caption
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastImage($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        // Return message ID. Make pull request for this.
        return $this->sendMessageImage($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Send an image file to group/user.
     *
     * @param string $to Recipient number
     * @param string $filepath The url/uri to the image file.
     * @param bool $storeURLmedia Keep copy of file
     * @param int $fsize size of the media file
     * @param string $fhash base64 hash of the media file
     * @param string $caption
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageImage($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if ($fsize == 0 || $fhash == '') {
            $allowedExtensions = ['jpg', 'jpeg', 'gif', 'png'];
            $size = 5 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'image', $allowedExtensions, $storeURLmedia,
                $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'image', $fsize, $filepath, $to, $caption);
        }
    }

    /**
     * Send a Broadcast Message with location data.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * If no name is supplied , receiver will see large sized google map
     * thumbnail of entered Lat/Long but NO name/url for location.
     *
     * With name supplied, a combined map thumbnail/name box is displayed
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param  array $targets An array of numbers to send to.
     * @param  float $long The longitude of the location eg 54.31652
     * @param  float $lat The latitude if the location eg -6.833496
     * @param  string $name (Optional) A name to describe the location
     * @param  string $url (Optional) A URL to link location to web resource
     * @return string           Message ID
     */
    public function sendBroadcastLocation($targets, $long, $lat, $name = null, $url = null)
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        // Return message ID. Make pull request for this.
        return $this->sendMessageLocation($targets, $long, $lat, $name, $url);
    }

    /**
     * Send a location to the user/group.
     *
     * If no name is supplied, the receiver will see a large google maps thumbnail of the lat/long,
     * but NO name or url of the location.
     *
     * When a name supplied, a combined map thumbnail/name box is displayed.
     *
     * @param mixed $to The recipient(s) to send the location to.
     * @param float $long The longitude of the location, e.g. 54.31652.
     * @param float $lat The latitude of the location, e.g. -6.833496.
     * @param string $name (Optional) A custom name for the specified location.
     * @param string $url (Optional) A URL to attach to the specified location.
     * @return string       Message ID
     */
    public function sendMessageLocation($to, $long, $lat, $name = null, $url = null)
    {
        $mediaNode = new ProtocolNode('media', [
            'type' => 'location',
            'encoding' => 'raw',
            'latitude' => $lat,
            'longitude' => $long,
            'name' => $name,
            'url' => $url
        ]);

        $id = (is_array($to)) ? $this->sendBroadcast($to, $mediaNode, 'media') : $this->sendMessageNode($to,
            $mediaNode);

        $this->waitForServer($id);

        // Return message ID. Make pull request for this.
        return $id;
    }

    /**
     * Send a Broadcast Message
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param  array $targets An array of numbers to send to.
     * @param  string $message Your message
     * @return string               Message ID
     */
    public function sendBroadcastMessage($targets, $message)
    {
        $bodyNode = new ProtocolNode('body', null, null, $message);

        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $bodyNode, 'text');
    }

    /**
     * Send a Broadcast Message with a video.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array $targets An array of numbers to send to.
     * @param string $path URL or local path to the video file to send
     * @param bool $storeURLmedia Keep a copy of the audio file on your server
     * @param int $fsize
     * @param string $fhash
     * @param string $caption
     * @return string|null           Message ID if successfully, null if not.
     */
    public function sendBroadcastVideo($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if (!is_array($targets)) {
            $targets = [$targets];
        }

        // Return message ID. Make pull request for this.
        return $this->sendMessageVideo($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Send a video to the user/group.
     *
     * @param string $to The recipient to send.
     * @param string $filepath A URL/URI to the MP4/MOV video.
     * @param bool $storeURLmedia Keep a copy of media file.
     * @param int $fsize Size of the media file
     * @param string $fhash base64 hash of the media file
     * @param string $caption *
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageVideo($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = '', $caption = '')
    {
        if ($fsize == 0 || $fhash == '') {
            $allowedExtensions = ['3gp', 'mp4', 'mov', 'avi'];
            $size = 20 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'video', $allowedExtensions, $storeURLmedia,
                $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'video', $fsize, $filepath, $to, $caption);
        }
    }

    /**
     * Delete Broadcast lists
     *
     * @param  string array $lists
     * Contains the broadcast-id list
     */
    public function sendDeleteBroadcastLists($lists)
    {
        $msgId = $this->createIqId();
        $listNode = [];
        $listsCount = count($lists);

        if ($lists != null && $listsCount > 0) {
            for ($i = 0; $i < $listsCount; $i++) {
                $listNode[$i] = new ProtocolNode('list', ['id' => $lists[$i]]);
            }
        } else {
            $listNode = null;
        }

        $deleteNode = new ProtocolNode('delete', null, $listNode);
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'w:b',
            'type' => 'set',
            'to' => WHATSAPP_SERVER
        ], [$deleteNode]);

        $this->sendNode($node);
    }

    /**
     *
     */
    public function sendClientConfig()
    {
        $child = new ProtocolNode('config', [
            'platform' => WHATSAPP_DEVICE,
            'version' => WHATSAPP_VER
        ]);
        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'type' => 'set',
            'xmlns' => 'urn:xmpp:whatsapp:push',
            'to' => WHATSAPP_SERVER
        ], [$child]);

        $this->sendNode($node);
    }

    /**
     *
     */
    public function sendGetClientConfig()
    {
        $msgId = $this->createIqId();
        $child = new ProtocolNode('config');
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'urn:xmpp:whatsapp:push',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [$child], null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Transfer your number to new one
     *
     * @param  string $number
     * @param  string $identity
     */
    public function sendChangeNumber($number, $identity)
    {
        $usernameNode = new ProtocolNode('username', null, null, $number);
        $passwordNode = new ProtocolNode('password', null, null, urldecode($identity));
        $modifyNode = new ProtocolNode('modify', null, [$usernameNode, $passwordNode]);
        $iqNode = new ProtocolNode('iq', [
            'xmlns' => 'urn:xmpp:whatsapp:account',
            'id' => $this->createIqId(),
            'type' => 'get',
            'to' => 'c.us'
        ], [$modifyNode]);

        $this->sendNode($iqNode);
    }

    /**
     * Send a request to return a list of groups user is currently participating in.
     *
     * To capture this list you will need to bind the "onGetGroups" event.
     */
    public function sendGetGroups()
    {
        $this->sendGetGroupsFiltered('participating');
    }

    /**
     * Send the getGroupList request to WhatsApp
     * @param  string $type Type of list of groups to retrieve. "owning" or "participating"
     */
    protected function sendGetGroupsFiltered($type)
    {
        $msgID = $this->nodeId['getgroups'] = $this->createIqId();

        $node = new ProtocolNode('iq', [
            'id' => $msgID,
            'type' => 'get',
            'xmlns' => 'w:g2',
            'to' => WHATSAPP_GROUP_SERVER
        ], [new ProtocolNode($type)], null);

        $this->sendNode($node);
        $this->waitForServer($msgID);
    }

    /**
     * Send a request to get new Groups V2 info.
     *
     * @param $groupID
     *    The group JID
     */
    public function sendGetGroupV2Info($groupID)
    {
        $this->nodeId['get_groupv2_info'] = $this->createIqId();

        $queryNode = new ProtocolNode('query', ['request' => 'interactive']);
        $node = new ProtocolNode('iq', [
            'id' => $this->nodeId['get_groupv2_info'],
            'xmlns' => 'w:g2',
            'type' => 'get',
            'to' => $this->getJID($groupID)
        ], [$queryNode]);

        $this->sendNode($node);
    }

    /**
     * Send a request to get a list of people you have currently blocked.
     */
    public function sendGetPrivacyBlockedList()
    {
        $this->nodeId['privacy'] = $this->createIqId();
        $child2 = new ProtocolNode('query', [], [new ProtocolNode('list', ['name' => 'default'])]);
        $node = new ProtocolNode('iq', [
            'id' => $this->nodeId['privacy'],
            'xmlns' => 'jabber:iq:privacy',
            'type' => 'get'
        ], [$child2]);

        $this->sendNode($node);
        $this->waitForServer($this->nodeId['privacy']);
    }

    /**
     * Send a request to get privacy settings.
     */
    public function sendGetPrivacySettings()
    {
        $this->nodeId['privacy_settings'] = $this->createIqId();
        $privacyNode = new ProtocolNode('privacy');
        $node = new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'id' => $this->nodeId['privacy_settings'],
            'xmlns' => 'privacy',
            'type' => 'get'
        ], [$privacyNode]);

        $this->sendNode($node);
        $this->waitForServer($this->nodeId['privacy_settings']);
    }

    /**
     * Set privacy of 'last seen', status or profile picture to all, contacts or none.
     *
     * @param string $category
     *   Options: 'last', 'status' or 'profile'
     * @param string $value
     *   Options: 'all', 'contacts' or 'none'
     */
    public function sendSetPrivacySettings($category, $value)
    {
        $msgId = $this->createIqId();
        $categoryNode = new ProtocolNode('category', ['name' => $category, 'value' => $value]);

        $privacyNode = new ProtocolNode('privacy', null, [$categoryNode]);
        $node = new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'type' => 'set',
            'id' => $msgId,
            'xmlns' => 'privacy'
        ], [$privacyNode]);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Get profile picture of specified user.
     *
     * @param string $number
     *  Number or JID of user
     * @param bool $large
     *  Request large picture
     */
    public function sendGetProfilePicture($number, $large = false)
    {
        $this->nodeId['getprofilepic'] = $this->createIqId();

        $hash = [];
        $hash['type'] = 'image';
        if (!$large) {
            $hash['type'] = 'preview';
        }
        $picture = new ProtocolNode('picture', $hash);

        $node = new ProtocolNode('iq', [
            'id' => $this->nodeId['getprofilepic'],
            'type' => 'get',
            'xmlns' => 'w:profile:picture',
            'to' => $this->getJID($number)
        ], [$picture]);

        $this->sendNode($node);
        $this->waitForServer($this->nodeId['getprofilepic']);
    }

    /**
     * @param  mixed $numbers Numbers to get profile profile photos of.
     * @return bool
     */
    public function sendGetProfilePhotoIds($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $msgId = $this->createIqId();

        $userNode = [];
        $c = count($numbers);
        for ($i = 0; $i < $c; $i++) {
            $userNode[] = new ProtocolNode('user', ['jid' => $this->getJID($numbers[$i])]);
        }

        if (!count($userNode)) {
            return false;
        }

        $listNode = new ProtocolNode('list', null, $userNode);
        $iqNode = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'w:profile:picture',
            'type' => 'get'
        ], [$listNode]);

        $this->sendNode($iqNode);
        $this->waitForServer($msgId);

        return true;
    }

    /**
     * Request to retrieve the last online time of specific user.
     *
     * @param string $to Number or JID of user
     */
    public function sendGetRequestLastSeen($to)
    {
        $this->nodeId['getlastseen'] = $this->createIqId();
        $messageNode = new ProtocolNode('iq', [
            'to' => $this->getJID($to),
            'type' => 'get',
            'id' => $this->nodeId['getlastseen'],
            'xmlns' => 'jabber:iq:last'
        ], [new ProtocolNode('query')]);

        $this->sendNode($messageNode);
        $this->waitForServer($this->nodeId['getlastseen']);
    }

    /**
     * Send a request to get the current server properties.
     */
    public function sendGetServerProperties()
    {
        $id = $this->createIqId();
        $child = new ProtocolNode('props');
        $node = new ProtocolNode('iq', [
            'id' => $id,
            'type' => 'get',
            'xmlns' => 'w',
            'to' => WHATSAPP_SERVER
        ], [$child]);

        $this->sendNode($node);
    }

    /**
     * Send a request to get the current service pricing.
     *
     * @param string $lg
     *   Language
     * @param string $lc
     *   Country
     */
    public function sendGetServicePricing($lg, $lc)
    {
        $pricingNode = new ProtocolNode('pricing', [
            'lg' => $lg,
            'lc' => $lc
        ]);
        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'urn:xmpp:whatsapp:account',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [$pricingNode]);

        $this->sendNode($node);
    }

    /**
     * Send a request to extend the account.
     */
    public function sendExtendAccount()
    {
        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'urn:xmpp:whatsapp:account',
            'type' => 'set',
            'to' => WHATSAPP_SERVER
        ], [new ProtocolNode('extend')]);

        $this->sendNode($node);
    }

    /**
     * Gets all the broadcast lists for an account.
     */
    public function sendGetBroadcastLists()
    {
        $this->nodeId['get_lists'] = $this->createIqId();
        $node = new ProtocolNode('iq', [
            'id' => $this->nodeId['get_lists'],
            'xmlns' => 'w:b',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [new ProtocolNode('lists')]);

        $this->sendNode($node);
    }

    /**
     * Send a request to get the normalized mobile number representing the JID.
     *
     * @param string $countryCode Country Code
     * @param string $number Mobile Number
     */
    public function sendGetNormalizedJid($countryCode, $number)
    {
        $ccNode = new ProtocolNode('cc', null, null, $countryCode);
        $inNode = new ProtocolNode('in', null, null, $number);
        $normalizeNode = new ProtocolNode('normalize', null, [$ccNode, $inNode]);
        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'urn:xmpp:whatsapp:account',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [$normalizeNode]);

        $this->sendNode($node);
    }

    /**
     * Removes an account from WhatsApp.
     *
     * @param string $lg Language
     * @param string $lc Country
     * @param string $feedback User Feedback
     */
    public function sendRemoveAccount($lg = null, $lc = null, $feedback = null)
    {
        if ($feedback != null && strlen($feedback) > 0) {
            if ($lg == null) {
                $lg = '';
            }

            if ($lc == null) {
                $lc = '';
            }

            $childNode = [
                new ProtocolNode('body', [
                    'lg' => $lg,
                    'lc' => $lc
                ], null, $feedback)
            ];
        } else {
            $childNode = null;
        }

        $this->sendNode(new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'xmlns' => 'urn:xmpp:whatsapp:account',
            'type' => 'get',
            'id' => $this->createIqId()
        ], [new ProtocolNode('remove', null, $childNode)]));
    }

    /**
     * Send a ping to the server.
     */
    public function sendPing()
    {
        $this->sendNode(new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'w:p',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [new ProtocolNode('ping')]));
    }

    /**
     * Get VoIP information of a number or numbers.
     *
     * @param mixed $jids
     */
    public function sendGetHasVoipEnabled($jids)
    {
        if (!is_array($jids)) {
            $jids = [$jids];
        }

        $userNode = [];
        foreach ($jids as $jid) {
            $userNode[] = new ProtocolNode('user', ['jid' => $this->getJID($jid)]);
        }

        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'voip',
            'type' => 'get',
            'to' => WHATSAPP_SERVER
        ], [new ProtocolNode('eligible', null, $userNode)]);

        $this->sendNode($node);
    }

    /**
     * Get the current status message of a specific user.
     *
     * @param mixed $jids The users' JIDs
     */
    public function sendGetStatuses($jids)
    {
        if (!is_array($jids)) {
            $jids = [$jids];
        }

        $children = [];
        foreach ($jids as $jid) {
            $children[] = new ProtocolNode('user', ['jid' => $this->getJID($jid)]);
        }

        $this->nodeId['getstatuses'] = $this->createIqId();

        $node = new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'type' => 'get',
            'xmlns' => 'status',
            'id' => $this->nodeId['getstatuses']
        ], [new ProtocolNode('status', null, $children)]);

        $this->sendNode($node);
        $this->waitForServer($this->nodeId['getstatuses']);
    }

    /**
     * Create a group chat.
     *
     * @param string $subject
     *   The group Subject
     * @param array $participants
     *   An array with the participants numbers.
     *
     * @return string
     *   The group ID.
     */
    public function sendGroupsChatCreate($subject, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }

        $participantNode = [];
        foreach ($participants as $participant) {
            $participantNode[] = new ProtocolNode('participant', [
                'jid' => $this->getJID($participant)
            ]);
        }

        $this->nodeId['groupcreate'] = $this->createIqId();

        $createNode = new ProtocolNode('create', [
            'subject' => $subject
        ], $participantNode);

        $iqNode = new ProtocolNode('iq', [
            'xmlns' => 'w:g2',
            'id' => $this->nodeId['groupcreate'],
            'type' => 'set',
            'to' => WHATSAPP_GROUP_SERVER
        ], [$createNode], null);

        $this->sendNode($iqNode);
        $this->waitForServer($this->nodeId['groupcreate']);
        $groupId = $this->groupId;

        $this->eventsManager()->fire('onGroupCreate', [
            $this->phoneNumber,
            $groupId
        ]);

        return $groupId;
    }

    /**
     * Change group's subject.
     *
     * @param string $gjid The group id
     * @param string $subject The subject
     */
    public function sendSetGroupSubject($gjid, $subject)
    {
        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'type' => 'set',
            'to' => $this->getJID($gjid),
            'xmlns' => 'w:g2'
        ], [new ProtocolNode('subject', null, null, $subject)]);

        $this->sendNode($node);
    }

    /**
     * Leave a group chat.
     *
     * @param mixed $gjids Group or group's ID(s)
     */
    public function sendGroupsLeave($gjids)
    {
        $this->nodeId['leavegroup'] = $this->createIqId();

        if (!is_array($gjids)) {
            $gjids = [$this->getJID($gjids)];
        }

        $nodes = [];
        foreach ($gjids as $gjid) {
            $nodes[] = new ProtocolNode('group', [
                'id' => $this->getJID($gjid)
            ]);
        }

        $leave = new ProtocolNode('leave', [
            'action' => 'delete'
        ], $nodes);

        $node = new ProtocolNode('iq', [
            'id' => $this->nodeId['leavegroup'],
            'to' => WHATSAPP_GROUP_SERVER,
            'type' => 'set',
            'xmlns' => 'w:g2'
        ], [$leave]);

        $this->sendNode($node);
        $this->waitForServer($this->nodeId['leavegroup']);
    }

    /**
     * Add participant(s) to a group.
     *
     * @param string $groupId The group ID.
     * @param mixed $participants An array with the participants numbers to add
     */
    public function sendGroupsParticipantsAdd($groupId, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }
        $this->sendGroupsChangeParticipants($groupId, $participants, 'add', $this->createMsgId());
    }

    /**
     * Change participants of a group.
     *
     * @param string $groupId The group ID.
     * @param array $participants An array with the participants.
     * @param string $tag The tag action. 'add' or 'remove'
     * @param        $id
     */
    protected function sendGroupsChangeParticipants($groupId, $participants, $tag, $id)
    {
        $_participants = [];
        foreach ($participants as $participant) {
            $_participants[] = new ProtocolNode('participant', ['jid' => $this->getJID($participant)]);
        }

        $childHash = [];
        $child = new ProtocolNode($tag, $childHash, $_participants);
        $node = new ProtocolNode('iq', [
            'id' => $id,
            'type' => 'set',
            'xmlns' => 'w:g2',
            'to' => $this->getJID($groupId)
        ], [$child]);

        $this->sendNode($node);
        $this->waitForServer($id);
    }

    /**
     * Remove participant(s) from a group.
     *
     * @param string $groupId The group ID.
     * @param mixed $participants An array with the participants numbers to remove
     */
    public function sendGroupsParticipantsRemove($groupId, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }
        $this->sendGroupsChangeParticipants($groupId, $participants, 'remove', $this->createMsgId());
    }

    /**
     * Promote participant(s) of a group; Make a participant an admin of a group.
     *
     * @param string $gId The group ID.
     * @param mixed $participants An array with the participants numbers to promote
     */
    public function sendPromoteParticipants($gId, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }
        $this->sendGroupsChangeParticipants($gId, $participants, 'promote', $this->createMsgId());
    }

    /**
     * Demote participant(s) of a group; remove participant of being admin of a group.
     *
     * @param string $gId The group ID.
     * @param array $participants An array with the participants numbers to demote
     */
    public function sendDemoteParticipants($gId, $participants)
    {
        if (!is_array($participants)) {
            $participants = [$participants];
        }
        $this->sendGroupsChangeParticipants($gId, $participants, 'demote', $this->createMsgId());
    }

    /**
     * Lock group: participants cant change group subject or profile picture except admin.
     *
     * @param string $gId The group ID.
     */
    public function sendLockGroup($gId)
    {
        $msgId = $this->createIqId();
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'w:g2',
            'type' => 'set',
            'to' => $this->getJID($gId)
        ], [new ProtocolNode('locked')]);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Unlock group: Any participant can change group subject or profile picture.
     *
     *
     * @param string $gId The group ID.
     */
    public function sendUnlockGroup($gId)
    {
        $msgId = $this->createIqId();
        $node = new ProtocolNode('iq', [
            'id' => $msgId,
            'xmlns' => 'w:g2',
            'type' => 'set',
            'to' => $this->getJID($gId)
        ], [new ProtocolNode('unlocked')]);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a text message to the user/group.
     *
     * @param string $to The recipient.
     * @param string $txt The text message.
     * @param string|null $id
     *
     * @return string     Message ID.
     */
    public function sendMessage($to, $txt, $id = null)
    {
        $id = $this->sendMessageNode($to, new ProtocolNode('body', null, null, $txt), $id);
        $this->waitForServer($id);

        if ($this->messageStore !== null) {
            $this->messageStore->saveMessage($this->phoneNumber, $to, $txt, $id, time());
        }

        return $id;
    }

    /**
     * Send a read receipt to a message.
     *
     * @param string $to The recipient.
     * @param string $id
     */
    public function sendMessageRead($to, $id)
    {
        $this->sendNode(new ProtocolNode('receipt', [
            'type' => 'read',
            'to' => $to,
            'id' => $id
        ]));
    }

    /**
     * Send the composing message status. When typing a message.
     *
     * @param string $to The recipient to send status to.
     */
    public function sendMessageComposing($to)
    {
        $this->sendChatState($to, 'composing');
    }

    /**
     * @param $to
     * @param $state
     */
    protected function sendChatState($to, $state)
    {
        $node = new ProtocolNode('chatstate', [
            'to' => $this->getJID($to)
        ], [new ProtocolNode($state)]);

        $this->sendNode($node);
    }

    /**
     * Send the 'paused composing message' status.
     *
     * @param string $to The recipient number or ID.
     */
    public function sendMessagePaused($to)
    {
        $this->sendChatState($to, 'paused');
    }

    /**
     * Send the offline status. User will show up as "Offline".
     */
    public function sendOfflineStatus()
    {
        $messageNode = new ProtocolNode('presence', ['type' => 'inactive']);
        $this->sendNode($messageNode);
    }

    /**
     * Send presence status.
     *
     * @param string $type The presence status.
     */
    public function sendPresence($type = 'active')
    {
        $this->sendNode(new ProtocolNode('presence', [
            'type' => $type
        ]));

        $this->eventsManager()->fire('onSendPresence', [
            $this->phoneNumber,
            $type,
            $this->name
        ]);
    }

    /**
     * Send presence subscription, automatically receive presence updates as long as the socket is open.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceSubscription($to)
    {
        $this->sendNode(new ProtocolNode('presence', ['type' => 'subscribe', 'to' => $this->getJID($to)]));
    }

    /**
     * Unsubscribe, will stop subscription.
     *
     * @param string $to Phone number.
     */
    public function sendPresenceUnsubscription($to)
    {
        $this->sendNode(new ProtocolNode('presence', ['type' => 'unsubscribe', 'to' => $this->getJID($to)]));
    }

    /**
     * Set the picture for the group.
     *
     * @param string $gjid The groupID
     * @param string $path The URL/URI of the image to use
     */
    public function sendSetGroupPicture($gjid, $path)
    {
        $this->sendSetPicture($gjid, $path);
    }

    /**
     * Set your profile picture
     *
     * @param string $jid
     * @param string $filepath URL or localpath to image file
     */
    protected function sendSetPicture($jid, $filepath)
    {
        $nodeID = $this->createIqId();

        $data = preprocessProfilePicture($filepath);
        $preview = createIconGD($filepath, 96, true);

        $picture = new ProtocolNode('picture', ['type' => 'image'], null, $data);
        $preview = new ProtocolNode('picture', ['type' => 'preview'], null, $preview);

        $node = new ProtocolNode('iq', [
            'id' => $nodeID,
            'to' => $this->getJID($jid),
            'type' => 'set',
            'xmlns' => 'w:profile:picture'
        ], [$picture, $preview]);

        $this->sendNode($node);
        $this->waitForServer($nodeID);
    }

    /**
     * Set the list of numbers you wish to block receiving from.
     *
     * @param mixed $blockedJids One or more numbers to block messages from.
     */
    public function sendSetPrivacyBlockedList($blockedJids = [])
    {
        if (!is_array($blockedJids)) {
            $blockedJids = [$blockedJids];
        }

        $items = [];
        foreach ($blockedJids as $index => $jid) {
            $items[] = new ProtocolNode('item', [
                'type' => 'jid',
                'value' => $this->getJID($jid),
                'action' => 'deny',
                'order' => $index + 1//WhatsApp stream crashes on zero index
            ]);
        }

        $child = new ProtocolNode('list', [
            'name' => 'default'
        ], $items);

        $node = new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'xmlns' => 'jabber:iq:privacy',
            'type' => 'set'
        ], [new ProtocolNode('query', null, [$child])]);

        $this->sendNode($node);
    }

    /**
     * Set your profile picture.
     *
     * @param string $path URL/URI of image
     */
    public function sendSetProfilePicture($path)
    {
        $this->sendSetPicture($this->phoneNumber, $path);
    }

    /**
     * Removes the profile photo.
     */
    public function sendRemoveProfilePicture()
    {
        $picture = new ProtocolNode('picture');
        $thumb = new ProtocolNode('picture', ['type' => 'preview']);

        $this->sendNode(new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'to' => $this->getJID($this->phoneNumber),
            'type' => 'set',
            'xmlns' => 'w:profile:picture'
        ], [$picture, $thumb]));
    }

    /**
     * Set the recovery token for your account to allow you to retrieve your password at a later stage.
     *
     * @param  string $token A user generated token.
     */
    public function sendSetRecoveryToken($token)
    {
        $child = new ProtocolNode('pin', [
            'xmlns' => 'w:ch:p'
        ], null, $token);

        $this->sendNode(new ProtocolNode('iq', [
            'id' => $this->createIqId(),
            'type' => 'set',
            'to' => WHATSAPP_SERVER
        ], [$child]));
    }

    /**
     * Update the user status.
     *
     * @param string $txt The text of the message status to send.
     */
    public function sendStatusUpdate($txt)
    {
        $node = new ProtocolNode('iq', [
            'to' => WHATSAPP_SERVER,
            'type' => 'set',
            'id' => $this->createIqId(),
            'xmlns' => 'status'
        ], [new ProtocolNode('status', null, null, $txt)]);

        $this->sendNode($node);
        $this->eventsManager()->fire('onSendStatusUpdate', [
            $this->phoneNumber,
            $txt
        ]);
    }

    /**
     * Send a vCard to the user/group.
     *
     * @param string $to The recipient to send.
     * @param string $name The contact name.
     * @param object $vCard The contact vCard to send.
     * @return string       Message ID
     */
    public function sendVcard($to, $name, $vCard)
    {
        $vCardNode = new ProtocolNode('vcard', [
            'name' => $name
        ], null, $vCard);

        $mediaNode = new ProtocolNode('media', [
            'type' => 'vcard'
        ], [$vCardNode]);

        // Return message ID. Make pull request for this.
        return $this->sendMessageNode($to, $mediaNode);
    }

    /**
     * Send a vCard to the user/group as Broadcast.
     *
     * @param array $targets An array of recipients to send to.
     * @param string $name The vCard contact name.
     * @param object $vCard The contact vCard to send.
     * @return string         Message ID
     */
    public function sendBroadcastVcard($targets, $name, $vCard)
    {
        $vCardNode = new ProtocolNode('vcard', [
            'name' => $name
        ], null, $vCard);

        $mediaNode = new ProtocolNode('media', [
            'type' => 'vcard'
        ], [$vCardNode]);

        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $mediaNode, 'media');
    }

    /**
     * Rejects a call
     *
     * @param array $to Phone number.
     * @param string $id The main node id
     * @param string $callId The call-id
     */
    public function rejectCall($to, $id, $callId)
    {
        $rejectNode = new ProtocolNode('reject', [
            'call-id' => $callId
        ]);

        $this->sendNode(new ProtocolNode('call', [
            'id' => $id,
            'to' => $this->getJID($to)
        ], [$rejectNode]));
    }

    /**
     * Sets the bind of the new message.
     *
     * @param $bind
     */
    public function setNewMessageBind($bind)
    {
        $this->newMsgBind = $bind;
    }

    /**
     * @param array $numbers
     * @param array $deletedNumbers
     * @param int $syncType
     * @param int $index
     * @param bool $last
     * @return string
     */
    public function sendSync(array $numbers, array $deletedNumbers = null, $syncType = 4, $index = 0, $last = true)
    {
        $users = [];

        for ($i = 0; $i < count($numbers); $i++) { // number must start with '+' if international contact
            $users[$i] = new ProtocolNode('user', null, null,
                (substr($numbers[$i], 0, 1) != '+') ? ('+' . $numbers[$i]) : ($numbers[$i]));
        }

        if ($deletedNumbers != null || count($deletedNumbers)) {
            for ($j = 0; $j < count($deletedNumbers); $j++, $i++) {
                $users[$i] = new ProtocolNode('user',
                    ['jid' => $this->getJID($deletedNumbers[$j]), 'type' => 'delete']);
            }
        }

        switch ($syncType) {
            case 0:
                $mode = 'full';
                $context = 'registration';
                break;
            case 1:
                $mode = 'full';
                $context = 'interactive';
                break;
            case 2:
                $mode = 'full';
                $context = 'background';
                break;
            case 3:
                $mode = 'delta';
                $context = 'interactive';
                break;
            case 4:
                $mode = 'delta';
                $context = 'background';
                break;
            case 5:
                $mode = 'query';
                $context = 'interactive';
                break;
            case 6:
                $mode = 'chunked';
                $context = 'registration';
                break;
            case 7:
                $mode = 'chunked';
                $context = 'interactive';
                break;
            case 8:
                $mode = 'chunked';
                $context = 'background';
                break;
            default:
                $mode = 'delta';
                $context = 'background';
        }

        $id = $this->createIqId();

        $node = new ProtocolNode('iq', [
            'id' => $id,
            'xmlns' => 'urn:xmpp:whatsapp:sync',
            'type' => 'get'
        ], [
            new ProtocolNode('sync', [
                'mode' => $mode,
                'context' => $context,
                'sid' => '' . ((time() + 11644477200) * 10000000),
                'index' => '' . $index,
                'last' => $last ? 'true' : 'false'
            ], $users)
        ]);

        $this->sendNode($node);
        $this->waitForServer($id);

        return $id;
    }

    /**
     * @param MessageStoreInterface $messageStore
     */
    public function setMessageStore(MessageStoreInterface $messageStore)
    {
        $this->messageStore = $messageStore;
    }

    /**
     * Process and save media image.
     *
     * @param ProtocolNode $node ProtocolNode containing media
     */
    protected function processMediaImage($node)
    {
        $media = $node->getChild('media');

        if ($media != null) {
            $filename = $media->getAttribute('file');
            $url = $media->getAttribute('url');
            $dir = DATA_FOLDER . DS . MEDIA_FOLDER . DS;

            //save thumbnail
            file_put_contents($dir . 'thumb_' . $filename, $media->getData());
            //download and save original
            file_put_contents($dir . $filename, file_get_contents($url));
        }
    }

    /**
     * Processes received picture node.
     *
     * @param ProtocolNode $node ProtocolNode containing the picture
     */
    protected function processProfilePicture($node)
    {
        $pictureNode = $node->getChild('picture');

        if ($pictureNode != null) {
            if ($pictureNode->getAttribute('type') == 'preview') {
                $filename = DATA_FOLDER . DS . PICTURES_FOLDER . DS . 'preview_' . $node->getAttribute('from') . 'jpg';
            } else {
                $filename = DATA_FOLDER . DS . PICTURES_FOLDER . DS . $node->getAttribute('from') . '.jpg';
            }

            file_put_contents($filename, $pictureNode->getData());
        }
    }
}