<?php

namespace Epto\Mqtt;

use RuntimeException;

class Util
{

    public static function tagParser(string $txt, string $Sx='${', string $Dx='}') : array
    {
        $sxl=strlen($Sx);
        $dxl=strlen($Dx);

        $o=array();
        $j = strlen($txt);

        for ($i=0;$i<$j;$i++) {

            $p = strpos($txt,$Sx);

            if ($p!==false) {

                $o[] = array(0,substr($txt,0,$p));

                $txt = substr($txt,$p+$sxl);
                $f = strpos($txt,$Dx);

                if ($f!==false) {

                    $t = substr($txt,0,$f);
                    $txt=substr($txt,$f+$dxl);
                    $o[] = array(1,$t);
                }

            } else break;
        }

        if (strlen($txt)>0) $o[] = array(0,$txt);
        return $o;
    }

    public static function getByDotPath(array $from, string $path, mixed $default) : mixed {

        $path = explode('.',$path);
        $cur = $from;

        foreach ($path as $step) {

            if (isset($cur[$step])) {

                $cur = $cur[$step];

            } else {

                return $default;
            }

        }

        return $cur;

    }

    public static function fixLen(string $str, int $len) : string
    {
        $str = str_pad($str,$len);
        return substr($str,0,$len);
    }

    public static function unparseURL(array $parts): string {
        $url = '';

        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . ':';
        }

        if (isset($parts['host'])) {
            $url .= '//';

            if (!empty($parts['user'])) {
                $url .= $parts['user'];
                if (!empty($parts['pass'])) {
                    $url .= ':' . $parts['pass'];
                }
                $url .= '@';
            }

            $url .= $parts['host'];

            if (!empty($parts['port'])) {
                $url .= ':' . $parts['port'];
            }
        }

        if (!empty($parts['path'])) {
            if (isset($parts['host']) && $parts['path'][0] !== '/') {
                $url .= '/';
            }
            $url .= $parts['path'];
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    public static function fetchURL(string $url, int $maxBytes = 1048576): string|false {
        $ch = curl_init($url);

        $data = '';
        $length = 0;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false, // usiamo callback
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'MQTTTEST',

            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$data, &$length, $maxBytes) {
                $chunkLen = strlen($chunk);
                $length += $chunkLen;

                // blocca se supera il limite
                if ($length > $maxBytes) {
                    return 0; // interrompe il download
                }

                $data .= $chunk;
                return $chunkLen;
            }
        ]);

        $result = curl_exec($ch);

        // errore cURL o limite superato
        if ($result === false || $length > $maxBytes) {
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // controlla HTTP status
        if ($httpCode >= 400) {
            return false;
        }

        return $data;
    }

}