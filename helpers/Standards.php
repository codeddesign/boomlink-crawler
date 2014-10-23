<?php

class Standards
{
    public static $default = 'n/a';

    /**
     * @param $url
     * @return mixed
     */
    public static function getHost($url)
    {
        $url = trim($url);

        // parse_url() won't work properly if 'http' is missing:
        if (substr($url, 0, 4) !== 'http') {
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

        if (strpos($string, 'T') !== false) {
            $parts = explode('T', $string);
            return $parts[0];
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
     * @param $mainLink
     * @param $link
     * @return string
     */
    public static function addMainLinkTo($mainLink, $link)
    {
        if ($link[0] == '/') {
            return $mainLink . substr($link, 1);
        }

        if (substr($link, 0, 4) !== 'http') {
            return $mainLink . $link;
        }

        return $link;
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
}