<?php

namespace Epto\Mqtt;

use Exception;

class UCLConfig
{

    public static function getFromServer(string $host) : ?array
    {

        $data = parse_url($host);
        if (!isset($data['host'])) {
            $data = parse_url("https://$host");
        }

        if (!$data) throw new Exception("Invalid host `$host`");

        $data['path'] = '/API2/UCL/Command/info';
        $url = Util::unparseURL($data);

        $raw = Util::fetchURL($url, 32768);
        if (!$raw) throw new Exception("Cannot download UCL Data from `$url`");

        $json = json_decode($raw,true);
        if (!is_array($json)) throw new Exception("Bad UCL Data from `$url`");

        if (!$json['configured']) throw new Exception("Remote UCL not configured");

        $o = [];
        $o['ip'] = gethostbyname($data['host']);
        $map = [
            'id'            =>  'id',
            'nome'          =>  'name',
            'struttura'     =>  'assetCode',
            'idStruttura'   =>  'structureId',
            'userId'        =>  'userId'
        ];

        foreach ($map as $k => $v) {

            if (!isset($json[$v])) throw new Exception("Missing UCL data `$v`");
            $o[$k] = $json[$v];

        }

        return $o;
    }

    public static function save(array $json) : void
    {

        $file = self::getUCLConfigFile();
        $raw = json_encode($json,JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

        if (file_put_contents($file,$raw) === false) {
            throw new Exception("Cannot save UCL data to `$file`");
        }

    }

    public static function getUCLConfigFile() : string
    {
        $file = Main::getProjectRoot();
        $file.= "/var/envConfig.json";
        return $file;
    }

    public static function load() : ?array
    {
        $file = self::getUCLConfigFile();
        if (!file_exists($file)) return null;

        $raw = file_get_contents($file, length: 65536);
        if ($raw === false) throw new Exception("Cannot load UCL data from `$file`");
        $json = json_decode($raw,true);

        if (!$json) throw new Exception("Bad UCL data from `$file`");

        $tpl = [
            'ip'            =>  '192.168.1.80',
            'id'            =>  '_default',
            'structureId'   =>  '_default',
            'name'          =>  '_default',
            'struttura'     =>  ''
            ]
        ;

        return array_replace($tpl,$json);

    }

}