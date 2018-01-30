<?php
/**
* @file transaction-queue-publish.php
* @brief 事务操作-生产者
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

$k_route = "q-transaction-test";

try {
    //创建连接和channel
    $conn = new AMQPConnection($conn_args);
    if (!$conn->connect()) {
        die("Cannot connect to the broker!\n");
    }
    $channel = new AMQPChannel($conn);

    //消息内    容
    $message = "TEST MESSAGE! 测试消息！";

    //创建交换机对象
    $ex = new AMQPExchange($channel);

    //发送消息
    echo "Send Message0:".$ex->publish($message, $k_route, AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE]).time()."\n";

    $channel->startTransaction();
    echo "Send Message1:".$ex->publish($message, $k_route, AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE]).time()."\n";
    echo "Send Message2:".$ex->publish($message, $k_route, AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE]).time()."\n";
    echo "Send Message3:".$ex->publish($message, $k_route, AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE]).time()."\n";
    sleep(5);
    $channel->commitTransaction();
    //$channel->rollbackTransaction();

    $conn->disconnect();
} catch (\Exception $e) {
    echo "transaction error:".$e->getMessage()."\n";
}
echo "transaction finish";
