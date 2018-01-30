<?php
class a
{
    public $_test_attr = 123;
    public function testRedis()
    {
        $client = new swoole_redis;
        $client->connect('127.0.0.1', 6379, function (swoole_redis $client, $result) {
            echo "connect".$this->_test_attr.getmypid()."\n";
            $client->get('key', function (swoole_redis $client, $result) {
                echo $this->_test_attr.getmypid();
                var_dump($result);
                exit;
            });
        });
    }
}

$aSrv = new a();
$aSrv->testRedis();
echo "a finish".getmypid();
