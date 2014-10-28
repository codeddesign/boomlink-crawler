<?php

/*
 * #todo:
 * - add special cases for registration date 'changed'|'changed_*' regarding the 'T' that is being contained
 * - do more tests, remove dubbing code
 * */

class WhoIs extends Service
{
    CONST PORT = 43, TIMEOUT = 5, DEBUG = true;
    private $domain, $tld, $ip, $whois_server, $servers, $special_whois, $errNo, $errMsg, $registration_keys, $_default, $_domain_id;

    /**
     * @param $arguments
     */
    public function makeSets(array $arguments = array('url' => '', 'domain_id' => ''))
    {
        // set domain:
        $this->domain = Standards::getHost($arguments['url']);

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
        $this->dataCollected = Standards::getDefaultNetworkRecord();

        // start collecting data:
        $this->dataCollected = array(
            'domain_id' => $arguments['domain_id'],
            'domain' => $this->domain,
        );
    }

    /**
     * starts fetching information:
     */
    public function doWork()
    {
        /* set 'whois' server: */
        $resp = $this->set_whois_server();
        //$this->debug($resp);

        /* set ip: */
        $this->dataCollected['server_ip'] = $this->ip;

        /* get 'created/registration date': */
        $resp = $this->get_info_domain();
        $this->dataCollected['registration_date'] = $this->determineRegistrationDate($resp);
        //$this->debug($resp);

        /* get ip, server location, hosting company: */
        $resp = $this->get_info_network();
        //$this->debug($resp);

        $this->dataCollected['server_location'] = $this->determineCountry($resp);
        $this->dataCollected['hosting_company'] = $this->determineHostingCompany($resp);
    }

    /**
     * @param $info
     * @return string
     */
    private function determineCountry($info)
    {
        $country = isset($info['country']) ? $info['country'] : $this->_default['country'];

        # special handle for Europe:
        if (stripos($country, 'eu') !== false AND stripos($country, '#') !== false) {
            $country = 'EU';
        }

        return $country;
    }

    /**
     * #todo - more research & tests;
     * @param array $info
     * @return mixed
     */
    private function determineHostingCompany(array $info)
    {
        return isset($info['orgname']) ? $info['orgname'] : $info['netname'];
    }

    /**
     * @param $data
     * @return string
     */
    private function determineRegistrationDate($data)
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
    private function set_whois_server()
    {
        $parsed = $this->parse_response($this->socket_request($this->servers['whois'], $this->tld . '.'));

        // get dataCollected data only:
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
    private function get_info_domain()
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
    private function socket_request($server, $query)
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
    private function get_info_network()
    {
        if ($this->ip == $this->domain) {
            $this->debug('Failed to get ip [' . $this->ip . '=?' . $this->domain . ']'/*, static::DO_EXIT*/);
            return array();
        }

        $found = FALSE;
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

        if (!isset($data)) {
            $data = array();
            $this->debug('Failed to get network info from.'/*, static::DO_EXIT*/);
        }

        return $data;
    }

    /**
     * @param $body
     * @param null $key
     * @return array
     */
    private function parse_response($body, $key = NULL)
    {
        $parsed = array();

        if (preg_match_all("#(.*?):(.*?)\n#", $body, $matched)) {
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
