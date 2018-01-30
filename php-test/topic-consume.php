<?php
/**
* @file topic-consume.php
* @brief 主题交换机-消费者
* @brief 主题交换机的routing_key需要有一定的规则，交换机和队列的binding_key需要采用*.#.*.....的格式，每个部分用.分开，其中：
* @brief *表示一个单词
* @brief #表示任意数量（零个或多个）单词。
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

$binding_keys = array_slice($argv, 1);
if (empty($binding_keys)) {
    echo "please enter binging key\n";
    exit(1);
}

$e_name = 'e-topic-test'; //交换机名

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
    $ex->setType(AMQP_EX_TYPE_TOPIC); //direct类型
    echo "Exchange Status:".$ex->declare()."\n";

    //创建队列
    $q = new AMQPQueue($channel);
    $q->setFlags(AMQP_DURABLE); //持久化
    echo "Message Total:".$q->declare()."\n";

    foreach ($binding_keys as $key) {
        //绑定交换机与队列，并指定路由键
        echo 'Queue Bind: '.$q->bind($ex->getName(), $key)."\n";
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
