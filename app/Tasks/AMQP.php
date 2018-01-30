<?php
/**
* @file AMQP.php
* @brief AMQP Task
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version 1.0
* @date 2018-01-28
 */

namespace App\Tasks;

use \PG\MSF\Tasks\Task;

class AMQP extends Task
{
    /**
     * @var array 当前要使用的amqp配置, [配置名, routing_key名]
     */
    protected $amqpConf = [];
    /**
     * @var array 全局amqpMQ配置
     */
    protected $config;

    /** @var \AMQPConnection */
    protected $amqpConnection;

    /** @var \AMQPChannel */
    protected $amqpChannel;

    /** @var \AMQPExchange */
    protected $amqpExchange;

    /** @var \AMQPQueue */
    protected $amqpQueue;

    /**
     * 构造方法 检测连接
     * AMQPTask constructor.
     * @param array ...$amqpConf
     * @throws Exception
     */
    public function __construct(...$amqpConf)
    {
        if ($amqpConf) {
            $this->amqpConf = $amqpConf;
        } elseif (empty($this->amqpConf)) {
            throw new Exception('No $amqpConf in this class or no server config in $amqpConf');
        }

        if (!is_object($this->amqpConnection) || !$this->amqpConnection->isConnected()) {
            $this->prepare($this->amqpConf[0], $this->amqpConf[1] ?? 'default', isset($this->amqpConf[2])?$this->amqpConf[2]:null, isset($this->amqpConf[3])?$this->amqpConf[3]:null);
        }

        parent::__construct();
    }

    /**
     * 长连接
     * @param string $confKey
     * @param string $routing_key
     * @throws \AMQPException
     */
    public function prepare(string $confKey, string $routing_key, int $exchange_flags = null, int $queue_flags = null)
    {
        $this->config = getInstance()->config['amqp'] ?? [];
        if (!isset($this->config[$confKey])) {
            throw new \AMQPException('No such a amqpMQ config ' . $confKey);
        }
        $conf = $this->config[$confKey];
        $this->amqpConnection = new \AMQPConnection([
            'host'            => $conf['host']              ??  'localhost',
            'port'            => $conf['port']              ??  '5672',
            'vhost'           => $conf['vhost']             ??  '/',
            'login'           => $conf['login']             ??  'guest',
            'password'        => $conf['password']          ??  'guest',
            'read_timeout'    => $conf['read_timeout']      ??  0,
            'write_timeout'   => $conf['write_timeout']     ??  0,
            'connect_timeout' => $conf['connect_timeout']   ??  0,
            'channel_max'     => $conf['channel_max']       ??  256,
            'frame_max'       => $conf['frame_max']         ??  131072,
            'heartbeat'       => $conf['heartbeat']         ??  0,
            'cacert'          => $conf['cacert']            ??  null,
            'key'             => $conf['key']               ??  null,
            'cert'            => $conf['cert']              ??  null,
            'verify'          => $conf['verify']            ??  1
        ]);

        //Establish connection AMQP
        $this->amqpConnection->pconnect();

        //Create and declare channel
        $this->amqpChannel = new \AMQPChannel($this->amqpConnection);

        //AMQP Exchange is the publishing mechanism
        $this->amqpExchange = new \AMQPExchange($this->amqpChannel);
        if ($exchange_flags !== null) {
            $this->amqpExchange->setFlags($exchange_flags);
        }

        //Declare Queue
        $this->amqpQueue = new \AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName($routing_key);
        if ($queue_flags !== null) {
            $this->amqpQueue->setFlags($queue_flags);
        }
        $this->amqpQueue->declareQueue();
    }

    /**
     * 发布消息
     * @param string $message
     * @param string $routing_key
     * @param flags 屏蔽字：AMQP_NOPARAM AMQP_MANDATORY AMQP_IMMEDIATE
     * @param attributes 附加属性
     * @return bool
     */
    public function publish(string $message, string $routing_key = 'default', int $flags = AMQP_NOPARAM, array $attributes = [])
    {
        return $this->amqpExchange->publish($message, $routing_key, $flags, $attributes);
    }

    /**
     * 读取消息
     * @param integer $flags get方法屏蔽字：AMQP_NOPARAM AMQP_AUTOACK
     * @return \AMQPEnvelope|bool
     */
    public function get($flags = AMQP_AUTOACK)
    {
        return $this->amqpQueue->get($flags);
    }

    /**
     * 消费消息
     * @param callable $callback 回调函数
     * @param integer $flags 屏蔽字：AMQP_NOPARAM AMQP_AUTOACK AMQP_JUST_CONSUME
     * @param int $autoAck 是否在MQ中清除
     */
    public function consume(callable $callback, $flags = AMQP_AUTOACK)
    {
        $this->amqpQueue->consume($callback, $flags);
    }

    /**
     * 告知MQ如何处理消息
     * 在消费未开启 autoAck 时，需要调用此方法。
     * @param $delivery_tag
     * @param int $type 1表示消费成功可以删除消息 2表示消费失败，重新放回队列  3表示消费失败，放弃处理
     * @return bool
     * @throws \AMQPQueueException
     */
    public function acknowledge($delivery_tag, $type = 1, $flags = AMQP_NOPARAM)
    {
        if ($type == 1) {
            //确认消息
            return $this->amqpQueue->ack($delivery_tag, $flags);
        } elseif ($type == 2) {
            //恢复消息
            return $this->amqpQueue->nack($delivery_tag, $flags);
        } elseif ($type == 3) {
            //确认取消
            return $this->amqpQueue->reject($delivery_tag, $flags);
        } else {
            throw new \AMQPQueueException('Undefined Acknowledge type');
        }
    }

    /**
     * 关闭持久连接，避免每次构造参数不同的情况下，
     * 获取的是同一个对象，导致函数处理不正确的情况。
     *
     * @return
     */
    public function disconnect()
    {
        return $this->amqpConnection->pdisconnect();
    }
}
