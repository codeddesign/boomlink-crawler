<?php

class WhoIs
{
    CONST PORT = 43, TIMEOUT = 3;
    public $timeout, $errno, $errmsg, $domain, $tld, $ip;

    function __construct($url)
    {
        $info = parse_url($url);

        //sets:
        if (array_key_exists("host", $info)) {
            $domain_temp = $info["host"];
            $domain_temp = str_ireplace("www.", "", $domain_temp);
            $this->domain = $domain_temp;
        } else {
            $this->domain = $url;
        }

        $parts = explode(".", $this->domain);
        $this->tld = $parts[count($parts) - 1];

        $this->ip = gethostbyname($this->domain);
    }

    function get_domain_info()
    {
        $server = $this->get_whois_server();
        if (strlen($server) > 0) {
            $data = $this->main_query($server, $this->domain);
            echo $data;
        }
    }

    /* checking for whois server of the tld - this needs some extra checks before complete usage:
       see: http://ubuntuforums.org/archive/index.php/t-1961277.html
    */

    function get_whois_server()
    {
        $server = "whois.iana.org";

        $result = "";
        $data = $this->main_query($server, $this->tld . ".");
        if (preg_match("/whois: (.*?)\n/", $data, $matched)) {
            $result = trim($matched[1]);
        }

        return $result;
    }

    /* checking for domain on the whois server */

    function main_query($server, $extra)
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

    /* host information based on domain ip */

    function get_parsed_network_record()
    {
        $result = $this->get_network_record();
        $new = $out = array(); // containers
        $network_ok = false;

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
            $out["server_ip"] = $this->ip;
            $out["server_location"] = $good["country"];
            if (array_key_exists("orgname", $good)) {
                $out["hosting_company"] = $good["orgname"];
            } else {
                $out["hosting_company"] = $good["netname"];
            }
        } else {
            #echo "something went wrong..";
        }

        return $out;
    }

    function get_network_record()
    {
        $servers = array(
            "ripe" => "whois.ripe.net",
            "arin" => "whois.arin.net",
        );

        $temp_info = array();
        foreach ($servers as $s_name => $server) {
            //check ip format, because in case it fails it returns the domain name:
            if ($this->ip != $this->domain) {
                $extra = $this->ip;
                $temp_info[$s_name] = $this->main_query($server, $extra);
            }
        }

        return $temp_info;
    }
}

//tests:
/*$url = $_GET["url"];
$w = new FsoWhois($url);
print_r($w->get_parsed_network_record());*/