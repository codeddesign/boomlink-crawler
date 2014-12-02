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
    private static $runConfessPattern = 'http://localhost/%s/scripts/run_phantom.php?url=%s';

    # generic crawler's progress status for data updates:
    const CURRENT_STATUS = 0, NEW_STATUS = 1;

    /**
     * Time to delay in seconds. We can also accepts fractions.
     *
     * @param $for
     * @return mixed
     */
    public static function getDelay($for)
    {
        $delay = array(
            'wait_for_finish_pause' => 1,
            'api_data_pause' => 1,
            'crawl_project_pause' => 1,
            'phantom_data_wait' => 5,
            'phantom_data_pause' => 5,
            'project_listener_pause' => 10,
            'proxy_data_wait' => 5,
            'proxy_data_pause' => (60 * 30), // 30min
            'curl_multi_exec_pause' => 1
        );

        if (!isset($delay[$for])) {
            Standards::debug('getDelay(): \'' . $for . '\' is not set.', Standards::DO_EXIT);
        }

        return $delay[$for];
    }

    /**
     * Limit of links to get from db that will be processed at once.
     * !! Exception: proxy data which corresponds to 6 different external links (google, bing, facebook, ..)
     *
     * @param $for
     * @return mixed
     */
    public static function getQueryLimit($for)
    {
        $limit = array(
            'proxy_data' => 1,
            'phantom_data' => 5,
            'api_data' => 5,
        );

        if (!isset($limit[$for])) {
            Standards::debug('getQueryLimit(): \'' . $for . '\' is not set.', Standards::DO_EXIT);
        }

        return $limit[$for];
    }

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
            '/var/www/html',
            '/var/www',
            '/helpers',
            '/libraries',
            '/services',
        );

        $path = str_ireplace($replace, '', __DIR__);
        if (isset($path[0]) and $path[0] == '/') {
            $path = substr($path, 1);
        }

        return sprintf(self::$runConfessPattern, $path, $url);
    }

    /**
     * @return array
     */
    public static function getDBConfig()
    {
        return array(
            'host' => '104.131.163.243',
            'db_name' => 'site_analysis',
            'username' => 'root',
            'password' => 'My6Celeb',
        );
    }
} 