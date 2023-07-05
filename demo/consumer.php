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
$exchangeName = "test_exchange1";
$type = "direct";
$channel->exchange_declare($exchangeName,$type,false,true,false);
$queueName = "test_queue1";
$channel->queue_declare($queueName,false,true,false,false);
$routingKey = "test_routing_key1";
$channel->queue_bind($queueName,$exchangeName,$routingKey);
//公平调度
$channel->basic_qos(null,1,null);
//no_ack false 开启手动应答 $channel->basic_consume(队列名,消费者标签,AMQP的标准RabbitMQ没有实现，收到消息后是否不需要回复，即认为被消费，排他消费，即这个队列只能由一个消费者消费，不返回执行结果，回调函数，，参数)
$callback = function (AMQPMessage $message){
    echo "消息为:".$message->body.PHP_EOL;
    //手动应答
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
};
$channel->basic_consume($queueName,'',false,false,false,false,$callback);
while (count($channel->callbacks)){
    $channel->wait();
}
$channel->close();
$connection->close();