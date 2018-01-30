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
$e_name = 'e_logs'; //交换机名
$k_route = ''; //路由key

//创建连接和channel
$conn = new AMQPConnection($conn_args);
if (!$conn->connect()) {
    die("Cannot connect to the broker!\n");
}
$channel = new AMQPChannel($conn);

//创建交换机
$ex = new AMQPExchange($channel);
$ex->setName($e_name);
$ex->setType(AMQP_EX_TYPE_FANOUT); //direct类型
echo "Exchange Status:".$ex->declare()."\n";

//创建队列
$q = new AMQPQueue($channel);
$q->setName("pub-sub-test-queue");
$q->setFlags(AMQP_DURABLE); //持久化
echo "Message Total:".$q->declare()."\n";

//绑定交换机与队列，并指定路由键
echo 'Queue Bind: '.$q->bind($e_name, $k_route)."\n";

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
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
