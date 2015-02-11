<?php

class Response
{
    public $linkId;
    public $link;
    public $body;
    public $header;
    public $curlInfo;
    public $redirectTo;
    public $linkInfo;
    public $linkNo;

    private $req;

    public function __construct( Request $request )
    {
        $this->req = $request;
        $this->makeSets();

        unset( $this->req );
    }

    public function makeSets()
    {
        $this->linkId   = $this->req->linkId;
        $this->link     = $this->req->link;
        $this->curlInfo = $this->req->curlInfo;
        $this->linkInfo = $this->req->linkInfo;
        $this->linkNo   = $this->req->linkNo;

        $content      = $this->req->body;
        $this->body   = trim( substr( $content, $this->req->curlInfo['header_size'] ) );
        $this->header = trim( substr( $content, 0, $this->req->curlInfo['header_size'] ) );

        $this->redirectTo = $this->curlInfo['redirect_url'];


    }

    /**
     * @return int
     */
    public function isRedirect()
    {
        return strlen( $this->redirectTo );
    }
}