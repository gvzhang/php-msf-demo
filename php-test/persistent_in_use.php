<?php
$conn_args = array(
    'host' => 'rabbitmq',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);
$cnn = new AMQPConnection($conn_args);
echo get_class($cnn), PHP_EOL;
$cnn->connect();
echo $cnn->isConnected() ? 'true' : 'false', PHP_EOL;
echo PHP_EOL;

$cnn2 = new AMQPConnection($conn_args);
echo get_class($cnn), PHP_EOL;
try {
        $cnn2->connect();
            echo 'reused', PHP_EOL;

} catch (AMQPException $e) {
    echo get_class($e), "({$e->getCode()}): ", $e->getMessage(), PHP_EOL;

}
echo $cnn->isConnected() ? 'true' : 'false', PHP_EOL;
