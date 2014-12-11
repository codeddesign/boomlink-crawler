<?php

class Curl
{
    private $curl_config, $curl_sets, $content, $header, $links, $link_info, $parsedCurlInfo;


    /**
     * @param bool $multi
     * @param array $opts
     * * available $opts => (BOOL follow, BOOL header, NULL|STRING proxy, INT timeout [seconds], STRING agent)
     */
    function __construct($multi = true, array $opts = array())
    {
        // sets:
        $this->links = $this->link_info = null;
        $this->parsedCurlInfo = array();

        // needed: apply options
        $this->setCurlOptions($opts);
    }

    /**
     * @param string|array $links
     */
    public function addLinks($links)
    {
        $this->links = $links;
    }

    /**
     * @param array $opts
     */
    public function setCurlOptions(array $opts = array())
    {
        $this->curl_sets = $opts;

        // changeable options
        $options_assoc = array(
            'timeout' => 'CURLOPT_CONNECTTIMEOUT',
            'agent' => 'CURLOPT_USERAGENT',
        );

        // curl options
        $this->curl_config = array(
            // defaults:
            CURLOPT_CONNECTTIMEOUT => 15, // <- seconds
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36",

            // don't change:
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_PROXY => false,
        );

        // proxy handle:
        if (isset($this->curl_sets['proxy'])) {
            $proxy = $this->curl_sets['proxy'];

            $this->curl_config[CURLOPT_PROXYTYPE] = 'HTTP';
            $this->curl_config[CURLOPT_PROXY] = $proxy['ProxyIP'] . ':' . $proxy['ProxyPort'];
        }

        // add options values if available for change:
        foreach ($this->curl_sets as $s_key => $value) {
            if (isset($options_assoc[$s_key])) {
                $this->curl_config[constant($options_assoc[$s_key])] = $this->curl_sets[$s_key];
            }
        }
    }

    /**
     * creates single or multiple sessions:
     */
    function run()
    {
        if ($this->links === null) {
            echo('Curl does no have any links set.');
            return false;
        }

        if (!is_array($this->links)) {
            $backup = $this->links;
            $this->links = array($backup);
        }

        $this->runMultiple($this->links);

        return true;
    }

    /**
     * @param $url
     * @return resource
     */
    protected function initSingleCurl($url)
    {
        $con = curl_init();
        curl_setopt($con, CURLOPT_URL, Standards::getCleanURL($url));
        curl_setopt_array($con, $this->curl_config);

        return $con;
    }

    /**
     * @param resource $con
     * @param int|string $key
     */
    protected function setLinkInfo($con, $key = 0)
    {
        $this->link_info[$key] = curl_getinfo($con);
    }

    /**
     * @param $url
     */
    protected function runSingle($url)
    {
        // run:
        $con = $this->initSingleCurl($url);
        $content = curl_exec($con);

        // save some data first:
        $this->setLinkInfo($con);
        $this->setBodyParts($content);

        // close:
        curl_close($con);
    }

    /**
     * @param array $urls
     */
    protected function runMultiple(array $urls)
    {
        $con = array();

        // init multiple:
        $mh = curl_multi_init();;

        // add links
        foreach ($urls as $u_key => $url) {
            $con[$u_key] = $this->initSingleCurl($url);
            curl_multi_add_handle($mh, $con[$u_key]);
        }

        do {
            $mrc = curl_multi_exec($mh, $active);
            Standards::doDelay(null, Config::getDelay('curl_multi_exec_pause')); // stop wasting CPU cycles and rest for a couple ms
        } while ($mrc == CURLM_CALL_MULTI_PERFORM || $active);

        // save data:
        foreach ($con as $u_key => $c) {
            $this->setLinkInfo($con[$u_key], $u_key);
            $temp_body = curl_multi_getcontent($con[$u_key]);
            $this->setBodyParts($temp_body, $u_key);

            curl_multi_remove_handle($mh, $con[$u_key]);
        }

        // close:
        curl_multi_close($mh);
    }

    /**
     * @param $content
     * @param int|string $key
     */
    protected function setBodyParts($content, $key = 0)
    {
        $this->content[$key] = trim(substr($content, $this->link_info[$key]['header_size']));
        $this->header[$key] = ($this->curl_config[CURLOPT_HEADER] === false) ? '' : trim(substr($content, 0, $this->link_info[$key]['header_size']));
    }

    /**
     * @return string|array
     */
    public function getHeaderOnly()
    {
        return $this->header;
    }

    /**
     * @return string|array
     */
    public function getBodyOnly()
    {
        return $this->content;
    }

    private function parseCurlInfo()
    {
        $info = array();
        $default = Standards::getDefaultLinksInfo();
        $needed = array_flip(array(
            'url',
            'content_type',
            'http_code',
            'primary_ip',
        ));

        // get only needed data:
        if (isset($this->link_info) AND is_array($this->link_info)) {
            foreach ($this->link_info as $key => $arr) {
                foreach ($arr as $temp_key => $temp_value) {
                    if (isset($needed[$temp_key])) {
                        $info[$key][$temp_key] = $temp_value;
                    }
                }
            }
        }

        // just in case: apply default values:
        if (count($info) == 0) {
            foreach ($needed as $n_key => $n_value) {
                $needed[$n_key] = $default;
            }

            foreach ($this->link_info as $key => $arr) {
                $info[$key] = $needed;
            }
        }

        $this->parsedCurlInfo = $info;

        return $info;
    }


    /**
     * @return array
     */
    public function getLinkCurlInfo()
    {
        if (count($this->parsedCurlInfo) == 0) {
            $this->parseCurlInfo();
        }

        return $this->parsedCurlInfo;
    }
}