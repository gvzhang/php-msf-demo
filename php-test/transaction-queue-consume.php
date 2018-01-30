<?php
/**
* @file transaction-queue-consume.php
* @brief 事务操作-消费者
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version 1.0
* @date 2018-01-30
 */

$conn_args = array(
    'host' => 'rabbitmq',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);

$q_name = 'q-transaction-test'; //队列名

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    //创建队列
    $q = new AMQPQueue($channel);
    $q->setName($q_name);
    $q->setFlags(AMQP_DURABLE); //持久化
    echo "Message Total:".$q->declare()."\n";

    //阻塞模式接收消息
    echo "Message:\n";

    $q->consume('processMessage');
} catch (\Exception $e) {
    echo "consume error:".$e->getMessage()."\n";
}
echo "consume finish\n";

/**
 * 消费回调函数
 * 处理消息
 */
function processMessage($envelope, $queue)
{
    $msg = $envelope->getBody();
    echo $msg.time()."\n"; //处理消息
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
