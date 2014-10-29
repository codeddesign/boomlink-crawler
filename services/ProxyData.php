<?php

/* Description:
 * - ACTS LIKE A SINGLE PARALLEL SERVICE;
 * - Even though all data is actually external data, this class (more precisely) gets data that requires PROXY in order to get it;
 * - It also takes care of the proxy logic;
 * */

class ProxyData extends Service
{
    private $curl, $external_links, $link;

    public function doSets(array $arguments = array('url' => '', 'domain_id' => '', 'url_id' => ''))
    {
        $link = trim($arguments['url']);
        if (strlen($link) == 0 OR !Standards::linkHasScheme($link)) {
            Standards::debug(__CLASS__ . ': missing link?', Standards::DO_EXIT);
        }

        // sets:
        $this->link = $link;
        $this->external_links = array(
            // social:
            'facebook' => 'http://api.facebook.com/restserver.php?method=links.getStats&urls=' . $link,
            'tweeter' => 'http://urls.api.twitter.com/1/urls/count.json?url=' . $link,
            'google' => 'https://plusone.google.com/_/+1/fastbutton?url=' . $link,

            // indexed:
            'google_indexed' => 'https://www.google.de/search?hl=de&start=0&q=site:' . urlencode($link),
            'bing_indexed' => 'http://www.bing.com/search?q=' . $this->cleanLinkForBing($link) . "&go=&qs=n&form=QBRE&filt=all&pq=" . $this->cleanLinkForBing($link) . "&sc=0-0&sp=-1&sk=&cc=de",

            // rank:
            'google_rank' => GooglePR::getURL($link),
        );

        $this->dataCollected = array(
            'domain_id' => $arguments['domain_id'],
            'link_id' => $arguments['link_id'],
        );
    }

    /**
     * Runs curl:
     */
    public function doWork()
    {
        // do the actual curl:
        $this->curl = new Curl();
        $this->curl->addLinks($this->external_links);
        $this->curl->run();

        // parse body's for needed data:
        $this->parseProxyData();
    }

    /**
     * Parses data:
     */
    private function parseProxyData()
    {
        $bodies = $this->curl->getBodyOnly();
        foreach ($bodies as $key => $content) {
            switch ($key) {
                case 'google':
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
                    if (array_key_exists("count", $content)) {
                        $result = $content["count"];
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
                case 'google_indexed':
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
                case 'bing_indexed':
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