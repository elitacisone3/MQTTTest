<?php

namespace Epto\Mqtt;

use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RuntimeException;

class Main
{

    private static string $basePath = '';
    private static array $config = [];
    private static $mqtt = null;
    private static MQTTTest|null $test = null;

    public static function getProjectRoot() : string
    {
        return self::$basePath;
    }

    public static function onMessage($topic, $message) : void
    {

        $json = json_decode($message,true);
        if (is_array($json)) $message = print_r($json,true);

        if (
            self::$test and
            !empty(self::$test->expect) and
            self::$test->expect == $topic
        ) {

            if (!empty(self::$test->expectRequestId)) {

                $json = json_decode($message,true);
                if (isset($json['RequestId'])) {
                    $exp = $json['RequestId'] == self::$test->expectRequestId;
                } else {
                    $exp = true;
                }

            } else {

                $exp = true;

            }

            if ($exp) {

                echo "EXPECTED: $topic\n";
                echo "$message\n\n";
                self::$mqtt->interrupt();
                return;

            }

        }

        echo "RECEIVED: $topic\n";
        echo "$message\n\n";

    }

    protected static function getConfig(string $path, mixed $default) : mixed {
        return Util::getByDotPath(self::$config,$path,$default);
    }

    public static function initContext(bool $noArgs = false) : array {

        self::$config =
            parse_ini_file(self::$basePath.'etc/config.conf',true,INI_SCANNER_RAW)
        or throw new RuntimeException("Bad config file");

        $json = UCLConfig::load();
        if (is_array($json)) {
            self::$config['UCL'] = $json;
        }

        $argv = $_SERVER['argv'];
        array_shift($argv);

        if ($noArgs == false and $argv) {

            $tpl = array_shift($argv);

            $par = [];
            $cPar = null;

            foreach ($argv as $item) {

                if (str_starts_with($item,'-')) {

                    if ($cPar) {
                        if (isset($par[$cPar])) throw new RuntimeException("Parametro gia impostato: $cPar");
                        $par[$cPar] = 1;
                    }

                    $item = ltrim($item,'-');
                    $cPar = $item;

                } else {

                    if ($cPar === null) throw new InvalidArgumentException("Bad parameter: $item");

                    if ($cPar) {

                        if (preg_match('/^\\(\\((?<a>.*)\\)\\)$/',$item,$match)) {
                            $item='${'.$match['a'].'}';
                        }

                        if (isset($par[$cPar])) throw new RuntimeException("Parametro gia impostato: $cPar");

                        $par[$cPar] = $item;
                    }
                    $cPar = null;

                }

            }

            if ($cPar) {
                if (isset($par[$cPar])) throw new RuntimeException("Parametro gia impostato: $cPar");
                $par[$cPar] = 1;
            }

        } else {

            $tpl = null;
            $par = [];

        }

        $cox = [
            'config'    => self::$config,
            'par'       => $par,
            'var'       => self::$config['var'] ?? [],
            'template'  => $tpl !== null ? $tpl : '',
            'topic'     => self::$config['topic'] ?? [],
            'expect'    => '',
            'expectId'  => '',
            'request'   =>  [
                'time'          =>  time(),
                'timeMillis'    =>  time() * 1000,
                'UUID'          =>  Functions::UUID(),
                'id'            =>  Functions::getRequestId()
            ]
        ];

        $clientId = self::getConfig('MQTT.clientId',null);

        if ($clientId) {

            $cox['request']['clientId'] = $clientId;

        } else {

            $cox['request']['clientId'] = 'MQTTTEST'.Functions::getClientId();

        }

        for ($i = 0; $i < 512; $i++) {

            $isChanged = false;
            $cox = self::tplArray($cox,$cox,$isChanged);
            if (!$isChanged) break;

        }

        if ($i == 10) throw new RuntimeException("Too many string expansions");
        self::$config = $cox['config'];

        return $cox;

    }

