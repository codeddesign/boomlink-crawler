<?php

/* Description:
 * - ACTS LIKE A SINGLE PARALLEL SERVICE;
 * - Even though all data is actually external data, this class (more precisely) gets data that requires PROXY in order to get it;
 * - It also takes care of the proxy logic;
 * */

class ProxyData extends Service
{
    private $dbo, $curl, $external_links, $proxies, $link_id;
    CONST CURRENT_STATUS = 0, NEW_STATUS = 1;

    public function doSets(array $arguments = array())
    {
        $this->dbo = new MySQL();
    }

    /**
     * @return bool|array
     */
    private function getNonParsedLinks()
    {
        $q = 'SELECT * FROM _sitemap_links WHERE proxy_data_status=' . self::CURRENT_STATUS . ' LIMIT 1';
        return $this->dbo->getResults($q);
    }

    /**
     * @return bool|array
     */
    private function getProxies()
    {
        $q = 'SELECT * FROM proxies_list';
        return $this->dbo->getResults($q);
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

        $q = 'UPDATE _sitemap_links_info SET ' . implode(',', $sets) . ' WHERE link_id=' . $this->link_id;
        return $this->dbo->runQuery($q);
    }

    private function updateStatus()
    {
        /*if (count($this->link_ids) == 0) {
            return false;
        }*/

        $q = 'UPDATE _sitemap_links SET proxy_data_status=' . static::NEW_STATUS . ' WHERE id IN (' . $this->link_id . ')';
        return $this->dbo->runQuery($q);
    }

    /**
     * Runs curl:
     */
    public function doWork()
    {
        $RUN = true;
        $i = 0;
        while ($RUN) {
            $un_parsed = $this->getNonParsedLinks();
            if (!$un_parsed) {
                $RUN = false;
            } else {
                $this->proxies = $this->getProxies();
                $useProxy = $this->proxies[$i];

                foreach ($un_parsed as $l_no => $info) {
                    $this->link_id = $info['id'];
                    $link = $info['page_url'];

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


                    $this->curl = new Curl(true, array('proxy' => $useProxy));
                    $this->curl->addLinks($this->external_links);
                    $this->curl->run();

                    // parse body's for needed data:
                    $this->parseProxyData();

                    # save data:
                    $this->dataSave();

                    # update status:
                    $this->updateStatus();
                }
            }

            if (!isset($this->proxies[$i])) {
                $i = 0;
                Standards::doPause('ProxyData', 60 * 30);
            } else {
                $i++;
                # make small delay:
                Standards::doDelay(rand(100, 300));
            }
        }
    }

    /**
     * Parses data:
     */
    private function parseProxyData()
    {
        $bodies = $this->curl->getBodyOnly();
        foreach ($bodies as $key => $content) {
            switch ($key) {
                case 'google_plus':
                    $result = 0;
                    if (preg_match("/= \{c:(.*?),/", $content, $matched)) {
                        $result = (int)$matched[1];
                    }

                    //
                    $this->dataCollected[$key] = $result;
                    break;
                case 'tweeter':
                    $result = 0;
                    $content = json_decode($content, true);
                    if (is_array($content)) {
                        if (array_key_exists('count', $content)) {
                            $result = $content['count'];
                        }
                    }

                    //
                    $this->dataCollected[$key] = $result;
                    break;
                case 'facebook':
                    $xml = simplexml_load_string($content);
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
                    if (($pos = strpos($content, 'Rank_')) !== false) {
                        $pageRank = substr($content, $pos + 9);
                    }

                    $this->dataCollected[$key] = $pageRank;
                    break;
                case 'indexed_google':
                    $res = 'unknown';
                    if (strlen($content) == 0) {
                        $res = "proxy-error";
                    } elseif (stripos($content, 'id="resultStats"') !== false) {
                        $res = "found";
                    } elseif (stripos($content, "did not match") !== false OR stripos($content, 'gefunden.') !== false) {
                        $res = "not-found";
                    }

                    $this->dataCollected[$key] = $res;
                    break;
                case 'indexed_bing':
                    $res = 'unknown';
                    if (strlen($content) == 0) {
                        $res = "proxy-error";
                    } elseif (stripos($content, "keine") !== false and stripos($content, "gefunden.") !== false) {
                        $res = "not-found";
                    } elseif (stripos($content, 'class="sb_count"') !== false) {
                        $res = "found";
                        if (preg_match('#<span(.*?)class="sb_count"(.*?)>[\d]+</span>#', $content, $matched)) {
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