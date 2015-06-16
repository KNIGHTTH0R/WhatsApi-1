<?php

namespace WhatsApi;

/**
 * Media uploader class
 */
class MediaUploader
{
    /**
     * @param $host
     * @param $POST
     * @param $HEAD
     * @param $filepath
     * @param $mediafile
     * @param $TAIL
     * @return bool|mixed
     */
    protected static function sendData($host, $POST, $HEAD, $filepath, $mediafile, $TAIL)
    {
        $sock = fsockopen('ssl://' . $host, 443);

        fwrite($sock, $POST);
        fwrite($sock, $HEAD);

        //write file data
        $buf = 1024;
        $totalread = 0;
        $fp = fopen($filepath, 'r');
        while ($totalread < $mediafile['filesize']) {
            $buff = fread($fp, $buf);
            fwrite($sock, $buff, $buf);
            $totalread += $buf;
        }
        //echo $TAIL;
        fwrite($sock, $TAIL);
        sleep(1);

        $data = fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        fclose($sock);

        list($header, $body) = preg_split("/\r\r/", $data, 2);

        $json = json_decode($body);
        if (!is_null($json)) {
            return $json;
        }

        return false;
    }

    /**
     * @param $uploadResponseNode
     * @param $messageContainer
     * @param $mediafile
     * @param $selfJID
     * @return bool|mixed
     */
    public static function pushFile($uploadResponseNode, $messageContainer, $mediafile, $selfJID)
    {
        //get vars
        $url = $uploadResponseNode->getChild('media')->getAttribute('url');

        return self::getPostString($messageContainer['filePath'], $url, $mediafile, $messageContainer['to'], $selfJID);
    }

    /**
     * @param $filepath
     * @param $url
     * @param $mediafile
     * @param $to
     * @param $from
     * @return bool|mixed
     */
    protected static function getPostString($filepath, $url, $mediafile, $to, $from)
    {
        $host = parse_url($url, PHP_URL_HOST);

        //filename to md5 digest
        $cryptoname = md5($filepath) . '.' . $mediafile['fileextension'];
        $boundary = 'zzXXzzYYzzXXzzQQ';

        if (is_array($to)) {
            $to = implode(',', $to);
        }

        $hBAOS = '--' . $boundary . "\r\n";
        $hBAOS .= "Content-Disposition: form-data; name=\"to\"\r\n\r\n";
        $hBAOS .= $to . "\r\n";
        $hBAOS .= '--' . $boundary . "\r\n";
        $hBAOS .= "Content-Disposition: form-data; name=\"from\"\r\n\r\n";
        $hBAOS .= $from . "\r\n";
        $hBAOS .= '--' . $boundary . "\r\n";
        $hBAOS .= 'Content-Disposition: form-data; name="file"; filename=\'' . $cryptoname . "\"\r\n";
        $hBAOS .= 'Content-Type: ' . $mediafile['filemimetype'] . "\r\n\r\n";

        $fBAOS = "\r\n--" . $boundary . "--\r\n";

        $contentlength = strlen($hBAOS) + strlen($fBAOS) + $mediafile['filesize'];

        $POST = 'POST ' . $url . "\r\n";
        $POST .= 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n";
        $POST .= 'Host: ' . $host . "\r\n";
        $POST .= 'User-Agent: ' . WHATSAPP_USER_AGENT . "\r\n";
        $POST .= 'Content-Length: ' . $contentlength . "\r\n\r\n";

        return self::sendData($host, $POST, $hBAOS, $filepath, $mediafile, $fBAOS);
    }
}