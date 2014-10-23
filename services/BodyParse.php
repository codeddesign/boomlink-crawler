<?php

class BodyParse
{
    protected $xPath, $xDoc, $xBody, $headings, $body, $parsedUrl;

    function __construct($parsedUrl, $body)
    {
        $this->body = $body;
        $this->parsedUrl = $parsedUrl;

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
        $save = array();
        for ($i = 1; $i <= 6; $i++) {
            $tempTagName = 'H' . $i;

            if (!isset($save[$tempTagName])) {
                $save[strtolower($tempTagName)] = 0;
            }

            $elements = $this->getElementsByTagName($tempTagName);
            $save[strtolower($tempTagName)] += $elements->length;
        }

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
    protected function getElementByTagName($tagName)
    {
        return $this->getStuffByTagName($tagName, false);
    }

    /**
     * @param $tagName
     * @return DOMNode|DOMNodeList
     */
    protected function getElementsByTagName($tagName)
    {
        return $this->getStuffByTagName($tagName, true);
    }

    /**
     * @return array
     */
    public function getPageTitle()
    {
        $node = $this->getElementByTagName('TITLE');

        // fall-back case:
        if (is_object($node) AND property_exists($node, 'length')) {
            $node = $node->item(0);
        }

        return array('title' => Standards::getBodyText($node->nodeValue));
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        $nodes = $this->getElementsByTagName('META');
        $needed = array(
            'description' => Standards::getDefaultMeta('description'),
        );

        foreach ($nodes as $n_no => $node) {
            $name = $node->getAttribute('name');
            if (array_key_exists($name, $needed)) {
                $needed[$name] = Standards::getBodyText($node->getAttribute('content'));
            }
        }

        return $needed;
    }

    /**
     * @param $attributes
     * @return array
     */
    private function regGetAttributes($attributes)
    {
        $attributes = trim($attributes);

        if (strlen($attributes) == 0) {
            return array();
        }

        $ending = ';nIl';

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
    protected function regGetElementsByTagName($tagName)
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
            if (Standards::linkIsOK($tempHref)) {
                /* separate the data of interest: */
                $linkData['href'] = array_flip(array(Standards::addMainLinkTo($this->parsedUrl, $tempHref)));

                // remove href from attributes:
                unset($link['attributes']['href']);

                $linkData['textContent'] = array_flip(array($link['textContent']));
                $linkData['attributes'] = $link['attributes'];
                return $linkData;
            }
        }

        return false;
    }

    private function filterLinks(array $linkData)
    {
        $linksOnly = array();
        foreach ($linkData as $l_no => $data) {
            // 1. remove no follow/no index/..:
            if (isset($data['rel']) AND !Standards::linkIsFollowable($data['rel'])) {
                unset($linkData[$l_no]);
            }

            // 2. get them uniquely in a separate array:
            if (isset($linkData[$l_no])) {
                $linksOnly[key($data['href'])][] = $l_no;
            }

            // 3. remove attributes:
            unset($linkData[$l_no]['attributes']);
        }

        // 4.a. merge the ones which have more than 1 link_no to the first and unset the rest:
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

        // 4.b. ignore+remove the ones which might have an extra '/' at the end;
        $ignoredLinks = array();
        $ignoredLinksData = array();
        foreach ($linksOnly as $link => $l_no) {
            $find = ($link[strlen($link) - 1] == '/') ? substr($link, 0, strlen($link) - 1) : $link . '/';
            if (array_key_exists($find, $linksOnly) AND !array_key_exists($link, $ignoredLinks)) {
                $temp = $linkData[$linksOnly[$find]];
                unset($temp['href']); // <- remove href from saved
                $ignoredLinksData[$l_no] = $temp;
                $ignoredLinks[$find] = '';

                // remove from $linkData:
                unset($linkData[$linksOnly[$find]]);
            }
        }

        // 5. merge
        foreach ($ignoredLinksData as $l_no => $data) {
            $linkData[$l_no] = array_merge($linkData[$l_no], $data);
        }

        // 6. make it cleaner / readable:
        $linkData = array_values($linkData);
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

        return $linkData;
    }

    /**
     * @return array
     */
    protected function getAllLinksData()
    {
        $elements = $this->regGetElementsByTagName('a');
        $linkData = array();

        # filtering 1:
        foreach ($elements as $t_no => $t) {
            if (($tempData = $this->adaptLinkData($t)) !== false) {
                $linkData[] = $tempData;
            }
        }

        // fallback case:
        if (count($linkData) == 0) {
            return $linkData;
        }

        // filtering #2
        $linkData = $this->filterLinks($linkData);

        return $linkData;
    }

    public function getHrefsOnly()
    {
        $allFilteredDataLinks = $this->getAllLinksData();
        $hrefs = array();

        foreach($allFilteredDataLinks as $a_no => $a) {
            if(isset($a['href'])) {
                $hrefs[$a['href']] = '';
            }
        }

        return $hrefs;
    }

    public function test() {
        print_r($this->getHrefsOnly());
    }
}