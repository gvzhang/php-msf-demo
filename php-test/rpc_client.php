<?php
class FibonacciRpcClient
{
    private $_connection;
    private $_channel;
    private $_exchange;
    private $_queue;
    private $_response;
    private $_corr_id;

    public function __construct()
    {
        $this->_connection = new AMQPConnection([
            "host"=>"rabbitmq", "port"=>5672,
            "login"=>"guest", "password"=>"guest", "vhost"=>"/"
        ]);
        if (!$this->_connection->connect()) {
            die("Cannot connect to the broker!\n");
        }

        $this->_channel = new AMQPChannel($this->_connection);
        $this->_exchange = new AMQPExchange($this->_channel);
        $this->_queue = new AMQPQueue($this->_channel);
        $this->_queue->declare();
    }

    public function on_response($envelope, $queue)
    {
        if ($envelope->getCorrelationId() == $this->_corr_id) {
            $this->_response = $envelope->getBody();
            return false;
        }
    }

    public function call($n)
    {
        $this->_response = null;
        $this->_corr_id = uniqid();

        $this->_exchange->publish($n, "rpc_queue", AMQP_NOPARAM, ["correlation_id"=>$this->_corr_id, "reply_to"=>$this->_queue->getName()]);
        $this->_queue->consume([$this, "on_response"]);
        return $this->_response;
    }
}

$fibonacci_rpc = new FibonacciRpcClient();
$response = $fibonacci_rpc->call(30);
echo "Got ".$response."\n";
