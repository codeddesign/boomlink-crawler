<?php

class DataStandards
{
    /**
     * @param $url
     * @return mixed
     */
    public static function getHost($url)
    {
        $url = trim($url);

        // parse url won't work properly if 'http' is missing:
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
            $url = substr($url, 0, strrpos('#', $url));
        }

        return $url;
    }

    /**
     * @return string
     */
    public static function getDefaultLinksInfo()
    {
        return '';
    }

    /**
     * @param $ip
     * @return array
     */
    public static function getDefaultNetworkRecord()
    {
        $d = ''; // OR 'n/a' ?

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
}