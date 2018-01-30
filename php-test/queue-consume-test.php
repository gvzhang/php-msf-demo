<?php
/*************************************
 * PHP amqp(RabbitMQ) Demo - consumer
 * Author: Linvo
 * Date: 2012/7/30
 *************************************/
//配置信息
$conn_args = array(
    'host' => 'rabbitmq',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);
$k_route = 'money-callback5a6de2d14dbec'; //路由key

//创建连接和channel
$conn = new AMQPConnection($conn_args);
if (!$conn->connect()) {
    die("Cannot connect to the broker!\n");
}
$channel = new AMQPChannel($conn);

//创建队列
try{
    $q = new AMQPQueue($channel);
    $q->setName($k_route);
    $q->setFlags(AMQP_NOPARAM);
    echo "Message Total:".$q->declareQueue()."\n";
}catch(\Exception $e){
    echo "error queue:".$e->getMessage()."\n";
}

$testVal = 1;

//阻塞模式接收消息
echo "Message:\n";
$q->consume('processMessage');
//$q->consume('processMessage', AMQP_AUTOACK); //自动ACK应答

/**
 * 消费回调函数
 * 处理消息
 */
function processMessage($envelope, $queue) {
    global $testVal;
    $msg = $envelope->getBody();
    echo $testVal."---".$msg."\n"; //处理消息
    $testVal++;
    echo $envelope->getDeliveryTag()."\n";
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
