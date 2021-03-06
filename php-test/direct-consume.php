<?php
/**
* @file direct-consume.php
* @brief 直连交换机-消费者
* @brief 直连交换机是一种带路由功能的交换机，一个队列会和一个交换机绑定，
* @brief 除此之外再绑定一个routing_key，当消息被发送的时候，需要指定一个binding_key，
* @brief 这个消息被送达交换机的时候，就会被这个交换机送到指定的队列里面去。
* @brief 同样的一个binding_key也是支持应用到多个队列中的。
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

$severities = array_slice($argv, 1);
if (empty($severities)) {
    echo "severities error\n";
    exit(1);
}

$e_name = 'e-route-test'; //交换机名

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    $ex = new AMQPExchange($channel);
    $ex->setName($e_name);
    $ex->setFlags(AMQP_DURABLE);
    $ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
    echo "Exchange Status:".$ex->declare()."\n";

    //创建队列
    $q = new AMQPQueue($channel);
    $q->setFlags(AMQP_DURABLE); //持久化
    echo "Message Total:".$q->declare()."\n";

    foreach ($severities as $severity) {
        //绑定交换机与队列，并指定路由键
        echo 'Queue Bind: '.$q->bind($ex->getName(), $severity)."\n";
    }

    //阻塞模式接收消息
    echo "Message:\n";
    $q->consume('processMessage');
} catch (\Exception $e) {
    echo "consume error:".$e->getMessage()."\n";
}
echo "consume finish";

/**
 * 消费回调函数
 * 处理消息
 */
function processMessage($envelope, $queue)
{
    $msg = $envelope->getBody();
    echo $msg.time()."\n"; //处理消息
    usleep(300000);
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
