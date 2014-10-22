<?php

/*
 * #todo:
 * - add special cases for registration date 'changed'|'changed_*' regarding the 'T' that is being contained
 * - do more tests, remove dubbing code
 * */

class WhoIs
{
    CONST PORT = 43, TIMEOUT = 5;
    protected $timeout, $domain, $tld, $ip, $whois_server, $needed, $servers, $special_whois, $errNo, $errMsg, $registration_keys;
    private $_default;

    function __construct($url)
    {
        // set domain:
        $this->domain = Standards::getHost($url);

        // set tld, ip:
        $this->tld = Standards::getTLD($this->domain);
        $this->ip = Standards::getIPByHost($this->domain);

        // 'who is' servers:
        $this->servers = array(
            'whois' => 'whois.iana.org',
            'network' => array(
                'ripe' => 'whois.ripe.net',
                'arin' => 'whois.arin.net',
            ),
        );

        // special case for whois server
        $this->special_whois = array(
            'whois.denic.de' => '-T dn %s',
        );

        // registration date, keys ordered by possibility:
        $this->registration_keys = array(
            'registered',
            'registered_on',

            'registration',
            'registration_date',
            'domain_registration_date',

            'created',
            'created_on',

            'creation',
            'creation_date',
            'domain_create_date',

            'activated',
            'activated_on',
            'domain_record_activated',

            'activation_date',
        );

        // set default:
        $this->_default = Standards::getDefaultNetworkRecord();
        $this->needed = Standards::getDefaultNetworkRecord();
    }

    /**
     * starts fetching information:
     */
    public function doWork()
    {
        /* set 'whois' server: */
        $resp = $this->set_whois_server();
        print_r($resp);

        /* set ip: */
        $this->needed['server_ip'] = $this->ip;

        /* get 'created/registration date': */
        $resp = $this->get_info_domain();
        $this->needed['registration_date'] = $this->determineRegistrationDate($resp);
        print_r($resp);

        /* get ip, server location, hosting company: */
        $resp = $this->get_info_network();
        //print_r($resp);

        // special handle for Europe:
        $country = isset($resp['country']) ? $resp['country'] : $this->_default['country'];
        if (stripos($country, 'eu') !== false AND stripos($country, '#') !== false) {
            $country = 'EU';
        }

        $this->needed['server_location'] = $country;
        $this->needed['hosting_company'] = isset($resp['orgname']) ? $resp['orgname'] : $resp['netname'];
    }

    /**
     * @return array
     */
    public function getNeededInfo()
    {
        return $this->needed;
    }

    /**
     * @param $data
     * @return string
     */
    protected function determineRegistrationDate($data)
    {
        $r = $this->_default['registration_date'];

        $found = false;
        foreach ($this->registration_keys as $k_no => $key) {
            if (!$found AND isset($data[$key])) {
                $found = true;
                $r = $data[$key];
            }
        }

        return Standards::getCleanDate($r);
    }

    /**
     * returns the rest of the parsed data;
     * @return array
     */
    protected function set_whois_server()
    {
        $parsed = $this->parse_response($this->socket_request($this->servers['whois'], $this->tld . '.'));

        // get needed data only:
        if (isset($parsed['whois'])) {
            $this->whois_server = $parsed['whois'];
        } else {
            $this->whois_server = NULL;
        }

        return $parsed;
    }

    /**
     * @return array
     */
    protected function get_info_domain()
    {
        if ($this->whois_server === NULL) {
            return 'No \'whois\' server found.';
        }

        // prepare query:
        $query = $this->domain;

        // handle special queries:
        if (array_key_exists($this->whois_server, $this->special_whois)) {
            $query = sprintf($this->special_whois[$this->whois_server], $this->domain);
        }

        return $this->parse_response($this->socket_request($this->whois_server, $query));
    }

    /**
     * @param $server
     * @param $query
     * @return string
     */
    protected function socket_request($server, $query)
    {
        $message = "";

        $fs = fsockopen($server, self::PORT, $this->errNo, $this->errMsg, self::TIMEOUT);
        if ($fs) {
            fputs($fs, $query . "\r\n");
            while (!feof($fs)) $message .= fgets($fs);
            fclose($fs);
        }

        return $message;
    }

    /**
     * @return array|string
     */
    protected function get_info_network()
    {
        if ($this->ip == $this->domain) {
            return 'Failed to get ip.';
        }

        $found = FALSE;
        $data = 'Failed to get network info.';
        foreach ($this->servers['network'] as $name => $server) {
            // continue to do requests only if not yet found:
            if (!$found) {
                $body = $this->socket_request($server, $this->ip);
                $parsed = $this->parse_response($body);

                if (isset($parsed['netname'])) {
                    $temp_value = strtolower($parsed['netname']);
                    if (stripos($temp_value, "ripe") === false AND stripos($temp_value, "arin") === false) {
                        $found = true;
                        $data = $parsed;
                    }
                }
            }
        }

        return $data;
    }

    protected function parse_response($body, $key = NULL)
    {
        $parsed = array();

        if (preg_match_all("/(.*?):(.*?)\n/", $body, $matched)) {
            foreach ($matched[1] as $key_no => $name) {
                $name = trim(strtolower($name));
                $name = str_replace("-", "", $name);
                $name = str_replace(" ", "_", $name);

                if (strpos($name, "#") === false and strpos($name, "%") === false) {
                    $value = trim($matched[2][$key_no]);

                    //avoid rewriting key values with the same name; important or not let's do it:
                    if (array_key_exists($name, $parsed)) {
                        $name = $name . "_" . $key_no;
                    }

                    //set it:
                    $parsed[$name] = $value;
                }
            }
        }

        if ($key !== NULL) {
            return array($key => $parsed);
        } else {
            return $parsed;
        }
    }
}
