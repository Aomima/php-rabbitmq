<?php
include  __DIR__ ."/../vendor/autoload.php";
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
$config = [
    'host' => '192.168.100.61',
    'port' => '5672',
    'login' => 'admin',
    'passward' => '123456',
    'vhost' => '/'
];
$con = $connection = new AMQPStreamConnection($config['host'],$config['port'],$config['login'],$config['passward'],$config['vhost']);
$channel = $con->channel();
//延迟交换机名称
$ex = "delay_ex";
$ex_type = "x-delayed-message";
//队列名称
$que = "delay_for_queue";
//routing key
$routing_key = "delay_routing_key";
$args = new AMQPTable();
$args->set('x-delayed-type','direct');
//声明延时交换机
$channel->exchange_declare($ex,$ex_type,false,true,false,false,false,$args);
//声明队列
$channel->queue_declare($que,false,true,false,false);
//绑定
$channel->queue_bind($que,$ex,$routing_key);
$data = [
    'delay_id' => 66,
    'delay_msg' => 'success',
];
$message = new AMQPMessage(json_encode($data),[
    'content_type' => 'text/plain',
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'application_headers' => new AMQPTable(['x-delay'=>10000]),
]);

//开启发布确认
$channel->confirm_select();
$channel->set_ack_handler(function (AMQPMessage $message){
    echo "成功投递延时消息:".$message->getBody();
});
$channel->set_nack_handler(function (AMQPMessage $message){
    echo "消息投递失败".$message->getBody();
});
//开启return机制
$channel->set_return_listener(function ($replyCode, $replyText, $exchange, $routingKey, $message){
    $msg  = 'oh hoo！发生错误了'.PHP_EOL;
    $msg .= '错误码：'.$replyCode.PHP_EOL;
    $msg .= '错误信息：'.$replyText.PHP_EOL;
    $msg .= '指定的交换机：'.$exchange.PHP_EOL;
    $msg .= '指定的路由键：'.$routingKey.PHP_EOL;
    $msg .= '投递的消息：'.$message->body.PHP_EOL;
    print_r($msg);
});
$channel->basic_publish($message,$ex,$routing_key,true);
$channel->wait_for_pending_acks();
$channel->close();
$con->close();





