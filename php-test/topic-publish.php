<?php
/**
* @file topic-publish.php
* @brief 主题交换机-生产者
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

$e_name = 'e-topic-test'; //交换机名
$r_key = "kern.critical";

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    //创建交换机对象
    $ex = new AMQPExchange($channel);
    $ex->setName($e_name);
    $ex->setFlags(AMQP_DURABLE);
    $ex->setType(AMQP_EX_TYPE_TOPIC); //direct类型
    echo "Exchange Status:".$ex->declare()."\n";

    //发送消息
    while (true) {
        $data = "A critical kernel error";
        echo time()."Send Message:".$ex->publish($data, $r_key, AMQP_NOPARAM, ["delivery_mode"=>AMQP_DURABLE])."\n";
        usleep(100000);
    }

    $conn->disconnect();
} catch (\Exception $e) {
    echo "publish error:".$e->getMessage()."\n";
}
echo "publish finish\n";
