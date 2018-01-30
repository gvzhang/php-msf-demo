<?php
/**
* @file Money.php
* @brief 红包任务
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version 1.0
* @date 2018-01-29
*/

namespace App\Tasks;

use \PG\MSF\Tasks\Task;
use App\Tasks\AMQP as AMQPTask;

/**
 * Class Money
 * @package App\Tasks
 */
class Money extends Task
{
    private $_response;

    private $_corr_id;

    /**
     * @brief get 获取拆分红包
     *
     * @return
     */
    public function get($uid, $mid)
    {
        $this->_response = null;
        $this->_corr_id = "money-get-".uniqid();
        $routingKey = "money-get";
        $callbackKey = "money-callback".uniqid();
        $rabbit = $this->getObject(AMQPTask::class, ['rabbit', $callbackKey, null, AMQP_AUTODELETE | AMQP_DURABLE]);
        $res = $rabbit->publish(json_encode(['uid' => $uid, 'mid'=>$mid]), $routingKey, AMQP_NOPARAM, ["correlation_id"=>$this->_corr_id, "reply_to"=>$callbackKey]);
        if ($res) {
            $rabbit->consume([$this, "GetResponse"], AMQP_NOPARAM);
            $rabbit->disconnect();
            $res = $this->_response;
        }
        return $res;
    }

    public function GetResponse($envelope, $queue)
    {
        $deliveryTag = $envelope->getDeliveryTag();
        if ($envelope->getCorrelationId() == $this->_corr_id) {
            $this->_response = $envelope->getBody();
            $queue->ack($deliveryTag);
            return false;
        } else {
            $queue->nack($deliveryTag, AMQP_REQUEUE);
        }
    }
}
