<?php
namespace Demo;
use Zxy\PhpRabbitmq\RabbitmqServer;
require __DIR__.'/../vendor/autoload.php';

$server = RabbitmqServer::getInstance("direct","ggg","ggg","ggg");
// $callback = function ($message){
//     echo "消费消息:".$message.PHP_EOL;
//     return true;
// };
// $server->consume($callback);
$server = $server->createDeadLetterQueue('hhh','hhh','hhh');
$callback = function ($message){
    echo "延迟消息:".$message.PHP_EOL;
    return false;
};
$server->consumeDelay($callback);

// $callback = function ($message){
//     echo "死信队列:".$message.PHP_EOL;
//     return true;
// };
// $server = $server->createDeadLetterQueue('fff','fff','fff');
// $server->consumeDeadLetter($callback);