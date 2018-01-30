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
//$e_name = 'e_test'; //交换机名
$q_name = 'q_test'; //队列名
//$k_route = 'key_1'; //路由key

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    //创建交换机
    //$ex = new AMQPExchange($channel);
    //$ex->setName($e_name);
    //$ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
    ////$ex->setFlags(AMQP_DURABLE); //持久化
    //echo "Exchange Status:".$ex->declare()."\n";

    //创建队列
    $q = new AMQPQueue($channel);
    $q->setName($q_name);
    $q->setFlags(AMQP_DURABLE); //持久化
    echo "Message Total:".$q->declare()."\n";

    //绑定交换机与队列，并指定路由键
    //echo 'Queue Bind: '.$q->bind($e_name, $k_route)."\n";

    //阻塞模式接收消息
    echo "Message:\n";

    $q->consume('processMessage');
} catch (\Exception $e) {
    echo "consume error:".$e->getMessage()."\n";
}
//$q->consume('processMessage', AMQP_AUTOACK); //自动ACK应答
echo "consume finish\n";

/**
 * 消费回调函数
 * 处理消息
 */
function processMessage($envelope, $queue)
{
    $msg = $envelope->getBody();
    usleep(300000);
    echo $msg.time()."\n"; //处理消息
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
