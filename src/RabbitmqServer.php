<?php
namespace Zxy\PhpRabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
class RabbitmqServer
{
    const mqConf = [
        'host' => '192.168.100.63',
        'port' => '5672',
        'login' => 'admin',
        'passward' => '123456',
        'vhost' => '/'
    ];

    const DEAD_LETTER_EXCHANGE_TYPE = "direct";
    private $connection;
    private $channel;
    private $exchange_name;
    private $queue_name;
    private $routing_key;
    private $exchange_type;
    private $open_dead_letter;
    private $dead_letter_exchange;
    private $dead_letter_queue;
    private $dead_routing_key;
    private $mandatory = true;
    private $is_declare = true;
    private static $instance = null;
    private static $exchange_type_collection = [
        "direct",
        "topic",
        "fanout",
        "x-delayed-message"
    ];

    private function __construct($exchange_type,$exchange_name,$queue_name,$routing_key)
    {
        $args = func_get_args();
        foreach ($args as $val){
            if(empty($val)){
                throw new Exception(-1,'Params is Error');
            }
        }
        if(!in_array($exchange_type,self::$exchange_type_collection)){
            throw new Exception(-1,'Exchange type Error');
        }
        $this->connection = new AMQPStreamConnection(self::mqConf['host'],self::mqConf['port'],self::mqConf['login'],self::mqConf['passward'],self::mqConf['vhost']);
        $this->channel = $this->connection->channel();
        $this->exchange_type = $exchange_type;
        $this->exchange_name = $exchange_name;
        $this->queue_name = $queue_name;
        $this->routing_key = $routing_key;
    }

    private function __clone(){}

    public static function getInstance($exchange_type,$exchange_name,$queue_name,$routing_key)
    {
        if(empty(self::$instance)){
            self::$instance = new self($exchange_type,$exchange_name,$queue_name,$routing_key);
        }
       return self::$instance;
    }

    private function createExchange($exchange_name,$exchange_type,$args = [])
    {
        $this->channel->exchange_declare($exchange_name,$exchange_type,false,true,false,false,false,$args);
        return $this;
    }

    private function createQueue($queue_name,$args = [])
    {
        $this->channel->queue_declare($queue_name,false,true,false,false,false,$args);
        return $this;
    }

    public function createDeadLetterQueue($dead_letter_exchange,$dead_letter_queue,$dead_letter_routing_key)
    {
        $args = func_get_args();
        if(!empty($args) && count($args) == 3){
            $this->open_dead_letter = 1;
            $this->dead_letter_exchange = $dead_letter_exchange;
            $this->dead_letter_queue = $dead_letter_queue;
            $this->dead_routing_key = $dead_letter_routing_key;
        }
        return $this;
    }

    private function bindQueueExchange($queue_name,$exchange_name,$routing_key)
    {
        $this->channel->queue_bind($queue_name,$exchange_name,$routing_key);
        return $this;
    }

    private function getExchangeQueueBind($exchange_args=[])
    {
        if($this->is_declare){
            $args = [];
            if($this->open_dead_letter){
                //申明死信队列
                $this->createExchange($this->dead_letter_exchange,self::DEAD_LETTER_EXCHANGE_TYPE)
                    ->createQueue($this->dead_letter_queue)
                    ->bindQueueExchange($this->dead_letter_queue,$this->dead_letter_exchange,$this->dead_routing_key);
                $args = new AMQPTable();
                $args->set("x-dead-letter-exchange",$this->dead_letter_exchange);
                $args->set("x-dead-letter-routing-key",$this->dead_routing_key);
            }
            //申明普通队列
            $this->createExchange($this->exchange_name,$this->exchange_type,$exchange_args)
                ->createQueue($this->queue_name,$args)
                ->bindQueueExchange($this->queue_name,$this->exchange_name,$this->routing_key)
                ->addListener();
            $this->is_declare = false;
        }
        return $this;
    }

    private function addListener()
    {
        $this->channel->confirm_select();
        $this->channel->set_ack_handler(function (AMQPMessage $message){
            echo "投递成功:".$message->body.PHP_EOL;
        });
        $this->channel->set_nack_handler(function (AMQPMessage $message){
            echo "投递失败:".$message->body.PHP_EOL;
        });
        if($this->mandatory){
            $this->channel->set_return_listener(function ($replyCode, $replyText, $exchange, $routingKey, $message){
                $msg  = 'oh hoo！发生错误了'.PHP_EOL.'错误码：'.$replyCode.PHP_EOL.'错误信息：'.$replyText.PHP_EOL.'指定的交换机：'.$exchange.PHP_EOL.'指定的路由键：'.$routingKey.PHP_EOL.'投递的消息：'.$message->body.PHP_EOL;
                print_r($msg);
            });
        }
    }

