<?php
/*************************************
 * PHP amqp(RabbitMQ) Demo - publisher
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
$e_name = 'direct_logs'; //交换机名
$k_route1 = 'key_direct_logs1'; //路由key
$k_route2 = 'key_direct_logs2'; //路由key
$k_route3 = 'key_direct_logs3'; //路由key

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
$ex->setName($e_name);
$ex->setType(AMQP_EX_TYPE_DIRECT);
echo "Exchange Status:".$ex->declare()."\n";

//发送消息
//$channel->startTransaction(); //开始事务
for ($i=0; $i<5; ++$i) {
    echo "Send Message1:".$ex->publish($message."1", $k_route1)."\n";
    echo "Send Message2:".$ex->publish($message."2", $k_route2)."\n";
    echo "Send Message3:".$ex->publish($message."3", $k_route3)."\n";
}
//$channel->commitTransaction(); //提交事务

$conn->disconnect();
