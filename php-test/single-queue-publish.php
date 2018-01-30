<?php
/**
* @file single-queue-publish.php
* @brief 单队列-生产者
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

$k_route = "q_test";

//创建连接和channel
$conn = new AMQPConnection($conn_args);
if (!$conn->connect()) {
    echo "Cannot connect tot broker!\n";
    die("Cannot connect to the broker!\n");
}
$channel = new AMQPChannel($conn);

//消息内容
$message = "TEST MESSAGE! 测试消息！";

//创建交换机对象
$ex = new AMQPExchange($channel);

//发送消息
while (true) {
    echo "Send Message:".$ex->publish($message, $k_route, AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE]).time()."\n";
    usleep(100000);
}

$conn->disconnect();
