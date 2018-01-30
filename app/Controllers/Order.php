<?php
/**
* @file Order.php
* @brief 订单业务
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version gzhang
* @date 2018-01-10
 */

namespace App\Controllers;

use PG\MSF\Controllers\Controller;
use App\Tasks\AMQP as AMQPTask;
use App\Tasks\Order as OrderTask;

class Order extends Controller
{
    const SK_REQUEST_LIMIT_KEY = "sk:user:submit:limit";
    const SK_ORDER_NO_KEY = 'sk:order:order_no';

    private $_redisSrv;
    private $_mysqlSrv;

    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
        $this->_mysqlSrv = $this->getMysqlPool('master');
        $this->_redisSrv = $this->getRedisPool('p1');
    }

    /**
     * @brief actionSubmit 提交订单
     *
     * @return
     */
    public function actionSubmit()
    {
        $uid = $this->getContext()->getInput()->post("uid");
        $gid = $this->getContext()->getInput()->post("gid");
        //$userLimit = yield $this->_redisSrv->get(self::SK_REQUEST_LIMIT_KEY.":".$uid);
        //if (empty($userLimit)) {
        //    yield $this->_redisSrv->set(self::SK_REQUEST_LIMIT_KEY.":".$uid, 1, 1);
        $subRes = yield $this->_submit($gid, $uid);
        if ($subRes !== false) {
            $this->outputJson($subRes);
        } else {
            $this->outputJson("无法获取用户信息或者提交失败");
        }
        //} else {
        //    $this->outputJson("submit too fast");
        //}
    }

    /**
     * @brief _submit 提交订单
     *
     * @param $gid
     * @param $uid
     *
     * @return
     */
    public function _submit($gid, $uid)
    {
        $uid = yield $this->_redisSrv->get(User::LOG_SESSION_KEY.":".$uid);
        if ($uid) {
            // 开启一个事务，并返回事务ID
            //$id = yield $this->_mysqlSrv->goBegin();

            // 悲观锁
            //$goodsInfo = yield $this->_mysqlSrv->go($id, "select * from sk_goods where id = ".$gid." for update");
            //if ($goodsInfo["result"]) {
            //    $stock = $goodsInfo["result"][0]["stock"];
            //    if ($stock>0) {
            //        $orderNo = yield $this->_getOrderNo();
            //        $stock--;
            //        $orderRes = yield $this->_mysqlSrv->insert("sk_order")->set("order_no", $orderNo)->set("goods_id", $gid)->set("user_id", $uid)->go($id);
            //        $stockRes = yield $this->_mysqlSrv->update('sk_goods')->set('stock', $stock)->where('id', $gid)->go($id);
            //        if ($orderRes["result"] && $stockRes['result']) {
            //            yield $this->_mysqlSrv->goCommit($id);
            //            return $stock;
            //        }
            //    }
            //}

            // 乐观锁
            //$goodsInfo  = yield $this->_mysqlSrv->select("*")->from('sk_goods')->where("id", $gid)->go($id);
            //if ($goodsInfo["result"]) {
            //    $curStock = $goodsInfo["result"][0]["stock"];
            //    if ($curStock>0) {
            //        $orderNo = yield $this->_getOrderNo();
            //        $updateStock = $curStock-1;
            //        $orderRes = yield $this->_mysqlSrv->insert("sk_order")->set("order_no", $orderNo)->set("goods_id", $gid)->set("user_id", $uid)->go($id);
            //        $stockRes = yield $this->_mysqlSrv->update('sk_goods')->set('stock', $updateStock)->where('id', $gid)->andWhere("stock", $curStock)->go($id);
            //        if ($orderRes["result"] && $stockRes['result'] && $orderRes["affected_rows"]>0 && $stockRes["affected_rows"]>0) {
            //            yield $this->_mysqlSrv->goCommit($id);
            //            return $updateStock;
            //        }
            //    }
            //}
            //yield $this->_mysqlSrv->goRollback($id);

            // 消息队列
            $random = rand(1, 3);
            $routingKey = "q_linvo".($random==1?"":$random);
            //$routingKey = "q_linvo";
            $rabbit = $this->getObject(AMQPTask::class, ['rabbit', $routingKey]);
            return yield $rabbit->publish(json_encode(['uid' => $uid, 'gid'=>$gid]), $routingKey);
        }
        return false;
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

    /**
     * @brief _getOrderNo 生成订单号
     *
     * @return
     */
    public function _getOrderNo2()
    {
        $dateTime = date("YmdHis"); // 格式化当前时间戳
        $fileName = ROOT_PATH."/test.lock";
        if ($handle = fopen($fileName, "r+")) {
            $result = false;
            if (flock($handle, LOCK_EX)) {
                $reqNo = fread($handle, 5);
                $reqNo = intval($reqNo)+1;
                rewind($handle);
                fwrite($handle, $reqNo);
                flock($handle, LOCK_UN);    // 释放锁定
                $reqNo = 10000 + $reqNo; // 补齐订单号长度
                $result = $dateTime  . $reqNo; // 生成订单号
            }
            fclose($handle);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * @brief actionTest 测试订单号生成-Redis锁
     *
     * @return
     */
    public function actionTest()
    {
        $orderNo = yield $this->_getOrderNo();
        $addRes = yield $this->_redisSrv->sadd("sk:test:order_no", $orderNo);
        getInstance()->log->error(json_encode($addRes));
        $this->outputJson($orderNo);
    }


    /**
     * @brief actionTest2 测试订单号生成-文件锁
     *
     * @return
     */
    public function actionTest2()
    {
        $orderNo = $this->_getOrderNo2();
        if ($orderNo) {
            $addRes = yield $this->_redisSrv->sadd("sk:test:order_no2", $orderNo);
            getInstance()->log->error(json_encode($addRes));
            $this->outputJson($orderNo);
        } else {
            $this->outputJson($orderNo);
        }
    }
}
