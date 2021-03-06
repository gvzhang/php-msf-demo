<?php
/**
* @file direct-publish.php
* @brief 直连交换机-发布者
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

$e_name = 'e-route-test'; //交换机名

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
    $ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
    echo "Exchange Status:".$ex->declare()."\n";

    //发送消息
    while (true) {
        $severity = mt_rand(0, 1)?"error":"info";
        $data = "send ".$severity;
        echo time()."Send Message:".$ex->publish($data, $severity, AMQP_NOPARAM, ["delivery_mode"=>AMQP_DURABLE])."\n";
        usleep(100000);
    }

    $conn->disconnect();
} catch (\Exception $e) {
    echo "publish error:".$e->getMessage()."\n";
}
echo "publish finish\n";
