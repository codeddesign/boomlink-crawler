<?php

class DataStandards
{
    /**
     * @param $url
     * @return mixed
     */
    public static function getDomain($url)
    {
        $parts = parse_url($url);
        return str_ireplace('www.', '', $parts['domain']);
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
    public static function getDefaultLinksInfo() {
        return '';
    }
}