    /**
     * @param $data
     * @return void
     * 发送消息
     */
    public function push($data)
    {
        $this->getExchangeQueueBind();
        $push_data = is_array($data) ? json_encode($data,JSON_UNESCAPED_UNICODE) : $data;
        $message = new AMQPMessage($push_data,[
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        $this->channel->basic_publish($message,$this->exchange_name,$this->routing_key,$this->mandatory);
        $this->channel->wait_for_pending_acks();
    }

    /**
     * @param $data
     * @param $time
     * @return void
     * 基于死信队列的延时消息
     */
    public function pushExpiration($data,$time)
    {
        if(empty($this->open_dead_letter)){
            throw new Exception('Please call createDeadLetterQueue func first',-1);
        }
        $this->getExchangeQueueBind();
        $push_data = is_array($data) ? json_encode($data,JSON_UNESCAPED_UNICODE) : $data;
        $message = new AMQPMessage($push_data,[
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'expiration' => $time * 1000
        ]);
        $this->channel->basic_publish($message,$this->exchange_name,$this->routing_key,$this->mandatory);
        $this->channel->wait_for_pending_acks();
    }

    private function getDelayArgs()
    {
        $args = new AMQPTable();
        $args->set("x-delayed-type","direct");
        return $args;
    }

    /**
     * @param $data
     * @param $time
     * @return void
     * 发送延迟消息
     */
    public function pushDelay($data,$time)
    {
        $args = $this->getDelayArgs();
        $this->exchange_type = "x-delayed-message";
        $this->getExchangeQueueBind($args);
        $push_data = is_array($data) ? json_encode($data,JSON_UNESCAPED_UNICODE) : $data;
        $message = new AMQPMessage($push_data,[
            'content_type' => 'text/plain',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable(['x-delay'=>$time * 1000])
        ]);
        $this->channel->basic_publish($message,$this->exchange_name,$this->routing_key,$this->mandatory);
        $this->channel->wait_for_pending_acks();
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    private function ack($message)
    {
        return $this->channel->basic_ack($message->delivery_info['delivery_tag']);
    }

    private function nack($message)
    {
        return $this->channel->basic_nack($message->delivery_info['delivery_tag'],false,true);
    }

    /**
     * @param $callback
     * @param $prefetch_count
     * @return void
     * @throws ErrorException
     * 消息消费
     */
    public function consume($callback = null,$prefetch_count = 1)
    {
        $this->getExchangeQueueBind();
        $this->channel->basic_qos(null,$prefetch_count,null);
        $callbackReturn = function (AMQPMessage $message)use ($callback){
            $return = $callback($message->body);
            var_dump($return);
            if($return){
                $this->ack($message);
            }else{
                //重回队列，后期优化为投递到死信队列
                $this->nack($message);
            }
        };
        $this->channel->basic_consume($this->queue_name,'',false,false,false,false,$callbackReturn);
        while (count($this->channel->callbacks)){
            $this->channel->wait();
        }
    }

    /**
     * @param $callback
     * @param $prefetch_count
     * @return void
     * @throws ErrorException
     * 死信队列消费
     */
    public function consumeDeadLetter($callback = null,$prefetch_count = 1)
    {
        $this->open_dead_letter = 0;
        $this->exchange_name = $this->dead_letter_exchange;
        $this->queue_name = $this->dead_letter_queue;
        $this->routing_key = $this->dead_routing_key;
        $this->getExchangeQueueBind();
        $this->channel->basic_qos(null,$prefetch_count,null);
        $callbackReturn = function (AMQPMessage $message)use ($callback){
            $return = $callback($message->body);
            var_dump($return);
            if($return){
                $this->ack($message);
            }else{
                //重回队列，后期优化为投递到死信队列
                $this->nack($message);
            }
        };
        $this->channel->basic_consume($this->queue_name,'',false,false,false,false,$callbackReturn);
        while (count($this->channel->callbacks)){
            $this->channel->wait();
        }
    }

    /**
     * @param $callback
     * @param $prefetch_count
     * @return void
     * 延迟消息消费
     */
    public function consumeDelay($callback = null,$prefetch_count = 1)
    {
        $args = $this->getDelayArgs();
        $this->exchange_type = "x-delayed-message";
        $this->getExchangeQueueBind($args);
        $this->channel->basic_qos(null,$prefetch_count,null);
        $callbackReturn = function (AMQPMessage $message)use ($callback){
            $return = $callback($message->body);
            var_dump($return);
            if($return){
                $this->ack($message);
            }else{
                //重回队列，后期优化为投递到死信队列
                $this->nack($message);
                // $this->channel->basic_reject($message->delivery_info['delivery_tag'],false,true);
            }
        };
        $this->channel->basic_consume($this->queue_name,'',false,false,false,false,$callbackReturn);
        while (count($this->channel->callbacks)){
            $this->channel->wait();
        }
    }


}