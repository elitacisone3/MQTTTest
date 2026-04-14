<?php

namespace Epto\Mqtt;

use RuntimeException;

class MQTTTest
{

    public string $topic = '';
    public string|null $expect = null;
    public string $messageId = '';
    public array $parameters = [];
    public array $extra = [];
    public array $json = [];
    public array $context = [];

    public bool $retain = false;
    public int $qos = 0;
    public string $expectRequestId = '';

    public function getPayload() : string
    {
        return json_encode($this->json,JSON_PRETTY_PRINT);
    }

    function __construct(string $file, array $cox) {

        $text = file_get_contents($file) or throw new RuntimeException("Template error `$file`");
        $text = str_replace("\xef\xbb\xbf",'',$text);
        $text = str_replace(["\r\n","\r"],"\n", $text);
        $lines = explode("\n",$text);

        $status = 0;
        $text = '';

        foreach ($lines as $ptr => $line) {

            $ptr++;
            $line = trim($line);

            if ($line == '') {
                if ($status == 0) {
                    if (!isset($this->topic)) throw new RuntimeException("Missing topic in `$file` @ $ptr");
                    $status = 1;
                }
                continue;
            }

            if ($status == 0) {

                if (preg_match('/^(?<key>[A-Za-z0-9]+):\\s*(?<value>.+)$/',$line,$match)) {

                    $match['value'] = trim($match['value']);

                    switch ($match['key']) {

                        case 'expect':
                            $this->expect = $match['value'];
                            break;

                        case 'retain':
                            $this->retain = intval($match['value']) !=0;
                            break;

                        case 'quos':
                            $this->qos = intval($match['value']);
                            break;

                        case 'topic':
                            $this->topic = $match['value'];
                            break;

                        default:
                            $this->extra[$match['key']] = $match['value'];
                    }

                    continue;
                }

                if ($line[0] == '[' or $line[0] == '{') throw new RuntimeException("Syntax error in: `$file` @ $ptr");
                $this->topic = $line;
                $status = 1;

            } else {

                if (
                    preg_match(
                        '/\\$\\{par\\.(?<key>[A-Za-z0-9_\\-]+)[|}]..+\\s+\\/\\/\\s*(?<text>.+)\\s*$/',
                        $line,
                        $match)
                ) {

                    $this->parameters[$match['key']] = trim($match['text']);

                } elseif (
                    preg_match(
                        '/\\$\\{par\\.(?<key>[A-Za-z0-9_\\-]+)[|}]./',
                        $line,
                        $match)
                ) {
                    $this->parameters[$match['key']] = '';
                }

                $line = preg_replace('/\\s+\\/\\/\\s+.+\\s*$/','',$line);

                $text .= "$line\n";

            }
        }

        if (!isset($this->topic)) throw new RuntimeException("Missing topic in `$file`");

        $this->topic = Main::tplString($this->topic,$cox);
        if ($this->expect) $this->expect = Main::tplString($this->expect,$cox);

        $json = json_decode($text,true);
        if (!is_array($json)) throw new RuntimeException("Cannot parse JSON in `$file`");
        $text = '';

        $this->json = Main::tplArray($json,$cox);
        $this->context = $cox;
    }

}