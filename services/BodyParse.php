<?php

class BodyParse
{
    protected $xPath, $xDoc;
    public $body, $headings;

    function __construct($body)
    {
        // needed to avoid 'html' errors/warnings:
        libxml_use_internal_errors(true);

        // 'normalize' body - IMPORTANT - it makes lowercase all tags
        $this->xDoc = new DomDocument();
        $this->xDoc->NormalizeDocument();
        $this->xDoc->loadHTML($body);

        // sets:
        $this->body = $this->xDoc->saveHTML();
        $this->xPath = new DomXPath($this->xDoc);

        // needed to avoid 'html' errors/warnings:
        libxml_use_internal_errors(false);
    }

    public function getLinks()
    {
        $save = array();
        $nodes = $this->getElementsByTagName('a');
        foreach ($nodes as $n_no => $node) {
            if ($node->attributes->length > 0) {
                $save[] = $this->getNodeAttributes($node->attributes);
            }
        }

        print_r($save);
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
        $node = $this->getElementByTagName('title');

        // fall-back case:
        if (is_object($node) AND property_exists($node, 'length')) {
            $node = $node->item(0);
        }

        return array('title' => DataStandards::getBodyText($node->nodeValue));
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        $nodes = $this->getElementsByTagName('meta');
        $needed = array(
            'description' => DataStandards::getDefaultMeta('description'),
        );

        foreach ($nodes as $n_no => $node) {
            $name = $node->getAttribute('name');
            if (array_key_exists($name, $needed)) {
                $needed[$name] = DataStandards::getBodyText($node->getAttribute('content'));
            }
        }

        return $needed;
    }
}