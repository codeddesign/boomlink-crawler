<?php

class Standards
{
    public static $default = 'n/a', $oneSecondsMls;
    CONST DEBUG = true, DO_EXIT = true;

    /**
     * @param $link
     * @return bool
     */
    public static function linkHasScheme($link)
    {
        return (strtolower(substr($link, 0, 4)) === 'http');
    }

    /**
     * @param $url
     * @return mixed
     */
    public static function getHost($url)
    {
        $url = trim($url);

        // parse_url() won't work properly if 'http' is missing:
        if (!self::linkHasScheme($url)) {
            $url = 'http://' . $url;
        }

        $parts = parse_url($url);
        if (array_key_exists("host", $parts)) {
            return str_ireplace("www.", "", $parts["host"]);
        }

        // this should never happen:
        return false;
    }

    /**
     * @param $host
     * @return string
     */
    public static function getIPByHost($host)
    {
        return gethostbyname($host);
    }

    /**
     * @param $host
     * @return string;
     */
    public static function getTLD($host)
    {
        $parts = explode(".", $host);

        return $parts[count($parts) - 1];
    }

    /**
     * @param $url
     * @return string
     */
    public static function getCleanURL($url)
    {
        $url = trim($url);
        if (stripos($url, '#') !== false) {
            $url = substr($url, 0, stripos('#', $url));
        }

        return $url;
    }

    /**
     * @return string
     */
    public static function getDefaultLinksInfo()
    {
        return self::$default;
    }

    /**
     * @param $ip
     * @return array
     */
    public static function getDefaultNetworkRecord()
    {
        $d = self::$default;

        return array(
            'server_ip' => $d,
            'server_location' => $d,
            'registration_date' => $d,
            'hosting_company' => $d,
        );
    }

    /**
     * @param $string
     * @return string
     */
    public static function getCleanDate($string)
    {
        $string = trim($string);
        if (preg_match('/([\d]T[\d])/', $string, $matched) !== false) {
            if (isset($matched[1][0])) {
                $string = str_replace($matched[1][0] . 'T', $matched[1][0] . ' ', $string);
                $parts = explode(' ', $string);

                return $parts[0];
            }
        }

        return $string;
    }

    /**
     * @param $text
     * @return string
     */
    public static function getBodyText($text)
    {
        /*return utf8_decode(trim($text))*/;

        return trim($text);
    }

    /**
     * @param array $array
     * @return object
     */
    public static function arrayToObject(array $array = array())
    {
        return (object)$array;
    }

    /**
     * @param $link
     * @return bool
     */
    public static function linkIsOK($link)
    {
        $link = trim($link);
        if (strlen($link) == 0) {
            return false;
        }

        $avoidLinks = array(
            'javascript:',

        );

        foreach ($avoidLinks as $a_no => $avoid) {
            if (stripos($link, $avoid) !== false) {
                return false;
            }
        }

        if ($link == '/') {
            return false;
        }

        return true;
    }


    /**
     * @param array $link
     * @param $part
     * @return string
     */
    public static function makeAbsoluteLink(array $link = array('main' => '', 'parsed' => ''), $part)
    {
        $out = $part;
        if (!self::linkHasScheme($part)) {
            if ($part[0] == '/') {
                $out = $link['main'] . $part;
            } else {
                if ($link['parsed'][strlen($link['parsed']) - 1] == '/') {
                    $middle = '';
                } else {
                    $middle = '/';
                }
                $out = $link['parsed'] . $middle . $part;
            }
        }

        return $out;
    }