    public static function main() {

        $dir = realpath(__DIR__);
        $dir = dirname($dir);
        $dir = str_replace('\\','/',$dir);
        $dir = rtrim($dir,'/');

        self::$basePath = "{$dir}/";

        $OPT = getopt('',['help:','list','configure:']);

        if (isset($OPT['configure'])) {

            self::doConfigure($OPT['configure']);

        } elseif (isset($OPT['list'])) {

            self::doList();
            exit();

        } elseif (isset($OPT['help']) and is_string($OPT['help'])) {

            $cox = self::initContext(true);
            self::doHelp($OPT['help'],$cox);
            exit();

        } elseif (in_array('--help',$_SERVER['argv'])) {

            self::doHelp(null,[]);
            exit();

        }

        $cox = self::initContext();

        if ($cox['template']) {

            if (!preg_match('/^[A-Za-z0-9_\\-]+$/',$cox['template'])) {
                throw new InvalidArgumentException("Bad template name: {$cox['template']}");
            }

            $tplFile = self::$basePath . "template/{$cox['template']}.txt";
            if (!file_exists($tplFile)) throw new RuntimeException("File not found: $tplFile");

            self::$test = new MQTTTest($tplFile,$cox);

            if (isset($cox['par']['no-expect'])) {
                self::$test->expect = null;

            } elseif (self::$test->expect) {

                if (
                    isset(self::$test->json['RequestId']) and
                    empty(self::$test->extra['noExpectRequestId'])
                ) {
                    self::$test->expectRequestId = self::$test->json['RequestId'];
                }

                if (!in_array(self::$test->expect,$cox['topic'])) {
                    $cox['topic']['_EXPECT_'] = self::$test->expect;
                }

            }

            if (!in_array(self::$test->topic,$cox['topic'])) {
                $cox['topic']['_TOPIC_'] = self::$test->topic;
            }

            if (isset($cox['par']['dump'])) {

                echo "\nDUMP: clientId = {$cox['request']['clientId']}\n";
                if (self::$test->expect !='') echo "EXPECT: ".self::$test->expect."\n";
                if (self::$test->expectRequestId !='') echo "EXPECT_ID: ".self::$test->expectRequestId."\n";
                echo "\nTOPIC: ".self::$test->topic."\n";
                echo "\n" . self::$test->getPayload()."\n\n";
                exit();
            }

        }

        if (isset($cox['par']['dump'])) {
            echo "No template!\n";
            exit();
        }

        self::$mqtt = new MqttClient(
            self::getConfig('MQTT.host','127.0.0.1')  ,
            intval(self::getConfig('MQTT.port',1883)) ,
            $cox['request']['clientId'])
        ;

        $settings = new ConnectionSettings();

        $x = self::getConfig('MQTT.username',null);
        if ($x !== null) $settings = $settings->setUsername($x);

        $x = self::getConfig('MQTT.password',null);
        if ($x !== null) $settings = $settings->setPassword($x);

        echo "\nCONNECT: {$cox['request']['clientId']}\n";
        self::$mqtt->connect($settings,self::getConfig('MQTT.cleanSession',0) != 0);
        echo "\n";

        $closure = function ($topic, $message, $retained, $matchedWildcards) {
            self::onMessage($topic, $message);
        };

        if (self::$test and self::$test->expect) {

            echo "EXPECT: ".self::$test->expect."\n";
            self::$mqtt->subscribe(self::$test->expect,$closure);

        } else {

            foreach ($cox['topic'] as $item) {
                echo "SUBSCRIBE: $item\n";
                self::$mqtt->subscribe($item,$closure);
            }

        }

        if (self::$test) {

            echo "\nPUBLISH: ";
            echo self::$test->topic;

            $payload = self::$test->getPayload();
            echo "\n$payload\n\n";

            self::$mqtt->publish(
                self::$test->topic,
                $payload,
                self::$test->qos,
                self::$test->retain
            );

            $payload = null;

        }

        echo "\nLOOP\n";
        self::$mqtt->loop(true);

        echo "\nDISCONNECT\n";
        self::$mqtt->disconnect();

    }

    public static function tplString(string $text, array $cox) : string
    {

        $o = '';
        $text = str_replace("\0",' ',$text);
        $text = str_replace('\\$',"\0",$text);
        $tokens = Util::tagParser($text,'${','}');

        foreach ($tokens as $token) {

            if ($token[0]) {

                $cmd = explode('|',$token[1],2);
                $cmd = array_pad($cmd,2,'');
                if ($cmd[0] == '') continue;

                if ($cmd[0][0] == '@') {

                    $method = substr($cmd[0],1);
                    $isDirect = $method[0] == '=';
                    if ($isDirect) $method = substr($method,1);

                    if (method_exists(Functions::class,$method)) {

                        $o.= call_user_func_array(
                            [Functions::class,$method],
                            [
                                $isDirect ?
                                    $cmd[1] :
                                    Util::getByDotPath($cox,$cmd[1],'')
                                ,
                                $cox
                            ]
                        );

                    } else {

                        throw new InvalidArgumentException("Unknown function: @{$method}");

                    }

                } else {

                    if ($cmd[1] != '' and $cmd[1][0] == '|') {
                        $cmd[1] = self::tplString('${'.substr($cmd[1],1).'}',$cox);
                    }

                    $x = Util::getByDotPath($cox,$cmd[0],$cmd[1]);
                    if ($x == '' and $cmd[1] !='') $x = $cmd[1];

                    $o.= $x;

                }

            } else {

                $o .= $token[1];

            }
        }

        $o = str_replace("\0",'$',$o);

        return $o;

    }

