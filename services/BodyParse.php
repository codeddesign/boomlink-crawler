<?php

class BodyParse
{
    protected $xPath, $xDoc, $xBody, $headings, $body, $parsedUrl, $parsedDomain, $mainURL;
    public $collected;

    function __construct($parsedUrl, $body)
    {
        $this->body = $body;
        $this->parsedUrl = $parsedUrl;
        $this->parsedDomain = Standards::getHost($parsedUrl);
        $this->mainURL = Standards::getMainURL($parsedUrl);

        $this->collected = array();

        // needed to avoid 'html' errors/warnings:
        libxml_use_internal_errors(true);

        // 'normalize' body - IMPORTANT - it makes lowercase all tags
        $this->xDoc = new DomDocument();
        $this->xDoc->NormalizeDocument();
        $this->xDoc->loadHTML($body);

        // sets:
        $this->xBody = $this->xDoc->saveHTML();
        $this->xPath = new DomXPath($this->xDoc);

        // needed to avoid 'html' errors/warnings:
        libxml_use_internal_errors(false);
    }

    /**
     * @return bool
     */
    public function isCrawlAllowed()
    {
        if (isset($this->collected['metaData'])) {
            $meta = $this->collected['metaData'];
        } else {
            $meta = $this->getMetaData();
        }

        // ..
        if (isset($meta['robots']) AND !Standards::isFollowable($meta['robots'])) {
            return false;
        }

        $this->collectAllData();
        return true;
    }

    /**
     * - RUNS ALL FUNCTIONS THAT GATHER DATA;
     * - created this way in case extra filtering or cases check is/are required along the workflow;
     * - commented lines are not extremely need. The methods might be called on need from the other ones;
     */
    private function collectAllData()
    {
        $this->getPageTitle();
        $this->getLanguage();
        $this->getCharset();
        $this->getHeadingsCount();
        $this->getAllLinksData();
    }

    /**
     * @param DOMNamedNodeMap $attributes
     * @return array
     */
    private function getNodeAttributes(DOMNamedNodeMap $attributes)
    {
        $out = array();
        foreach ($attributes as $attribute) {
            $out[$attribute->name] = $attribute->value;
        }

        return $out;
    }

    /**
     * ! NOT BEING USED DUE TO POSSIBLE HTML ERRORS !
     * @return array
     */
    private function getLinksViaDOM()
    {
        $save = array();
        $nodes = $this->getElementsByTagName('A');
        foreach ($nodes as $n_no => $node) {
            if ($node->attributes->length > 0) {
                //$save[] = $this->getNodeAttributes($node->attributes);
            }
        }

        return $save;
    }

    /**
     * @return array
     */
    public function getHeadingsCount()
    {
        if (isset($this->collected['headersCount'])) {
            return $this->collected['headersCount'];
        }

        // ..
        $save = array();
        for ($i = 1; $i <= 6; $i++) {
            $tempTagName = 'H' . $i;

            if (!isset($save[$tempTagName])) {
                $save[strtolower($tempTagName)] = 0;
            }

            $elements = $this->getElementsByTagName($tempTagName);
            $save[strtolower($tempTagName)] += $elements->length;
        }

        $this->collected['headersCount'] = $save;

        return $save;
    }

    /**
     * @param $tagName
     * @param $multiple
     * @return DOMNode|DOMNodeList
     */
    private function getStuffByTagName($tagName, $multiple)
    {
        $tagName = strtolower($tagName);

        $temp = $this->xDoc->getElementsByTagName($tagName);
        if (!$multiple AND $temp->length === 1) {
            // DOMNode:
            return $temp->item(0);
        } else {
            // DOMNodeList:
            return $temp;
        }
    }

    /**
     * @param $tagName
     * @return DOMNode|DOMNodeList
     */
    private function getElementByTagName($tagName)
    {
        return $this->getStuffByTagName($tagName, false);
    }

    /**
     * @param $tagName
     * @return DOMNode|DOMNodeList
     */
    private function getElementsByTagName($tagName)
    {
        return $this->getStuffByTagName($tagName, true);
    }

