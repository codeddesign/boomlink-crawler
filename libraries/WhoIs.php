<?php
#todo - more questions, tests and adapts
class WhoIs
{
    CONST PORT = 43, TIMEOUT = 3;
    protected $timeout, $errno, $errmsg, $domain, $tld, $ip, $whois_server, $needed;

    function __construct($url)
    {
        // default:
        $this->needed = array();

        // set domain:
        $this->domain = DataStandards::getHost($url);

        // set tld, ip:
        $this->tld = DataStandards::getTLD($this->domain);
        $this->ip = DataStandards::getIPByHost($this->domain);

        // get 'whois' server:
        $this->set_server_info();
        echo 'domain_info -> '.$this->get_domain_info();
        exit;
        // get ip, server location, hosting company
        $this->parse_network_record();
    }

    /**
     * @return array
     */
    function getInfo()
    {
        return $this->needed;
    }

    /**
     * @return string
     */
    function get_domain_info()
    {
        if ($this->whois_server !== NULL) {
            return $this->main_query($this->whois_server, '-T dn '.$this->domain);
        } else {
            return 'No \'whois\' server found.';
        }
    }

    protected function set_server_info()
    {
        // default:
        $this->whois_server = $this->date_changed = NULL;
        $server = 'whois.iana.org';

        $data = $this->main_query($server, $this->tld . '.');
        echo $data;
        if (preg_match("/whois:(.*?)\n/", $data, $matched)) {
            $this->whois_server = trim($matched[1]);
        }

        if (preg_match("/changed:(.*?)\n/", $data, $matched)) {
            $this->needed['date_changed'] = trim($matched[1]);
        }
    }

    /* checking for whois server of the tld - this needs some extra checks before complete usage:
       see: http://ubuntuforums.org/archive/index.php/t-1961277.html
    */

    /* checking for domain on the whois server */
    protected function main_query($server, $extra)
    {
        $message = "";

        $fs = fsockopen($server, self::PORT, $this->errno, $this->errmsg, self::TIMEOUT);
        if ($fs) {
            fputs($fs, $extra . "\r\n");
            while (!feof($fs)) $message .= fgets($fs);
            fclose($fs);
        }

        return $message;
    }

    /**
     *
     */
    protected function parse_network_record()
    {
        $result = $this->get_network_record();
        $new = array(); // containers
        $network_ok = false;

        // default:
        $out = array(
            'server_ip' => $this->ip,
            'server_location' => '',
            'hosting_company' => '',
        );

        foreach ($result as $n_name => $data) {
            //parse the results and create an array:
            if (preg_match_all("/(.*?):(.*?)\n/", $data, $matched)) {
                foreach ($matched[1] as $key_no => $name) {
                    $name = strtolower($name);
                    $name = str_replace("-", "", $name);

                    if (strpos($name, "#") === false and strpos($name, "%") === false) {
                        $value = trim($matched[2][$key_no]);

                        //avoid rewriting key values with the same name; important or not let's do it:
                        if (array_key_exists($n_name, $new) and array_key_exists($name, $new[$n_name])) {
                            $name = $name . "_" . $key_no;
                        }

                        //set it:
                        $new[$n_name][$name] = $value;

                        //here we check the good network information provider:
                        if ($name === "netname") {
                            $temp_value = strtolower($value);
                            if (stripos($temp_value, "ripe") === false and stripos($temp_value, "arin") === false) {
                                $network_ok = $n_name;
                            }
                        }
                    }
                }
            }
        }

        //debugging purposes:
        #print_r($new);
        #echo "good info network: '".$network_ok."'<br/>";

        //based on the good one get what we need:
        if ($network_ok !== false) {
            $good = $new[$network_ok];

            //sets:
            $out = array(
                'server_ip' => $this->ip,
                'server_location' => $good["country"],
                'hosting_company' => array_key_exists("orgname", $good) ? $good["orgname"] : $good["netname"],
            );
        }

        $this->needed += $out;
    }

    /**
     * @return array
     */
    protected function get_network_record()
    {
        $servers = array(
            "ripe" => "whois.ripe.net",
            "arin" => "whois.arin.net",
        );

        $temp_info = array();
        foreach ($servers as $s_name => $server) {
            //check ip format, because in case it fails it returns the domain name:
            if ($this->ip !== $this->domain) {
                $extra = $this->ip;
                $temp_info[$s_name] = $this->main_query($server, $extra);
            }
        }

        return $temp_info;
    }
}
