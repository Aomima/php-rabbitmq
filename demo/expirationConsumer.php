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

$con = $connection = new AMQPStreamConnection($config['host'],$config['port'],$config['login'],$config['passward'],$config['vhost'],false,'AMQPLAIN',null,'en_US',3.0,3.0,null,false,1);
$channel = $con->channel();
//交换机类型
$type = "direct";
//交换机名称
// $exName = "normal_ex";
$deadLetterExName = "dead_letter_ex_expiration";
//队列名称
// $queName = "normal_queue";
$deadLetterQueName = "dead_letter_que_expiration";
//路由键名称
// $key = "normal_key";
$deadLetterKey = "dead_letter_key_expiration";
//声明交换机
// $channel->exchange_declare($exName,$type,false,true,false);
$channel->exchange_declare($deadLetterExName,$type,false,true,false);
//声明死信队列参数
// $args = new AMQPTable();
// $args->set('x-message-ttl',10000);
// $args->set('x-dead-letter-exchange',$deadLetterExName);
// $args->set('x-dead-letter-routing-key',$deadLetterKey);
//声明队列
// $channel->queue_declare($queName,false,true,false,false,false,$args);
$channel->queue_declare($deadLetterQueName,false,true,false,false);
//绑定
// $channel->queue_bind($queName,$exName,$key);
$channel->queue_bind($deadLetterQueName,$deadLetterExName,$deadLetterKey);
//公平调度
$channel->basic_qos(null,1,null);
//消费回调
$calback = function (AMQPMessage $message){
    echo "延时消息为:".$message->body.PHP_EOL;
    //手动应答
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
};

$channel->basic_consume($deadLetterQueName,'',false,false,false,false,$calback);
while (count($channel->callbacks)){
    $channel->wait();
}
$channel->close();
$con->close();