    /**
     * @return string
     */
    public function getPageTitle()
    {
        if (isset($this->collected['pageTitle'])) {
            return $this->collected['pageTitle'];
        }

        // ..
        $node = $this->getElementByTagName('TITLE');

        // fall-back case:
        if (is_object($node) AND property_exists($node, 'length')) {
            $node = $node->item(0);
        }

        $pageTitle = Standards::getBodyText($node->nodeValue);

        $this->collected['pageTitle'] = $pageTitle;
        return $pageTitle;
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        $elements = $this->getElementsByTagName('META');
        $save = array();
        foreach ($elements as $n_no => $node) {
            $mergeTemp = array();

            // get all attributes of the element:
            $attributes = $this->getNodeAttributes($node->attributes);
            if (count($attributes) == 1) {
                // there's no need to lowercase the key name here:
                $mergeTemp = $attributes;
            } else {
                $attributes = array_values($attributes);

                for ($i = 0; $i < count($attributes); $i++) {
                    if (isset($attributes[$i + 1])) {
                        // lowercase is needed because the 1st value becomes a key
                        $mergeTemp = array(strtolower($attributes[$i]) => $attributes[$i + 1]);
                    }
                }
            }

            if (count($mergeTemp) > 0) {
                $save = array_merge($save, $mergeTemp);
            }
        }

        $this->collected['metaData'] = $save;
        return $save;
    }

    /**
     * @param $attributes
     * @return array
     */
    private function regGetAttributes($attributes)
    {
        $ending = ';nIl';
        $attributes = trim($attributes);
        if (strlen($attributes) == 0) {
            return array();
        }

        // special test:
        if (stripos($attributes, '7tv') !== false) {
            //$attributes = ' class="xyz"'.$attributes;
            //$attributes .= 'class="xyz"';
        }

        $attributes .= $ending;
        if (preg_match_all('#\s*(.*?)\s*=\s*("|\')(.*?)("|\'|' . $ending . ')\s*#', $attributes, $matched)) {
            $save = array();
            $temp = array_combine($matched[1], $matched[3]);
            foreach ($temp as $a_name => $a_value) {
                $save[strtolower($a_name)] = trim($a_value);
            }

            return $save;
        }

        return array();
    }

    /**
     * @param $tagName
     * @return array
     */
    private function regGetElementsByTagName($tagName)
    {
        $elements = array();
        if (preg_match_all('#<' . $tagName . '(.*?)>(.*?)</' . $tagName . '>#is', $this->body, $matched)) {
            foreach ($matched[1] as $m_no => $m) {
                $elements[$m_no]['attributes'] = $this->regGetAttributes($m);
                $elements[$m_no]['textContent'] = trim(strip_tags($matched[2][$m_no]));
            }
        }

        return $elements;
    }

    /**
     * @param $attribute
     * @param array $attributes
     * @return bool
     */
    private function hasAttribute($attribute, array $attributes)
    {
        return isset($attributes[$attribute]);
    }

    /**
     * @param array $link
     * @return bool
     */
    private function adaptLinkData(array $link)
    {
        if ($this->hasAttribute('href', $link['attributes'])) {
            $tempHref = Standards::getCleanURL($link['attributes']['href']);

            /* separate the data of interest: */
            if (Standards::linkIsOK($tempHref)) {
                $linkData['href'] = array_flip(
                    array(
                        Standards::makeAbsoluteLink(array(
                            'main' => $this->mainURL,
                            'parsed' => $this->parsedUrl,
                        ), $tempHref)
                    )
                );

                #debug:
                /* echo $this->parsedUrl . ' + ' . $tempHref . ' => ' . key($linkData['href']) . "\n"; */

                // remove href from attributes:
                unset($link['attributes']['href']);

                $linkData['textContent'] = array_flip(array($link['textContent']));
                $linkData['attributes'] = $link['attributes'];
                return $linkData;
            }
        }

        return false;
    }

