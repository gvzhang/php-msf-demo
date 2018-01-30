<?php
/**
* @file dead-letter.php
* @brief 死信操作
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version 1.0
* @date 2018-01-30
*/

try {
    $conn_args = array(
    'host' => 'rabbitmq',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);
    $cnn = new AMQPConnection($conn_args);
    $cnn->connect();

    $ch = new AMQPChannel($cnn);

    //$suffix = sha1(microtime(true));
    $suffix = "5ea189c3dcb578a763a3a1000a04faf6d6dccc37";

    $dlx = new AMQPExchange($ch);
    $dlx->setName('dlx-' . $suffix);
    $dlx->setType(AMQP_EX_TYPE_TOPIC);
    $dlx->setFlags(AMQP_DURABLE);
    $dlx->declareExchange();

    $dq = new AMQPQueue($ch);
    $dq->setName('dlx-' . $suffix);
    $dq->declareQueue();
    $dq->setFlags(AMQP_DURABLE);
    $dq->bind($dlx->getName(), '#');

    $ex = new AMQPExchange($ch);
    $ex->setName("exchange-" . $suffix);
    $ex->setType(AMQP_EX_TYPE_FANOUT);
    $ex->setFlags(AMQP_DURABLE);
    $ex->declareExchange();

    $q = new AMQPQueue($ch);
    $q->setName('dlx-test-queue-' . $suffix);
    $q->setFlags(AMQP_DURABLE);
    // 设置消息被nack或者reject后的存活时间
    $q->setArgument('x-message-ttl', 10000);
    $q->setArgument('x-dead-letter-exchange', $dlx->getName());
    $q->declareQueue();
    $q->bind($ex->getName());

    $ex->publish('message');

    $envelope = $q->get();
    $q->nack($envelope->getDeliveryTag(), AMQP_REQUEUE);
} catch (\Exception $e) {
    echo "dead letter error:".$e->getMessage()."\n";
}
echo "dead finish\n";
