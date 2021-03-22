<?php

$startTime = microtime(true);
chdir("C:\Users\schoo\Desktop");
@mkdir($argv[1]);
chdir($argv[1]);
file_put_contents("Download.{$argv[2]}", file_get_contents($argv[3]));
$time = microtime(true) - $startTime;
echo "Task finished in $time seconds...\n";