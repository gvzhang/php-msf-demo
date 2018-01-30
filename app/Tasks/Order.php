<?php

namespace App\Tasks;

use \PG\MSF\Tasks\Task;
use App\Tasks\Order as OrderTask;

/**
 * Class Demo
 * @package App\Tasks
 */
class Order extends Task
{
    /**
     * @brief _getOrderNo 生成订单号
     *
     * @return
     */
    private function _getOrderNo()
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);

        $dateTime = date("YmdHis"); // 格式化当前时间戳
        $reqNoKey = 'sk:order:order_no:'.$dateTime; // 设置redis键值，每秒钟的请求次数
        $reqNo = $redis->incr($reqNoKey); // 将redis值加1
        $redis->expire($reqNoKey, 5); // 设置redis过期时间,避免垃圾数据过多
        $reqNo = 10000 + $reqNo; // 补齐订单号长度
        return $dateTime  . $reqNo; // 生成订单号
    }

    public function processMessage($envelope, $queue)
    {
        $isSuccess = false;
        $deliveryTag = $envelope->getDeliveryTag();
        $data = json_decode($envelope->getBody());
        if (isset($data->uid) && isset($data->gid) && $data->uid && $data->gid) {
            $uid = $data->uid;
            $gid = $data->gid;

            $config = getInstance()->config['mysql']['master']??[];
            if (empty($config)) {
                getInstance()->log->error("数据库配置有误");
            } else {
                $mysqli = null;
                $stmt = null;
                try {
                    // db connection
                    $mysqli = new \mysqli($config["host"], $config["user"], $config["password"], $config["database"]);
                    if ($mysqli->connect_errno) {
                        getInstance()->log->error("Connection Failed: [".$mysqli->connect_errno. "] : ".$mysqli->connect_error);
                    } else {
                        $error = "";
                        $orderNo = $this->_getOrderNo();
                        $curStock = 0;
                        $mysqli->autocommit(false);
                        //if ($stmt = $mysqli->prepare("SELECT * FROM sk_goods WHERE id=?")) {
                        if ($stmt = $mysqli->prepare("SELECT * FROM sk_goods WHERE id=? FOR UPDATE")) {
                            $stmt->bind_param("i", $gid);
                            $exe_result = $stmt->execute();
                            $stmt_result = $stmt->get_result();
                            if ($exe_result) {
                                $row_data = $stmt_result->fetch_assoc();
                                if ($row_data) {
                                    $curStock = $row_data["stock"];
                                    if ($curStock > 0) {
                                        $updateStock = $curStock - 1;
                                        $stmt = $mysqli->prepare("INSERT INTO sk_order (order_no, goods_id, user_id) VALUES (?, ?, ?)");
                                        $stmt->bind_param("sis", $orderNo, $gid, $uid);
                                        $exe_result = $stmt->execute();
                                        if ($exe_result && $mysqli->affected_rows>0) {
                                            //$stmt = $mysqli->prepare("UPDATE sk_goods SET stock = ? WHERE id = ? AND stock = ?");
                                            //$stmt->bind_param("iii", $updateStock, $gid, $curStock);
                                            $stmt = $mysqli->prepare("UPDATE sk_goods SET stock = ? WHERE id = ?");
                                            $stmt->bind_param("ii", $updateStock, $gid);
                                            $exe_result = $stmt->execute();
                                            if ($exe_result && $mysqli->affected_rows>0) {
                                                $isSuccess = true;
                                                getInstance()->log->error("消费成功 OrderNo:".$orderNo);
                                            }
                                        }
                                    } else {
                                        $isSuccess = true;
                                        getInstance()->log->error("消费成功 库存不足");
                                    }
                                }
                            }
                        }
                        if ($isSuccess) {
                            $mysqli->commit();
                        } else {
                            $mysqli->rollback();
                            $error = "mysqli_error:".$mysqli->error."  stmt_error:".($stmt?$stmt->error:"");
                            getInstance()->log->error("事务提交失败  ".$error);
                        }
                    }
                } catch (Exception $e) {
                    getInstance()->log->error($e->getMessage(). " <pre>".$e->getTraceAsString()."</pre>");
                }
                $stmt && $stmt->close();
                $mysqli && $mysqli->close();
            }
        }
        if ($isSuccess) {
            $queue->ack($deliveryTag); //手动发送ACK应答
        } else {
            $queue->nack($deliveryTag, AMQP_REQUEUE); //手动发送ACK应答
            getInstance()->log->error("order submit failed");
        }
    }
}