    /**
     * @param array $linkData
     * @return array
     */
    private function makeLinkDataUnique(array $linkData)
    {
        if (count($linkData) == 0) {
            return $linkData;
        }

        // Get them uniquely in a separate array:
        $linksOnly = array();
        foreach ($linkData as $l_no => $data) {
            if (isset($linkData[$l_no])) {
                $linksOnly[key($data['href'])][] = $l_no;
            }
        }

        // Merge the ones which have more than 1 link_no to the first and unset the rest:
        foreach ($linksOnly as $link => $all_l_no) {
            if (count($all_l_no) > 1) {
                for ($i = 1; $i < count($all_l_no); $i++) {
                    // remove href before merge:
                    unset($linkData[$all_l_no[$i]]['href']);

                    // merge to first:
                    $linkData[$all_l_no[0]] = array_merge_recursive($linkData[$all_l_no[0]], $linkData[$all_l_no[$i]]);

                    // remove completely:
                    unset($linkData[$all_l_no[$i]]);
                }
            }

            $linksOnly[$link] = $all_l_no[0];
        }

        // Ignore and remove the ones which might have an extra '/' at the end;
        $ignoredLinks = array();
        $ignoredLinksData = array();
        foreach ($linksOnly as $link => $l_no) {
            $find = ($link[strlen($link) - 1] == '/') ? substr($link, 0, strlen($link) - 1) : $link . '/';
            if (array_key_exists($find, $linksOnly) AND !array_key_exists($link, $ignoredLinks)) {
                $temp = $linkData[$linksOnly[$find]];
                unset($temp['href']); // <- remove href before saving

                $ignoredLinksData[$l_no] = $temp;
                $ignoredLinks[$find] = '';

                // remove from $linkData:
                unset($linkData[$linksOnly[$find]]);
            }
        }

        // Merge ignoredLinksData saved in previous step:
        foreach ($ignoredLinksData as $l_no => $data) {
            $linkData[$l_no] = array_merge($linkData[$l_no], $data);
        }

        return array_values($linkData);
    }

    /**
     * @param array $linkData
     * @return array
     */
    private function makeLinkDataCleaner(array $linkData)
    {
        foreach ($linkData as $l_no => $data) {
            foreach ($data as $key => $temp) {
                // make the array as a value
                if ($key == 'href') {
                    $linkData[$l_no][$key] = key($temp);
                }

                // adapts to textContent:
                if ($key == 'textContent') {
                    foreach ($temp as $t_key => $t_data) {
                        // remove extra array created due to array_merge_recursive in a previous step:
                        if (is_array($t_data)) {
                            $linkData[$l_no][$key][$t_key] = 0;
                        }

                        // save only the ones which got something:
                        if (strlen(trim($t_key)) > 0) {
                            $linkData[$l_no][$key][] = $t_key;
                        }

                        // remove old ones:
                        unset($linkData[$l_no][$key][$t_key]);
                    }
                }
            }
        }

        return array_values($linkData);
    }

    /**
     * Separates the links depending on: 'no-follow/no-index' / if 'external' / if 'internal'.
     * @param array $linkData
     * @return array
     */
    private function separateLinkData(array $linkData)
    {
        foreach ($linkData as $l_no => $data) {
            // remove attributes:
            unset($linkData[$l_no]['attributes']);

            if (isset($data['attributes']['rel']) AND !Standards::isFollowable($data['attributes']['rel'])) {
                // set: no-follow/no-index links:
                $type = 'no-follow';
            } else if (Standards::getHost($data['href']) !== $this->parsedDomain) {
                // set: external links:
                $type = 'external';
            } else {
                // set: internal links:
                $type = 'internal';
            }

            // save:
            $this->collected['linkData'][$type][] = $linkData[$l_no];

            # debug:
            /*echo $data['href']. ' '.$type." (".Standards::getHost($data['href'])." ? ".$this->parsedDomain.")\n";*/
        }

        // fallback case:
        $possibleTypes = array('no-follow', 'external', 'internal');
        foreach ($possibleTypes as $p_no => $type) {
            if (!isset($this->collected['linkData'][$type])) {
                $this->collected['linkData'][$type] = array();
            }
        }

        return $linkData;
    }

