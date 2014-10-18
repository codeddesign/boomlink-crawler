<?php

class Social
{
    protected $social_links, $stats, $curl;

    function __construct($link, $curl_opts = array())
    {
        // todo: add proxy;

        $this->curl = new Curl($curl_opts);
        $this->social_links = array(
            'google' => 'https://plusone.google.com/_/+1/fastbutton?url=' . $link,
            'facebook' => 'http://api.facebook.com/restserver.php?method=links.getStats&urls=' . $link,
            'tweeter' => 'http://urls.api.twitter.com/1/urls/count.json?url=' . $link,
        );
    }

    /**
     * runs crawler:
     */
    public function run()
    {
        $this->curl->addLinks($this->social_links);
        $this->curl->run();

        $this->parseStats();
    }

    /**
     * parses data:
     */
    protected function parseStats()
    {
        foreach ($this->curl->getBodyOnly() as $key => $content) {
            switch ($key) {
                case 'google':
                    $result = 0;
                    if (preg_match("/= \{c:(.*?),/", $content, $matched)) {
                        $result = (int)$matched[1];
                    }

                    //
                    $this->stats[$key] = $result;
                    break;
                case 'tweeter':
                    $result = 0;
                    $content = json_decode($content, true);
                    if (array_key_exists("count", $content)) {
                        $result = $content["count"];
                    }

                    //
                    $this->stats[$key] = $result;
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

                    $this->stats += array(
                        'fb_shares' => $arr['share_count'],
                        'fb_likes' => $arr['like_count'],
                        'fb_comments' => $arr['comment_count'],
                    );
                    break;
            }
        }
    }

    /**
     * @return array
     */
    public function getStats()
    {
        return $this->stats;
    }
}