    /**
     * @param $parsedLink
     * @return string
     */
    public static function getMainURL($parsedLink)
    {
        $host = self::getHost($parsedLink);

        return substr($parsedLink, 0, (strpos($parsedLink, $host) + strlen($host)));
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isFollowable($value)
    {
        $doNotTrack = array(
            'nofollow',
            'noindex',
            'no-index',
            'no-follow',
        );

        foreach ($doNotTrack as $d_no => $p) {
            if (stripos($value, $p) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $string
     * @return array|mixed|string
     */
    public static function json_encode_special(array $string)
    {
        $string = json_encode($string, true);
        $string = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($matches) {
                $sym = mb_convert_encoding(
                    pack('H*', $matches[1]),
                    'UTF-8',
                    'UTF-16'
                );

                return $sym;
            },
            $string
        );

        return $string;
    }

    /**
     * @param $robotsFileContent
     * @return array|bool
     */
    public static function getRobotsRules($robotsFileContent)
    {
        if ($robotsFileContent == false) {
            return false;
        }

        // defaults:
        $rules = array();
        $agent = '*';
        $i = 0;

        // parsing:
        $lines = explode("\n", $robotsFileContent);
        foreach ($lines as $l_num => $line) {
            $line = trim($line);

            if (preg_match('#^user-agent: (.*)#i', $line, $matched)) {
                $agent = trim($matched[1]);
            }

            if (preg_match('#^(allow|disallow):(.*)#i', $line, $matched)) {
                // save only the ones which have something set as 'path':
                if (strlen(trim($matched[2])) > 0) {
                    $rules[$agent][$i]['type'] = strtolower(trim($matched[1]));
                    $rules[$agent][$i]['match'] = trim($matched[2]);
                    $i++;
                }
            }
        }

        return $rules;
    }

    /**
     * @param $robotsRules
     * @param $crawlerAgent
     * @param $link
     * @return bool
     */
    public static function respectsRobotsRules($robotsRules, $crawlerAgent, $link)
    {
        if (!is_array($robotsRules)) {
            return true;
        }

        # do tests, by adding some rules:
        //$robotsRules['*'][] = array('type' => 'allow', 'match' => '/service/file.html');
        //$robotsRules['*'][] = array('type' => 'disallow', 'match' => '/service');

        $parsed = parse_url($link);
        if (!isset($parsed['path'])) {
            $path = '/';
        } else {
            $path = $parsed['path'];
        }

        /* $userAgent can be '*' or a specific name user-agent (robot's name, ..) */
        $allowed = true;
        $tempo = 0;
        foreach ($robotsRules as $userAgent => $rules) {
            if ($userAgent == '*' OR $userAgent == $crawlerAgent) {
                foreach ($rules as $r_no => $rule) {
                    # needed because we are doing some checks based on length too.
                    $rule_match = $rule['match'];
                    $rule_match = preg_quote($rule_match);
                    $length = strlen($rule_match);

                    #IMPORTANT: preg_match requires '#' as delimiter!
                    if (preg_match('#^' . $rule_match . '#', $path, $matched)) {
                        if ($tempo < $length) {
                            $tempo = $length;
                            $allowed = ($rule['type'] == 'allow') ? true : false;
                        } elseif ($tempo == $length AND $rule['type'] == 'allow') {
                            $tempo = $length;
                            $allowed = true;
                        }
                    }
                }

            }
        }

        return $allowed;
    }

    /**
     * @param string $service
     * @param int $seconds
     */
    public static function doDelay($service = null, $seconds)
    {
        $seconds = intval($seconds);

        if (!$seconds) {
            $seconds = 1 / 2 * self::$oneSecondsMls;
        }

        $restTime = $seconds * self::$oneSecondsMls;
        if ($service !== null) {
            self::debug($service . ' is sleeping ' . $restTime . ' mls');
        }

        usleep($restTime);
    }

    /**
     * @param $msg
     * @param bool $exit
     */
    public static function debugToFile($msg, $exit = false)
    {
        if (self::DEBUG) {
            ob_start();
            if (is_array($msg)) {
                print_r($msg);
            } else {
                echo $msg;
            }
            echo "\n";

            $content = ob_get_contents();
            ob_end_clean();
            error_log($content);
        }

        self::debug($msg, $exit);
    }

    /**
     * @param $msg
     * @param bool $exit
     */
    public static function debug($msg, $exit = false)
    {
        if (self::DEBUG) {
            if (is_array($msg)) {
                print_r($msg);
            } else {
                echo $msg;
            }
            echo "\n";
        }

        if ($exit) {
            exit(-1);
        }
    }

    /**
     * @param $link
     * @return array
     */
    public static function getHostAndPathOnly($link)
    {
        //get domain:
        $host = self::getHost($link);

        //get rest of the link after domain:
        $rest = substr($link, stripos($link, $host) + strlen($host));

        return array(
            'host' => $host,
            'rest' => $rest,
        );
    }

    /**
     * @param $link
     * @return array
     */
    public static function generatePossibleLinks($link)
    {
        $parts = self::getHostAndPathOnly($link);

        $patterns = array(
            "http://%s%s", "http://www.%s%s", "https://%s%s", "https://www.%s%s",
            "http://%s%s/", "http://www.%s%s/", "https://%s%s/", "https://www.%s%s/",
        );

        $links = array();
        for ($i = 0; $i < count($patterns); $i++) {
            $temp = sprintf($patterns[$i], $parts['host'], $parts['rest']);
            if (strrpos($temp, "//") == strlen($temp) - 2) {
                $temp = substr($temp, 0, strrpos($temp, "//"));
            }

            $links[] = $temp;
        }

        return $links;
    }

    private static function sortByKeyLength($a, $b)
    {
        if (strlen($a) == strlen($b)) {
            return 0;
        }

        if (strlen($a) > strlen($b)) {
            return 1;
        }

        return -1;
    }

    /**
     * @param array $nextLinks
     * @return array
     */
    public static function removePossibleDuplicates(array $nextLinks)
    {
        uksort($nextLinks, array('Standards', 'sortByKeyLength'));
        foreach ($nextLinks as $link => $null) {
            $p_links = Standards::generatePossibleLinks($link);
            foreach ($p_links as $p_no => $p_link) {
                if ($p_link !== $link AND isset($nextLinks[$p_link])) {
                    unset($nextLinks[$link]);
                }
            }
        }

        return $nextLinks;
    }

    /**
     * @param $link
     * @param array $links
     * @return bool
     */
    public static function linkMightExistIn($link, array $links)
    {
        $p_links = Standards::generatePossibleLinks($link);
        foreach ($p_links as $p_no => $p_link) {
            if (isset($links[$p_link])) {
                return true;
            }
        }

        return false;
    }
}