<?php
namespace Demo;
use Zxy\PhpRabbitmq\RabbitmqServer;
require __DIR__.'/../vendor/autoload.php';

$server = RabbitmqServer::getInstance("direct","ggg","ggg","ggg");
// for ($i = 0; $i < 100; $i++) {
//     $server->push([
//         'msg' => "第".($i+1)."次消息",
//         'data' => "successful"
//     ]);
//     sleep(1);
// }
//
$server = $server->createDeadLetterQueue('hhh','hhh','hhh');
// $server->pushDelay([
//     'msg' => '第'.(1).'次延迟消息',
//     'data' => 'successful',
// ],(1));
for ($i = 0; $i < 100; $i++) {
    $server->pushDelay([
        'msg' => '第'.($i+1).'次延迟消息',
        'data' => 'successful',
    ],($i+1));
}

// $server = $server->createDeadLetterQueue('fff','fff','fff');
// for ($i = 0; $i < 100; $i++) {
//     $server->pushExpiration([
//         'msg' => '第'.($i+1).'次expiration延迟消息',
//         'data' => 'successful',
//     ],($i+1));
// }

// for ($i = 0; $i < 100; $i++) {
//     $server->pushDelay([
//         'msg' => '第'.($i+1).'次延迟消息',
//         'data' => 'successful',
//     ],($i+1));
// }

