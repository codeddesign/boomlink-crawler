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
    private function linkDataOK(array $link) {
        if ($this->hasAttribute('href', $link['attributes'])) {
            $tempHref = Standards::getCleanURL($link['attributes']['href']);
            if (Standards::linkIsOK($tempHref)) {
                $linkData['href'] = Standards::addMainLinkTo($this->parsedUrl, $tempHref);
                $linkData['textContent'] = $link['textContent'];
                unset($link['attributes']['href']);
                $linkData['attributes'] = $link['attributes'];
                return $linkData;
            }
        }

        return false;
    }

    private function filterLinks(array $linkData) {
        $linksOnly = array();
        foreach($linkData as $l_no => $data) {
            // 1. remove no follow/no index/..:
            if(isset($data['rel']) AND !Standards::linkIsFollowable($data['rel'])) {
                unset($linkData[$l_no]);
            }

            // 2. get them uniquely in a separate array:
            if(isset($linkData[$l_no])) {
                $linksOnly[$data['href']] = $l_no;
            }
        }

        // 3. remove the ones which might have an extra '/' at the end;
        $savedLinks = array();
        foreach($linksOnly as $link => $l_no) {
            $find = ($link[strlen($link)-1] == '/') ? substr($link, 0, strlen($link)-1) : $link.'/';
            if(array_key_exists($find, $linksOnly) AND !array_key_exists($link, $savedLinks)) {
                $saveDetails = $linkData[$linksOnly[$find]];
                /*echo 'saved:'.$linksOnly[$find];
                print_r($saveDetails);*/
                unset($linkData[$linksOnly[$find]]);
                $savedLinks[$find] = '';
            }
        }

        print_r($savedLinks);
        return array_values($linkData);
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
            if( ($tempData = $this->linkDataOK($t)) !== false) {
                $linkData[] = $tempData;
            }
        }

        // fallback case:
        if(count($linkData) == 0) {
            return $linkData;
        }

        // filtering #2
        $linkData = $this->filterLinks($linkData);

        return $linkData;
    }

    public function test()
    {
        print_r(
            $this->getAllLinksData()
        );
    }

    /**
     * @return mixed
     */
    private function getLinksNoIndex()
    {
        /*$links = $this->getElementsByTagNameAndAttribute('a', 'rel');
        return $links;*/
    }
}