    /**
     * @return bool
     */
    public function getAllLinksData()
    {
        $elements = $this->regGetElementsByTagName('a');

        # adapt link data:
        $linkData = array();
        foreach ($elements as $t_no => $t) {
            if (($tempData = $this->adaptLinkData($t)) !== false) {
                $linkData[] = $tempData;
            }
        }

        // fallback case:
        if (count($linkData) == 0) {
            return false;
        }

        # filtering:
        $linkData = $this->makeLinkDataUnique($linkData);
        $linkData = $this->makeLinkDataCleaner($linkData);

        // save separate links data based on type:
        $linkData = $this->separateLinkData($linkData);

        // save all data too:
        $this->collected['linkData']['complete'] = $linkData;

        return true;
    }

    /**
     * @return array
     */
    public function getCanonicalLINKS()
    {
        if (isset($this->collected['canonicalLinks'])) {
            return $this->collected['canonicalLinks'];
        }

        // ..
        $canonicalLinks = array();
        $nodes = $this->regGetElementsByTagName('link');

        foreach ($nodes as $n_no => $node) {
            $relValue = strtolower(trim($node->getAttribute('rel')));

            if (strtolower($relValue) == 'canonical') {
                $href = strtolower(trim($node->getAttribute('href')));
                if (strlen($href) > 0) {
                    $canonicalLinks[$href] = '';
                }
            }
        }

        $this->collected['canonicalLinks'] = $canonicalLinks;

        return $canonicalLinks;
    }

    /**
     * It's not returning links that DON'T ALLOW FOLLOWING and also is NOT CONTAINING the CANONICAL ones.
     * @return array
     */
    public function getFilteredInternalLinksOnly()
    {
        if (!isset($this->collected['linkData'])) {
            $this->getAllLinksData();
        }

        //..
        $links = array();
        foreach ($this->collected['linkData']['internal'] as $a_no => $a) {
            if (isset($a['href'])) {
                $links[$a['href']] = '';
            }
        }

        // remove canonical links from 'linksOnly':
        if (!isset($this->collected['canonicalLinks'])) {
            $this->getCanonicalLINKS();
        }

        foreach ($links as $link => $null) {
            if (array_key_exists($link, $this->collected['canonicalLinks'])) {
                unset($links[$link]);
            }
        }

        // set:
        $this->collected['crawlableLinks'] = $links;

        return $links;
    }

    public function getContentLanguage()
    {

    }

    /**
     * @return bool|string
     */
    public function getCharset()
    {
        if (isset($this->collected['charset'])) {
            return $this->collected['charset'];
        }

        // ..
        $charset = false;
        if (!isset($this->collected['metaData'])) {
            $this->getMetaData();
        }

        $metaData = $this->collected['metaData'];
        if (isset($metaData['charset'])) {
            $charset = $metaData['charset'];
        }

        if (!$charset AND isset($metaData['content-type'])) {
            if (preg_match('/charset=(.*)/i', $metaData['content-type'], $matched)) {
                $charset = $matched[1];
            }
        }

        if ($charset !== FALSE) {
            $charset = trim(strtolower($charset));
        }

        $this->collected['charset'] = $charset;
        return $charset;
    }

    /**
     * @return bool|string
     */
    public function getLanguage()
    {
        if (isset($this->collected['language'])) {
            return $this->collected['language'];
        }

        // ..
        $lang = false;
        if (!isset($this->collected['metaData'])) {
            $this->getMetaData();
        }

        $metaData = $this->collected['metaData'];
        if (isset($metaData['content-language'])) {
            $lang = $metaData['content-language'];
        }

        if (!$lang) {
            // check the values from 'html' tag:
            $element = $this->getElementByTagName('HTML');
            if (property_exists($element, 'length')) {
                $element = $element->item(0);
            }

            $attributes = $this->getNodeAttributes($element->attributes);

            $possibleNames = array('lang', 'xml:lang');
            foreach ($possibleNames as $p_no => $p_name)
                if (!$lang AND isset($attributes[$p_name])) {
                    $lang = $attributes[$p_name];
                }
        }

        if ($lang !== FALSE) {
            $lang = trim(strtolower($lang));
        }

        $this->collected['language'] = $lang;
        return $lang;
    }

    public function viewAllData()
    {
        print_r(
            $this->collected
        );
    }
}