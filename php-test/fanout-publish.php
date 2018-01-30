<?php
/**
* @file fanout-publish.php
* @brief 扇形交换机-生产者
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

try {
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
    $ex->setFlags(AMQP_DURABLE);
    $ex->setType(AMQP_EX_TYPE_FANOUT);
    echo "Exchange Status:".$ex->declare()."\n";

    //发送消息
    while (true) {
        echo time()."Send Message:".$ex->publish($message, "", AMQP_NOPARAM, ['delivery_mode'=>AMQP_DURABLE])."\n";
        usleep(100000);
    }

    $conn->disconnect();
} catch (\Exception $e) {
    echo "publish error:".$e->getMessage()."\n";
}
echo "publish finish \n";
