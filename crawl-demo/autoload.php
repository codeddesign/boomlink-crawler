<?php
function __autoload($className)
{
    $dirs = array(
        'helpers',
        'libraries',
        'services',

    );

    foreach ($dirs as $d_no => $dir) {
        $fileName = __DIR__.'/../'.$dir . '/' . $className . '.php';
        if (file_exists($fileName)) {
            require_once $fileName;
        }
    }
}