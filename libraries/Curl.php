<?php

class Curl
{
    protected $curl_config, $curl_sets, $content, $header, $links, $debug, $link_info;

    /**
     * @param array $opts
     * * available $opts => (BOOL follow, BOOL header, NULL|STRING proxy, INT timeout [seconds], STRING agent)
     */
    function __construct(array $opts = array())
    {
        // sets:
        $this->debug = true;
        $this->links = $this->link_info = NULL;

        if(count($opts) > 0) {
            $this->setCurlOptions($opts);
        }
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
            CURLOPT_CONNECTTIMEOUT => 3, // <- seconds
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36",

            // don't change:
            CURLOPT_HEADER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_FRESH_CONNECT => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
        );

        // add options values if available for change:
        foreach ($this->curl_sets as $s_key => $value) {
            if (array_key_exists($s_key, $options_assoc)) {
                $this->curl_config[constant($options_assoc[$s_key])] = $this->curl_sets[$s_key];
            }
        }

        // to add or not to add the proxy:
        if (isset($this->curl_sets['proxy']) AND ($this->curl_sets['proxy']) !== NULL) {
            list($ip, $port, $username, $password) = explode(":", trim($this->curl_sets["proxy"]));

            $curl_proxy_config = array(
                CURLOPT_PROXYTYPE => "HTTP",
                CURLOPT_PROXY => $ip,
                CURLOPT_PROXYPORT => $port,
                CURLOPT_PROXYUSERPWD => $username . ':' . $password,
            );

            $this->curl_config += $curl_proxy_config;
        }
    }

    /**
     * creates single or multiple sessions:
     */
    function run()
    {
        if ($this->links === NULL) {
            echo('Curl does no have any links set.');
            return;
        }

        if (!is_array($this->links)) {
            $this->runSingle($this->links);
        } else {
            $this->runMultiple($this->links);
        }
    }

    /**
     * @param $url
     * @return resource
     */
    protected function initSingleCurl($url)
    {
        $con = curl_init();
        curl_setopt($con, CURLOPT_URL, DataStandards::getCleanURL($url));
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
        $content = curl_exec($con = $this->initSingleCurl($url));

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

        // run:
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        // remove handles:
        foreach ($con as $u_key => $c) {
            // save some data first:
            $this->setLinkInfo($con[$u_key], $u_key);
            $this->setBodyParts(curl_multi_getcontent($con[$u_key]), $u_key);

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
        $this->content[$key] = trim(substr(utf8_decode($content), $this->link_info[$key]['header_size']));
        $this->header[$key] = ($this->curl_config[CURLOPT_HEADER] === FALSE) ? '' : trim(substr($content, 0, $this->link_info[$key]['header_size']));
    }

    /**
     * @return string|array
     */
    public function getHeaderOnly()
    {
        if (count($this->header) == 1) {
            return $this->header[0];
        } else {
            return $this->header;
        }
    }

    /**
     * @return string|array
     */
    public function getBodyOnly()
    {
        if (count($this->content) == 1) {
            return $this->content[0];
        } else {
            return $this->content;
        }
    }

    /**
     * @return array
     * ^ more precisely it returns array that holds $needed
     */
    public function getLinkInfo()
    {
        $info = array();
        $default = DataStandards::getDefaultLinksInfo();

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
                    if (array_key_exists($temp_key, $needed)) {
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

        if (count($info) == 1) {
            return $info[key($info)];
        }

        return $info;
    }
}