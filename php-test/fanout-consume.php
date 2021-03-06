<?php
/**
* @file fanout-consume.php
* @brief 扇形交换机-消费者
* @brief 扇形交换机是最基本的交换机类型，它所能做的事情非常简单———广播消息。
* @brief 扇形交换机会把能接收到的消息全部发送给绑定在自己身上的队列。
* @brief 因为广播不需要“思考”，所以扇形交换机处理消息的速度也是所有的交换机类型里面最快的。
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
$e_name = 'e-pub-test'; //交换机名
$q_name = 'q-pub-test';

$num = array_slice($argv, 1);
if(empty($num)){
    echo "num param error\n";
    exit;
}
$q_name = $q_name.$num[0];

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    //创建交换机
    $ex = new AMQPExchange($channel);
    $ex->setName($e_name);
    $ex->setFlags(AMQP_DURABLE);
    $ex->setType(AMQP_EX_TYPE_FANOUT); //direct类型
    echo "Exchange Status:".$ex->declare()."\n";

    //创建队列
    $q = new AMQPQueue($channel);
    $q->setName($q_name);
    $q->setFlags(AMQP_DURABLE); //持久化
    echo "Message Total:".$q->declare()."\n";

    //绑定交换机与队列，并指定路由键
    echo 'Queue Bind: '.$q->bind($e_name, "")."\n";

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
    usleep(300000);
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
