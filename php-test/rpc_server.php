<?php
//配置信息
$conn_args = array(
    'host' => 'rabbitmq',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);

$q_name = "rpc_queue";

$conn = new AMQPConnection($conn_args);
if (!$conn->connect()) {
    die("Cannot connect to the broker!\n");
}
$channel = new AMQPChannel($conn);

$queue = new AMQPQueue($channel);
$queue->setName($q_name);
$queue->setFlags(AMQP_AUTODELETE);
$queue->declare();

try{
    $queue->consume("rpc_callback");
}catch(\Exception $e){
    echo $e->getMessage();
    exit;
}

function fib($n)
{
    if ($n == 0) {
        return 0;
    }
    if ($n == 1) {
        return 1;
    }
    return fib($n-1)+fib($n-2);
}

function rpc_callback($envelope, $queue)
{
    global $channel;
    $n = $envelope->getBody();

    $replyTo = $envelope->getReplyTo();
    $corelationId = $envelope->getCorrelationId();

    $exchange = new AMQPExchange($channel);
    $exchange->publish(fib($n), $replyTo, AMQP_NOPARAM, ["correlation_id"=>$corelationId]);

    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
