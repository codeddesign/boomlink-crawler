<?php

class RequestMulti extends Request
{
    private $mh;
    private $queue;
    private $running = array();
    private $runningCount = 0;

    public function __construct( $agent )
    {
        $this->agent = $agent;
        $this->mh    = curl_multi_init();
    }

    public function __destruct()
    {
        if (isset( $this->mh )) {
            curl_multi_close( $this->mh );
        }
    }

    /**
     * @param array $links
     * @param null $proxy
     *
     * @return $this
     */
    public function addLinks( array $links, $proxy = null )
    {
        if ( ! is_array( $links[key( $links )] )) {
            $links = $this->linksAdapt( $links );
        }

        foreach ($links as $l_no => $info) {
            $single = new RequestSingle( $info, $this->agent, $l_no, $proxy);
            $this->attach( $single );
        }

        return $this;
    }

    /**
     * @param $linksFromDb
     *
     * @return array
     */
    protected function linksAdapt( $linksFromDb )
    {
        $out = array();
        foreach ($linksFromDb as $l_no => $link) {
            $out[] = array(
                'linkId' => ( is_array($link) AND isset( $link['id'] ) ) ? $link['id'] : $l_no,
                'link'   => ( is_array($link) AND isset( $link['PageURL'] ) ) ? $link['PageURL'] : $link,
                'depth'  => ( is_array($link) AND isset( $link['depth'] ) ) ? $link['depth'] : 0,
            );
        }

        return $out;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function send()
    {
        while ($this->performCurl()) {
            $this->selectCurl();
        }

        return $this;
    }

    /**
     * @param RequestSingle $request
     *
     * @return $this
     */
    private function attach( RequestSingle $request )
    {
        $this->queue[$request->getRID()] = $request;

        return $this;
    }

    /**
     * @param RequestSingle $request
     *
     * @return $this
     */
    private function detach( RequestSingle $request )
    {
        unset( $this->queue[$request->getRID()] );

        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function performCurl()
    {
        if ($this->count() == 0) {
            throw new Exception( 'Cannot perform if there are no requests in queue.' );
        }

        $notRunning = $this->getRequestsNotRunning();
        do {
            /**
             * Apply cURL options to new requests
             */
            foreach ($notRunning as $request) {
                curl_multi_add_handle( $this->mh, $request->getResource() );
                $this->running[$request->getRID()] = $request;
            }

            $runningBefore = $this->runningCount;
            do {
                $mrc = curl_multi_exec( $this->mh, $this->runningCount );
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
            $runningAfter = $this->runningCount;

            $completed = ( $runningAfter < $runningBefore ) ? $this->read() : 0;

            $notRunning = $this->getRequestsNotRunning();
        } while (count( $notRunning ) > 0);


        return $this->count() > 0;
    }

    /**
     * @param int $timeout
     *
     * @return bool
     * @throws Exception
     */
    private function selectCurl( $timeout = 1 )
    {
        if ($this->count() == 0) {
            throw new Exception( 'Cannot select if there are no requests in queue.' );
        }

        return curl_multi_select( $this->mh, $timeout ) !== - 1;
    }

    /**
     * @return int
     */
    private function read()
    {
        $n = 0;
        while ($info = curl_multi_info_read( $this->mh )) {
            $n ++;
            $request = $this->queue[(int) $info['handle']];
            $result  = $info['result'];

            curl_multi_remove_handle( $this->mh, $request->getResource() );
            unset( $this->running[$request->getRID()] );
            $this->detach( $request );

            // make needed sets:
            $this->linkId   = $request->linkId;
            $this->link     = $request->link;
            $this->linkInfo = $request->linkInfo;
            $this->linkNo   = $request->linkNo;

            $this->body     = curl_multi_getcontent( $request->getResource() );
            $this->curlInfo = curl_getinfo( $request->getResource() );

            // create response:
            $this->response[$request->linkNo] = new Response( $this );

            if ($result !== CURLE_OK) {
                $error = curl_error( $request->getResource() );
                echo "curl error: ".$error . "\n\n";
                exit();
            }
        }

        return $n;
    }

    /**
     * @return array
     */
    private function getRequestsNotRunning()
    {
        return array_diff_key( $this->queue, $this->running );
    }

    /**
     * @return int
     */
    private function count()
    {
        return count( $this->queue );
    }
}