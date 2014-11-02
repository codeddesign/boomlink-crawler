<?php

/*
 * Important info:
 * - If a new api key is added make sure that the pattern (api link) has the 'key' before the 'url' (as GET parameters)
 * */

class Config
{
    # api settings:
    private static $apiKeys = array(
        'majestic' => array(
            'key' => 'CF2C61AFBE51F1A8B3E0947073C0D3D5',
            'pattern' => 'http://api.majestic.com/api/json?app_api_key=%s&cmd=GetBackLinkData&item=%s&Count=0&datasource=fresh'
        ),
        'uclassify_read' => array(
            'key' => 'OJt25NzkGDPYn2IT0cE9st02SAM',
            'pattern' => 'http://uclassify.com/browse/uClassify/Sentiment/ClassifyUrl?readkey=%s&url=%s&version=1.01'
        ),
    );

    # local link that runs confess through phantomjs
    private static $runConfessPattern = 'http://localhost/%s/scripts/run_confess.php?url=%s';

    # generic crawler's progress status for data updates:
    const CURRENT_STATUS = 0, NEW_STATUS = 1;

    /**
     * @param $keyName
     * @param $url
     * @return string
     */
    public static function getApiLink($keyName, $url)
    {
        if (!isset(self::$apiKeys[$keyName])) {
            Standards::debug('Exit: config key \'' . $keyName . '\' does not exist.', Standards::DO_EXIT);
        }

        $info = self::$apiKeys[$keyName];
        return sprintf($info['pattern'], $info['key'], $url);
    }

    /**
     * @param $url
     * @return string
     */
    public static function getConfessLink($url)
    {
        $replace = array(
            '/8win/', # needed for local dev environment
            '/var/www/html/',
            '/var/www/',
            '/services',
            '/libraries',
        );

        $path = str_ireplace($replace, '', __DIR__);

        return sprintf(self::$runConfessPattern, $path, $url);
    }
} 