<?php
// Function to find the vendor directory and require the autoload.php
function findAndRequireAutoload($dir) {
    $rootDirectory = $dir;
    while (!file_exists($rootDirectory . '/vendor/autoload.php')) {
        $rootDirectory = dirname($rootDirectory);
        if ($rootDirectory == dirname($rootDirectory)) {
            throw new Exception('Could not find vendor/autoload.php');
        }
    }
    require $rootDirectory . '/vendor/autoload.php';
    return $rootDirectory;
}