    public static function tplArray(array $json, array $cox, bool &$isChanged = false) : mixed
    {

        foreach ($json as &$value) {

            if (is_array($value)) {

                $value = self::tplArray($value, $cox, $isChanged);

            } elseif (is_string($value)) {

                if (is_string($value) and str_contains($value,'${')) {
                    $value = self::tplString($value,$cox);
                    $isChanged = true;
                    if (is_numeric($value) and $value[0] != '0') $value = floatval($value);
                }

            }

        }

        return $json;
    }

    private static function getOptJSON(string $file) : array {
        if (!file_exists($file)) return [];
        $data = file_get_contents($file);
        if ($data !== false) $data = json_decode($data,true);
        if (!is_array($data)) $data = [];
        return $data;
    }

    private static function doHelp(?string $cap, array $cox) {

        echo "\n";

        if ($cap) {

            if (!preg_match('/^[A-Za-z0-9_\\-]+$/',$cap)) throw new InvalidArgumentException("Bad template: $cap");

            $dictionary = self::getOptJSON(self::$basePath . "template/dictionary.json");
            $globals = self::getOptJSON(self::$basePath."template/globals.json");

            $file = self::$basePath . "template/{$cap}.hlp.txt";
            $tpl = self::$basePath . "template/{$cap}.txt";

            if (file_exists($tpl)) {

                $test = new MQTTTest($tpl,$cox);
                $name = basename($_SERVER['argv'][0]);
                $o = [ $name , $cap ];
                $helpText = [];

                $parMap = $test->parameters;
                $parMap = array_merge($globals,$parMap);

                ksort($parMap);

                foreach ($parMap as $k => $v) {

                    $par = (strlen($k) > 1 ? '--' : '-') . $k;
                    $o[] = '[ ' . $par . ' <valore> ]';

                    if ($v == '') {
                        $v = $dictionary[$k] ?? '(Parametro non documentato)';
                    }

                    $helpText[$par] = $v;
                }

                $len = 0;
                foreach ($o as $ptr => $token) {

                    $nextLen = $len + strlen($token) + 1;

                    if ($nextLen >= 80) {
                        $nextLen = 2;
                        echo "\n ";
                    }

                    if ($ptr) echo ' ';
                    echo $token;
                    $len = $nextLen;
                }

                echo "\n\n";

                if (file_exists($file)) {
                    readfile($file);
                    echo "\n";
                }

                echo "\nParametri:\n\n";

                foreach ($helpText as $k => $v) {
                    echo '  '.Util::fixLen($k,16).' ';
                    $v = wordwrap($v,61,"\n");
                    $v = str_replace("\n","\n".str_repeat(' ',19),$v);
                    echo "$v\n";
                    if (str_contains($v,"\n")) echo "\n";
                }

            }

        } else {

            $file = self::$basePath . "res/help.txt";
            $text = file_get_contents($file);
            $text = str_replace("\r\n","\n",$text);
            $self = basename($_SERVER['argv'][0]);
            $text = str_replace('%%SELF%%',$self,$text);
            echo "$text\n";

        }

        echo "\n";

    }

    private static function doList() : void
    {
        $flt = addcslashes(self::$basePath,'{}?*');
        $flt.= "template/*.txt";

        $list = glob($flt);
        echo "Lista template:\n\n";

        foreach ($list as $item) {
            $item = pathinfo($item,PATHINFO_FILENAME);
            if (str_contains($item,'.')) continue;
            echo "  $item\n";
        }

        echo "\nUsa ";
        echo basename($_SERVER['argv'][0]);
        echo " --help <template>\n";
        echo "per vedere la guida dei singoli template.\n";
        echo "\n";
    }

    private static function doConfigure(string $URL) : void
    {

        $envConfig = UCLConfig::getFromServer($URL);
        if (!$envConfig) {
            echo "Cannot get config\n";
            exit(1);
        }
        UCLConfig::save($envConfig);
        print_r($envConfig);
        exit(0);

    }

}