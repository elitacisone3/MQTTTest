<?php

namespace Epto\Mqtt;

class Functions
{

    public static function UUID() : string
    {
        $data = openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function getClientId(string $extra = '') : string
    {

        $raw = __FILE__;
        $raw.= "\n";
        $raw.= php_uname('s');
        $raw.= "\n";
        $raw.= php_uname('m');
        $raw.= "\n";
        $raw.= php_uname('n');
        $raw.= "\n";
        $raw.= $extra;

        $sid = crc32($raw);

        $sid^=$sid>>1;
        $sid&=0x7FFFFFFF;
        $sid = base_convert($sid,10,36);
        $sid = strtoupper($sid);

        return str_pad($sid,6,'0',STR_PAD_LEFT);

    }

    public static function getRequestId() : string
    {
        $varPath = Main::getProjectRoot();
        $varPath.= "var/" . self::getClientId();
        $varPath.= ".cnt";

        if (file_exists($varPath)) {
            $cnt = intval(file_get_contents($varPath));
        } else {
            $cnt = 0;
        }

        $cnt++;
        file_put_contents($varPath,$cnt);
        chmod($varPath,0666);
        return str_pad($cnt,7,'0',STR_PAD_LEFT);

    }

}