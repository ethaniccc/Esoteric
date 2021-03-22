<?php

$startTime = microtime(true);
require 'vendor/autoload.php';

const SERVER = "nyc02.witherhosting.com:2022";
const USERNAME = "nujjxgw9.7342dacf";
const PASSWORD  = "aa46b8ed";

use phpseclib\Net\SFTP;

$sftp = new SFTP(SERVER);
if(!$sftp->login(USERNAME, PASSWORD)){
    echo "Failed to login into the server.";
    return;
}

$sftp->chdir('plugins');
$sftp->put("Esoteric.phar", file_get_contents('../../Esoteric.phar'));
$sftp->disconnect();

unlink("../../Esoteric.phar");
$timeDiff = microtime(true) - $startTime;
echo "Finished uploading Esoteric to the test server in $timeDiff seconds...\n";