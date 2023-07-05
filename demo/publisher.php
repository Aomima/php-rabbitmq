<?php
include  __DIR__ ."/../vendor/autoload.php";
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
$config = [
    'host' => '192.168.100.61',
    'port' => '5672',
    'login' => 'admin',
    'passward' => '123456',
    'vhost' => '/'
];
$connection = new AMQPStreamConnection($config['host'],$config['port'],$config['login'],$config['passward'],$config['vhost']);
$channel = $connection->channel();
//开启发布确认模式
$channel->confirm_select();
//推送成功回调
$channel->set_ack_handler(function(AMQPMessage $message){
    echo "set_ack_handler消息发送成功回调：".$message->body.PHP_EOL;
});
//推送失败回调
$channel->set_nack_handler(function(AMQPMessage $message){
    echo "set_nack_handler消息发送失败回调：".$message->body.PHP_EOL;
});
$channel->set_return_listener(function ($replyCode, $replyText, $exchange, $routingKey, $message){
    $msg  = 'oh hoo！发生错误了'.PHP_EOL;
    $msg .= '错误码：'.$replyCode.PHP_EOL;
    $msg .= '错误信息：'.$replyText.PHP_EOL;
    $msg .= '指定的交换机：'.$exchange.PHP_EOL;
    $msg .= '指定的路由键：'.$routingKey.PHP_EOL;
    $msg .= '投递的消息：'.$message->body.PHP_EOL;
    print_r($msg);
});
$exchangeName = "test_exchange1";
$type = "direct";
//初始化交换机        channel->exchange_declare(交换机名称, 推送类型, 是否检测同名队列, 是否开启队列持久化, 通道关闭后是否删除队列);
$channel->exchange_declare($exchangeName,$type,false,true,false);
//初始化队列         $channel->queue_declare(队列名称, 是否检测同名队列, 是否开启队列持久化, 队列是否可以被其他队列访问, 通道关闭后是否删除队列);
$queueName = "test_queue1";
$channel->queue_declare($queueName,false,true,false,false);
//队列与交换机绑定
$routingKey = "test_routing_key1";
$channel->queue_bind($queueName,$exchangeName,$routingKey);
$data = [
    'id' => 96,
    'msg' => 'successful'
];
$message = new AMQPMessage(json_encode($data),[
    'content_type' => 'text/plain',
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
]);
//mandatory 设置为true，则开启return机制
$channel->basic_publish($message,$exchangeName,$routingKey,true);
$channel->wait_for_pending_acks();
$channel->close();
$connection->close();






