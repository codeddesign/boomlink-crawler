<?php
require_once 'autoload.php';

function json_exit( $out )
{
    header( 'Content-Type: application/json' );
    echo json_encode( $out, JSON_PRETTY_PRINT );
    exit();
}

if ( ! isset( $_POST['link'] ) OR ! strlen( trim( $_POST['link'] ) )) {
    json_exit( array( 'error' => 'no link set' ) );
}

$link = trim( $_POST['link'] );
$curl = new Curl();
$curl->runSingle( $link );

$bodies = $curl->getBodyOnly();
$info   = $curl->getLinkCurlInfo();
$heads  = $curl->getHeaderOnly();

$bp = new BodyParse( $link, $bodies[0], $heads[0], $info[0] );
$bp->isCrawlAllowed();

// ..
$collected = $bp->collected;
unset( $collected['linkData']['complete'] );
$collected['body_text']     = $bp->getBodyText();
$collected['headings_text'] = $bp->getHeadingsText();

json_exit( $collected );