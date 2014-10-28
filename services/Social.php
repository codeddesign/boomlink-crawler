<?php

class Social extends Service
{
    private $_social_links, $_curl;

    /**
     * In this service: it sets the links to be parsed
     * @param array $arguments
     */
    public function makeSets(array $arguments = array('url' => '', 'domain_id' => '', 'link_id' => ''))
    {
        $link = trim($arguments['url']);
        if (strlen($link) == 0 OR !Standards::linkHasScheme($link)) {
            $this->debug(__CLASS__ . ': missing link?', static::DO_EXIT);
            return;
        }

        $this->_social_links = array(
            'google' => 'https://plusone.google.com/_/+1/fastbutton?url=' . $link,
            'facebook' => 'http://api.facebook.com/restserver.php?method=links.getStats&urls=' . $link,
            'tweeter' => 'http://urls.api.twitter.com/1/urls/count.json?url=' . $link,
        );

        $this->dataCollected = array(
            'domain_id' => $arguments['domain_id'],
            'link_id' => $arguments['link_id'],
        );
    }

    public function doWork()
    {
        $this->_curl = new Curl();
        $this->_curl->addLinks($this->_social_links);
        $this->_curl->run();
        // ..
        $this->parseStats();
    }

    /**
     * parses data:
     */
    private function parseStats()
    {
        foreach ($this->_curl->getBodyOnly() as $key => $content) {
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

                    $temp = array(
                        'fb_shares' => $arr['share_count'],
                        'fb_likes' => $arr['like_count'],
                        'fb_comments' => $arr['comment_count'],
                    );

                    $this->dataCollected = array_merge($this->dataCollected, $temp);
                    break;
            }
        }
    }
}