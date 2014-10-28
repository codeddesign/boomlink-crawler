<?php
/**
 * @param $className
 */
function __autoload($className)
{
    $dirs = array(
        'helpers',
        'libraries',
        'services',

    );

    foreach ($dirs as $d_no => $dir) {
        $fileName = $dir . '/' . $className . '.php';
        if (file_exists($fileName)) {
            require_once $fileName;
        }
    }
}