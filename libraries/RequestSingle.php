<?php

class RequestSingle extends Request
{
    private $sh;
    private $proxy;

    /**
     * @param $linkInfo | holds the information of the link (from db or anything else set)
     * @param $agent
     * @param int $linkNo
     * @param null $proxy
     */
    public function __construct( $linkInfo, $agent, $linkNo = 0, $proxy = null )
    {
        $this->linkInfo = $linkInfo;
        $this->linkNo = $linkNo;

        if ( ! is_array( $linkInfo )) {
            $linkInfo = $this->adaptLink( $linkInfo );
        }

        if ( ! isset( $linkInfo['link'] )) {
            $linkInfo = $this->translateInfo( $linkInfo );
        }

        $this->sh = curl_init();

        $this->link     = $linkInfo['link'];
        $this->linkId   = $linkInfo['linkId'];
        $this->agent    = $agent;
        $this->proxy    = $proxy;

        $this->addOptions();
    }

    public function __destruct()
    {
        if (isset( $this->sh )) {
            curl_close( $this->sh );
        }
    }

    /**
     * @return $this
     */
    public function send()
    {
        // make needed sets:
        $this->body     = curl_exec( $this->sh );
        $this->curlInfo = curl_getinfo( $this->sh );

        // create response:
        $this->response = new Response( $this );

        return $this;
    }

    protected function addOptions()
    {
        $options = array(
            CURLOPT_URL            => $this->link,
            CURLOPT_USERAGENT      => $this->agent,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        if(is_array($this->proxy)) {
            $options[CURLOPT_PROXYTYPE] = 'HTTP';
            $options[CURLOPT_PROXY] = $this->proxy['ProxyIP'].':'.$this->proxy['ProxyPort'];
        }

        curl_setopt_array( $this->sh, $options );
    }

    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->sh;
    }

    /**
     * @return int
     */
    public function getRID()
    {
        return (int) $this->sh;
    }

    /**
     * @param $info
     *
     * @return array
     */
    public function adaptLink( $info )
    {
        return array(
            'linkId' => 0,
            'link'   => $info
        );
    }

    /**
     * @param $linkInfo
     *
     * @return array
     */
    public function translateInfo( $linkInfo )
    {
        return array(
            'linkId' => $linkInfo['id'],
            'link'   => $linkInfo['PageURL'],
            'depth'  => $linkInfo['depth'],
        );
    }
}