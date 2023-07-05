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
$channel->basic_qos(null,1,null);
$callback = function (AMQPMessage $message) use ($channel){
    echo "收到延迟交换机延迟消息".$message->body.PHP_EOL;
    $channel->basic_ack($message->delivery_info['delivery_tag'],false);
};
$channel->basic_consume($que,'',false,false,false,false,$callback);
while (count($channel->callbacks)){
    $channel->wait();
}
$channel->close();
$con->close();