<?php

function test(int $seconds) : void{
    $callable = function() use(&$seconds){
        echo "$seconds seconds left" . PHP_EOL;
        $seconds--;
        if($seconds === 0){
            echo "time up!" . PHP_EOL;
            exit(0);
        }
        sleep(1);
    };
    while(true){
        $callable();
    }
}

$num = 5;
test($num);