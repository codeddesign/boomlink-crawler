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
        $parts = parse_url($url);

        if (array_key_exists("host", $parts)) {
            return str_ireplace("www.", "", $parts["host"]);
        } else {
            return str_replace(array('/', '#'), '', $url);
        }
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
}