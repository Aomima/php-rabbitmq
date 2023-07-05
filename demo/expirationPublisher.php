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
//交换机类型
$type = "direct";
//交换机名称
$exName = "normal_ex_expiration";
$deadLetterExName = "dead_letter_ex_expiration";
//队列名称
$queName = "normal_queue_expiration";
$deadLetterQueName = "dead_letter_que_expiration";
//路由键名称
$key = "normal_key_expiration";
$deadLetterKey = "dead_letter_key_expiration";
//声明交换机
$channel->exchange_declare($exName,$type,false,true,false);
$channel->exchange_declare($deadLetterExName,$type,false,true,false);
//声明死信队列参数
$args = new AMQPTable();
// $args->set('x-message-ttl',10000);
$args->set('x-dead-letter-exchange',$deadLetterExName);
$args->set('x-dead-letter-routing-key',$deadLetterKey);
//声明队列
$channel->queue_declare($queName,false,true,false,false,false,$args);
$channel->queue_declare($deadLetterQueName,false,true,false,false);
//绑定
$channel->queue_bind($queName,$exName,$key);
$channel->queue_bind($deadLetterQueName,$deadLetterExName,$deadLetterKey);
//消息参数
$data = [
    'product_id' => 80,
    'product_name' => 'success',
];

$args1 = new AMQPTable();
$args1->set('x-delay',3000);

$message = new AMQPMessage(json_encode($data),[
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'content_type' => 'text/plain',
    // 'application_headers' => $args1,
    'expiration' => 3000
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

$channel->basic_publish($message,$exName,$key,true);
$channel->wait_for_pending_acks();
$channel->close();
$con->close();


