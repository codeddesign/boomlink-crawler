<?php

/* Description:
 * - ACTS LIKE A SINGLE PARALLEL SERVICE;
 * - Even though all data is actually external data, this class (more precisely) gets data that requires PROXY in order to get it;
 * - It also takes care of the proxy logic;
 * */

class ProxyData extends Service implements ServiceInterface
{
    private $dbo, $external_links, $proxies, $link_id;
    private $userAgentName;
    private $responses;

    public function doSets(array $arguments = array())
    {
        $this->userAgentName = '';

        $this->dbo = new MySQL();
    }

    /**
     * @return bool|array
     */
    private function getNonParsedLinks()
    {
        $pattern = 'SELECT * FROM page_main_info WHERE proxy_data_status=%d LIMIT %d';
        $q = sprintf($pattern, Config::CURRENT_STATUS, Config::getQueryLimit('proxy_data'));
        return $this->dbo->getResults($q);
    }

    /**
     * @return bool|array
     */
    private function getProxies()
    {
        $q = 'SELECT * FROM proxies_list';
        $proxies = $this->dbo->getResults($q);

        for ($i = 0; $i <= 5; $i++) {
            shuffle($proxies);
        }

        return $proxies;
    }

    private function dataSave()
    {
        if (count($this->dataCollected) == 0) {
            return false;
        }

        $sets = array();
        foreach ($this->dataCollected as $key => $value) {
            $sets[] = $key . '=\'' . trim($value) . '\'';
        }

        if (count($sets) == 0) {
            return false;
        }

        $pattern = 'UPDATE page_main_info SET %s WHERE id=%d';
        $q = sprintf($pattern, implode(', ', $sets), $this->link_id);
        return $this->dbo->runQuery($q);
    }

    private function updateStatus()
    {
        /*if (count($this->link_ids) == 0) {
            return false;
        }*/

        $pattern = 'UPDATE page_main_info SET proxy_data_status=%d WHERE id IN (%s)';
        $q = sprintf($pattern, Config::NEW_STATUS, $this->link_id);
        return $this->dbo->runQuery($q);
    }

    /**
     * Runs curl:
     */
    public function doWork()
    {
        /*# todo: remove [tests]
        sleep(10);
        echo 'proxy data - service now should exit!'."\n";
        exit(9);*/

        # get proxies
        $this->proxies = $this->getProxies();
        $useProxy = array();

        # loop:
        $RUN = true;
        $i = 0;
        while ($RUN) {
            $un_parsed = $this->getNonParsedLinks();
            if (!$un_parsed) {
                #$RUN = false;
                Standards::doDelay($this->serviceName, Config::getDelay('proxy_data_wait'));
            } else {
                //
                if (!isset($this->proxies[$i])) {
                    # resets:
                    $i = 0;
                    Standards::doDelay($this->serviceName, Config::getDelay('proxy_data_pause'));

                    # update proxies:
                    $this->proxies = $this->getProxies();
                } else {
                    $useProxy = $this->proxies[$i];
                    $i++;
                }

                # info: right now $un_parsed contains only one link;
                foreach ($un_parsed as $l_no => $info) {
                    $this->link_id = $info['id'];
                    $link = $info['PageURL'];

                    $this->external_links = array(
                        // social:
                        'facebook' => 'http://api.facebook.com/restserver.php?method=links.getStats&urls=' . $link,
                        'tweeter' => 'http://urls.api.twitter.com/1/urls/count.json?url=' . $link,
                        'google_plus' => 'https://plusone.google.com/_/+1/fastbutton?url=' . $link,

                        // indexed:
                        'indexed_google' => 'https://www.google.de/search?hl=de&start=0&q=site:' . urlencode($link),
                        'indexed_bing' => 'http://www.bing.com/search?q=' . $this->cleanLinkForBing($link) . "&go=&qs=n&form=QBRE&filt=all&pq=" . $this->cleanLinkForBing($link) . "&sc=0-0&sp=-1&sk=&cc=de",

                        // rank:
                        'google_rank' => GooglePR::getURL($link),
                    );


                    $multi = new RequestMulti( $this->userAgentName );
                    $multi->addLinks( $this->external_links, $useProxy);
                    $multi->send();
                    $this->responses = $multi->getResponse();

                    // parse body's for needed data:
                    $this->parseProxyData();

                    # save data:
                    $this->dataSave();

                    # update status:
                    $this->updateStatus();

                    # small pause:
                    Standards::doDelay($this->serviceName. '[pid: ' . $this->getPID() . ']', Config::getDelay('proxy_data_wait'));
                }
            }

            # $RUN = false;
        }
    }

    /**
     * Parses data:
     */
    private function parseProxyData()
    {
        foreach ($this->responses as $r_no => $response) {
            $key = $response->linkId;

            switch ($key) {
                case 'google_plus':
                    $result = 0;
                    if (preg_match("/= \{c:(.*?),/", $response->body, $matched)) {
                        $result = (int)$matched[1];
                    }

                    //
                    $this->dataCollected[$key] = $result;
                    break;
                case 'tweeter':
                    $result = 0;
                    $content = json_decode($response->body, true);
                    if (is_array($content)) {
                        if (isset($content['count'])) {
                            $result = $content['count'];
                        }
                    }

                    //
                    $this->dataCollected[$key] = $result;
                    break;
                case 'facebook':
                    libxml_use_internal_errors(true);
                    try {
                        $xml = simplexml_load_string($response->body);
                    } catch (Exception $e) {
                        // ..
                        $xml = array();
                    }

                    $arr = json_decode(json_encode($xml), true);
                    if (!isset($arr['link_stat'])) {
                        $arr = array(
                            'share_count' => 0,
                            'like_count' => 0,
                            'comment_count' => 0,
                        );
                    } else {
                        $arr = $arr['link_stat'];
                    }

                    $this->dataCollected += array(
                        'fb_shares' => $arr['share_count'],
                        'fb_likes' => $arr['like_count'],
                        'fb_comments' => $arr['comment_count'],
                    );
                    break;
                case 'google_rank':
                    $pageRank = '0';
                    if (($pos = strpos($response->body, 'Rank_')) !== false) {
                        $pageRank = substr($response->body, $pos + 9);
                    }

                    $this->dataCollected[$key] = $pageRank;
                    break;
                case 'indexed_google':
                    $res = 'unknown';
                    if (strlen($response->body) == 0) {
                        $res = "proxy-error";
                    } elseif (stripos($response->body, 'id="resultStats"') !== false) {
                        $res = "found";
                    } elseif (stripos($response->body, "did not match") !== false OR stripos($response->body, 'gefunden.') !== false) {
                        $res = "not-found";
                    }

                    $this->dataCollected[$key] = $res;
                    break;
                case 'indexed_bing':
                    $res = 'unknown';
                    if (strlen($response->body) == 0) {
                        $res = "proxy-error";
                    } elseif (stripos($response->body, "keine") !== false and stripos($response->body, "gefunden.") !== false) {
                        $res = "not-found";
                    } elseif (stripos($response->body, 'class="sb_count"') !== false) {
                        $res = "found";
                        if (preg_match('#<span(.*?)class="sb_count"(.*?)>[\d]+</span>#', $response->body, $matched)) {
                            $count = trim($matched[3]);
                            if ($count < 1) {
                                $res = "not-found";
                            }
                        }
                    }

                    $this->dataCollected[$key] = $res;
                    break;
            }
        }
    }

    /**
     * @param $link
     * @return mixed
     */
    private function cleanLinkForBing($link)
    {
        $replace = array(
            "https://",
            "http://",
            "www."
        );

        return str_ireplace($replace, "", $link);
    }
}