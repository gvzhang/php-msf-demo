<?php
namespace App\Console;

use App\Tasks\AMQP as AMQPTask;
use App\Tasks\Order as OrderTask;

class Batch extends Controller
{
    const SK_ORDER_NO_KEY = 'sk:order:order_no';

    private $_redisSrv;
    private $_mysqlSrv;
    private $_rabbitSrv;

    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
        $this->_mysqlSrv = $this->getMysqlPool('master');
        $this->_redisSrv = $this->getRedisPool('p1');
    }

    /**
     * @brief _getOrderNo 生成订单号
     *
     * @return
     */
    public function _getOrderNo()
    {
        $dateTime = date("YmdHis"); // 格式化当前时间戳
        $reqNoKey = self::SK_ORDER_NO_KEY.':'.$dateTime; // 设置redis键值，每秒钟的请求次数
        $reqNo = yield $this->_redisSrv->incr($reqNoKey); // 将redis值加1
        yield $this->_redisSrv->expire($reqNoKey, 5); // 设置redis过期时间,避免垃圾数据过多
        $reqNo = 10000 + $reqNo; // 补齐订单号长度
        return $dateTime  . $reqNo; // 生成订单号
    }

    // public function actionRun($arg="")
    // {
    //     // consume获取消息方式，无法结合协程
    //     //$orderTask = $this->getObject(OrderTask::class);
    //     //$this->_rabbitSrv = $this->getObject(AMQPTask::class, ['rabbit', 'q_linvo'.$arg]);
    //     //$this->_rabbitSrv->consume([$orderTask, 'processMessage'], AMQP_NOPARAM);

    //     // get获取消息方式, 结合协程处理方式
    //     $this->_rabbitSrv = $this->getObject(AMQPTask::class, ['rabbit', 'q_linvo'.$arg]);
    //     $count = 1;
    //     while ($count<=2000) {
    //         $envelope = $this->_rabbitSrv->get(AMQP_NOPARAM);
    //         if (false !== $envelope) {
    //             $isSuccess = false;
    //             $deliveryTag = $envelope->getDeliveryTag();
    //             $data = json_decode($envelope->getBody());
    //             if (isset($data->uid) && isset($data->gid) && $data->uid && $data->gid) {
    //                 $stock = 0;
    //                 $uid = $data->uid;
    //                 $gid = $data->gid;
    //                 $orderNo = yield $this->_getOrderNo();

    //                 // 开启一个事务，并返回事务ID
    //                 $id = yield $this->_mysqlSrv->goBegin();

    //                 // 悲观锁
    //                 $goodsInfo = yield $this->_mysqlSrv->go($id, "select * from sk_goods where id = ".$gid." for update");
    //                 if ($goodsInfo["result"]) {
    //                     $stock = $goodsInfo["result"][0]["stock"];
    //                     if ($stock>0) {
    //                         $stock--;
    //                         $orderRes = yield $this->_mysqlSrv->insert("sk_order")->set("order_no", $orderNo)->set("goods_id", $gid)->set("user_id", $uid)->go($id);
    //                         $stockRes = yield $this->_mysqlSrv->update('sk_goods')->set('stock', $stock)->where('id', $gid)->go($id);
    //                         if ($orderRes["result"] && $stockRes['result']) {
    //                             $isSuccess = true;
    //                         }
    //                     } else {
    //                         $isSuccess = true;
    //                     }
    //                 }

    //                 if ($isSuccess) {
    //                     yield $this->_mysqlSrv->goCommit($id);
    //                     $this->_rabbitSrv->acknowledge($deliveryTag, 1); //手动发送ACK应答
    //                     getInstance()->log->error("order submit success,". ($stock>0?"OrderNo:".$orderNo:"库存不足"));
    //                 } else {
    //                     yield $this->_mysqlSrv->goRollback($id);
    //                     $this->_rabbitSrv->acknowledge($deliveryTag, 2, AMQP_REQUEUE); //手动发送ACK应答
    //                     getInstance()->log->error("order submit failed");
    //                 }
    //                 $count++;
    //             } else {
    //                 getInstance()->log->error("params error");
    //             }
    //         }
    //     }
    // }


    /**
     * @brief getRandomMoney 获取下一拆分红包    的金额
     *
     * @param $remainSize
     * @param $remainMoney
     *
     * @return
     */
    public function getRandomMoney($remainSize, $remainMoney)
    {
        if ($remainSize == 1) {
            $divideMoney = $remainMoney;
        } else {
            $divideMoney = mt_rand(1, floor(($remainMoney*100/$remainSize)*2)-1);
            $divideMoney = round($divideMoney/100, 2);
        }
        return $divideMoney;
    }

    public function actionMoney()
    {
        $this->_rabbitSrv = $this->getObject(AMQPTask::class, ['rabbit', 'money-get', null, AMQP_DURABLE]);
        $count = 1;
        while ($count<=2000) {
            $envelope = $this->_rabbitSrv->get(AMQP_NOPARAM);
            if (false !== $envelope) {
                $deliveryTag = $envelope->getDeliveryTag();
                $replyTo = $envelope->getReplyTo();
                $corelationId = $envelope->getCorrelationId();
                $data = json_decode($envelope->getBody());
                $retVal = ["success"=>false, "data"=>"", "err_msg"=>""];
                if (isset($data->uid) && isset($data->mid) && $data->uid && $data->mid) {
                    $uid = $data->uid;
                    $mid = $data->mid;

                    $id = yield $this->_mysqlSrv->goBegin();
                    try {
                        $skMoneyRes = yield $this->_mysqlSrv->go($id, "select divide_count, balance from sk_money where id = ".$mid." and status=0 for update");
                        if (empty($skMoneyRes["result"])) {
                            throw new \Exception("无效的红包");
                        }
                        $isGetMoney = yield $this->_mysqlSrv->select('money_id')->from('sk_divide_money')->where('user_id', $uid)->andwhere("money_id", $mid)->go($id);
                        if ($isGetMoney["result"]) {
                            throw new \Exception("你已经抢过这个红包了");
                        }
                        $remainSize = yield $this->_mysqlSrv->go($id, "select count(*) as get_count from sk_divide_money where money_id=".$mid);
                        $remainSize = $skMoneyRes["result"][0]["divide_count"] - $remainSize["result"][0]["get_count"];
                        $remainMoney = $skMoneyRes["result"][0]["balance"];
                        $divideMoney = $this->getRandomMoney($remainSize, $remainMoney);

                        // 插入拆分红包数据
                        $insertRes = yield $this->_mysqlSrv->insert("sk_divide_money")->set("money_id", $mid)->set("money", $divideMoney)->set("user_id", $uid)->go($id);
                        if (empty($insertRes["result"]) || $insertRes["affected_rows"]<=0) {
                            throw new \Exception("拆分红包失败");
                        }

                        // 更新红包余额
                        $skMoneyUpdateSql = "update sk_money set balance=balance-".$divideMoney." ".($remainSize==1?", status=1":"")." where id=".$mid;
                        $updateRes = yield $this->_mysqlSrv->go($id, $skMoneyUpdateSql);
                        if (empty($updateRes["result"]) || $updateRes["affected_rows"]<=0) {
                            throw new \Exception("更新红包信息失败");
                        }

                        // 更新用户钱包
                        $updateRes = yield $this->_mysqlSrv->go($id, "update sk_user set wallet=wallet+".$divideMoney." where id=".$uid);
                        if (empty($updateRes["result"]) || $updateRes["affected_rows"]<=0) {
                            throw new \Exception("更新用户钱包失败");
                        }
                        yield $this->_mysqlSrv->goCommit($id);
                        getInstance()->log->error("uid:".$uid." success get ￥".$divideMoney);
                        $retVal["success"] = true;
                        $retVal["data"] = $divideMoney;
                    } catch (\Exception $e) {
                        $errorMsg = $e->getMessage();
                        yield $this->_mysqlSrv->goRollback($id);
                        getInstance()->log->error("uid:".$uid." fail to get money. ErrorMsg:".$errorMsg);
                        $retVal["success"] = false;
                        $retVal["err_msg"] = $errorMsg;
                    }
                    $count++;
                } else {
                    getInstance()->log->error("params error");
                    $retVal["success"] = false;
                    $retVal["err_msg"] = $errorMsg;
                }
                $this->_rabbitSrv->acknowledge($deliveryTag, 1); //手动发送ACK应答
                $this->_rabbitSrv->publish(json_encode($retVal, JSON_UNESCAPED_UNICODE), $replyTo, AMQP_NOPARAM, ["correlation_id"=>$corelationId]);
            }
        }
    }


    public function actionStart()
    {
        $consumePidFile = getInstance()->config['server']['pid_path'] . "consume-process.pid";
        if (file_exists($consumePidFile)) {
            echo "consumer had started\n";
        } else {
            file_put_contents($consumePidFile, "");
            $pidsData = [];
            for ($i=1; $i<=3; $i++) {
                $process = new \Swoole\Process(function (\Swoole\Process $childProcess) use ($i) {
                    //$params = ['batch/run'];
                    $params = ['batch/money'];
                    $i > 1 && array_push($params, $i);
                    $childProcess->exec('/home/worker/data/www/seconds-kill-system/console.php', $params); // exec
                });
                $consumePid = $process->start(); // 启动子进程
                $pidsData[$consumePid] = $i;
            }
            file_put_contents($consumePidFile, json_encode($pidsData), LOCK_EX);
        }
    }

    public function actionStop()
    {
        $consumePidFile = getInstance()->config['server']['pid_path'] . "consume-process.pid";
        if (!file_exists($consumePidFile)) {
            echo "consumer had stoped\n";
        } else {
            $consumePids = json_decode(file_get_contents($consumePidFile), true);
            foreach ($consumePids as $pid=>$queue) {
                \Swoole\Process::kill($pid);
            }
            unlink($consumePidFile);
        }
    }
}
