<?php

class Curl
{
    private $_curl_config, $_curl_sets, $_content, $_header, $_links, $_debug;

    /**
     * @param array $opts
     * * available $opts => (BOOL follow, BOOL header, NULL|STRING proxy, INT timeout [seconds], STRING agent)
     */
    function __construct(array $opts = array())
    {
        $this->_debug = false;

        // ..
        $this->_links = NULL;
        $this->setCurlOptions($opts);
    }

    /**
     * @param string|array $links
     */
    public function addLinks($links)
    {
        $this->_links = $links;
    }

    /**
     * @param array $opts
     */
    public function setCurlOptions(array $opts = array())
    {
        $this->_curl_sets = $opts;

        // changeable options
        $options_assoc = array(
            'header' => 'CURLOPT_HEADER',
            'follow' => 'CURLOPT_FOLLOWLOCATION',
            'agent' => 'CURLOPT_USERAGENT',
            'timeout' => 'CURLOPT_CONNECTTIMEOUT',
        );

        // curl options
        $this->_curl_config = array(
            // changeable:
            CURLOPT_HEADER => TRUE,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36",
            CURLOPT_FOLLOWLOCATION => TRUE,

            // main:
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_FRESH_CONNECT => TRUE,
            CURLOPT_CONNECTTIMEOUT => 3, // <- seconds
        );

        // add options values if available for change:
        foreach ($this->_curl_sets as $s_key => $value) {
            if (array_key_exists($s_key, $options_assoc)) {
                $this->_curl_config[constant($options_assoc[$s_key])] = $this->_curl_sets[$s_key];
            }
        }

        // to add or not to add the proxy:
        if (isset($this->_curl_sets['proxy']) AND ($this->_curl_sets['proxy']) !== NULL) {
            list($ip, $port, $username, $password) = explode(":", trim($this->_curl_sets["proxy"]));

            $curl_proxy_config = array(
                CURLOPT_PROXYTYPE => "HTTP",
                CURLOPT_PROXY => $ip,
                CURLOPT_PROXYPORT => $port,
                CURLOPT_PROXYUSERPWD => $username . ':' . $password,
            );

            $this->_curl_config += $curl_proxy_config;
        }
    }

    /**
     * creates single or multiple sessions:
     */
    function run()
    {
        if ($this->_links === NULL) {
            exit('Curl does no have any links set.');
        }

        if (!is_array($this->_links)) {
            $this->runSingle($this->_links);
        } else {
            $this->runMultiple($this->_links);
        }
    }

    /**
     * @param $url
     * @return resource
     */
    private function initSingleCurl($url)
    {
        $con = curl_init();
        curl_setopt($con, CURLOPT_URL, DataStandards::getCleanURL($url));
        curl_setopt_array($con, $this->_curl_config);
        return $con;
    }

    private function debugCurlConnection($con)
    {
        if($this->_debug) {
            echo '<pre>';
            print_r(curl_getinfo($con));
            echo '</pre>';
        }
    }

    /**
     * @param $url
     */
    private function runSingle($url)
    {
        // run ONE:
        $content = curl_exec($con = $this->initSingleCurl($url));
        $this->debugCurlConnection($con);
        curl_close($con);
        $this->setBodyParts($content);
    }

    /**
     * @param array $urls
     */
    private function runMultiple(array $urls)
    {
        $con = array();

        // init multiple:
        $mh = curl_multi_init();;

        // add links
        foreach ($urls as $u_key => $url) {
            $con[$u_key] = $this->initSingleCurl($url);
            curl_multi_add_handle($mh, $con[$u_key]);
        }

        // run MULTIPLE:
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        // close handles:
        foreach ($con as $u_key => $c) {
            $this->setBodyParts(curl_multi_getcontent($con[$u_key]), $u_key);
            $this->debugCurlConnection($con[$u_key]);
            curl_multi_remove_handle($mh, $con[$u_key]);
        }

        curl_multi_close($mh);
    }

    /**
     * @param $content
     * @param int|string $key
     */
    private function setBodyParts($content, $key = 0)
    {
        $this->_content[$key] = utf8_decode($content);
        $this->_header[$key] = '';
    }

    /**
     * @return bool|string
     */
    public function getFinalLocation()
    {
        $header = $this->getHeaderOnly();
        $finalLocation = false;

        $lines = explode("\n", $header);
        foreach ($lines as $l_num => $line) {
            if (stripos($line, 'Location:' !== false) AND preg_match('/Location:(.*?)\n/', $line, $matched)) {
                $finalLocation = trim($matched[1]);
            }
        }

        return $finalLocation;
    }

    /**
     * @return string|array
     */
    public function getHeaderOnly()
    {
        if (count($this->_header) == 1) {
            return $this->_header[0];
        } else {
            return $this->_header;
        }
    }

    /**
     * @return string|array
     */
    public function getBodyOnly()
    {
        if (count($this->_content) == 1) {
            return $this->_content[0];
        } else {
            return $this->_content;
        }
    }
}