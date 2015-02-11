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

$link = trim($_POST['link']);

$single = new RequestSingle($link, 'boomlink/v3' );
$single->send();

# get request's response:
$response = $single->getResponse();

$bp = new BodyParse( $link, $response->body, $response->header, $response->curlInfo );
$bp->isCrawlAllowed();

// ..
$collected = $bp->collected;
unset( $collected['linkData']['complete'] );
$collected['body_text']     = $bp->getBodyText();
$collected['headings_text'] = $bp->getHeadingsText();

json_exit( $collected );