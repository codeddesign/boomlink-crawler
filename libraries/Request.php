<?php

abstract class Request
{
    public $agent;
    public $link;
    public $linkId;
    public $body;
    public $curlInfo;
    public $linkInfo;

    public $linkNo;

    protected $response;

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }
}