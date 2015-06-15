<?php
require __DIR__ . '/vendor/mgp25/whatsapi/src/whatsprot.class.php';

$username = '998903742706'; // Your number with country code, ie: 34123456789
$nickname = 'Oskar'; // Your nickname, it will appear in push notifications
$token = md5($username);
$debug = true;  // Shows debug log
$password = 'Pgggbg9MlueBhW+J4AQdqZso/jM=';

// Create a instance of WhastPort.
$w = new WhatsProt($username, $token, $nickname, $debug);
$w->connect(); // Connect to WhatsApp network
$w->loginWithPassword($password);

$target = '998915506418'; // The number of the person you are sending the message
$message = 'Hi! :) this is a test message';

$w->sendMessage($target , $message);