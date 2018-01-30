<?php
/**
 * 抢红包
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Controllers;

use PG\MSF\Controllers\Controller;
use App\Tasks\AMQP as AMQPTask;
use App\Tasks\Money as MoneyTask;

class Money extends Controller
{
    const SK_MONEY_NO_KEY = 'sk:money:no';

    private $_redisSrv;
    private $_mysqlSrv;

    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
        $this->_mysqlSrv = $this->getMysqlPool('master');
        $this->_redisSrv = $this->getRedisPool('p1');
    }

    //public function actionGet()
    //{
    //    //$uid = $this->getContext()->getInput()->post("uid");
    //    $uid = mt_rand(1, 2000);
    //    $mid = $this->getContext()->getInput()->post("mid");
    //    $res = yield $this->getMoney($uid, $mid);
    //    $this->output($res);
    //}

    /**
     * @brief getMoney 获取拆分红包（单体模式，预先计算）
     *
     * @param $uid
     * @param $mid
     *
     * @return
     */
    //public function getMoney($uid, $mid)
    //{
    //    //$uid = yield $this->_redisSrv->get(User::LOG_SESSION_KEY.":".$uid);
    //    //if ($uid) {
    //    $errorMsg = "";
    //    $id = yield $this->_mysqlSrv->goBegin();
    //    $skMoneyRes = yield $this->_mysqlSrv->go($id, "select divide_count from sk_money where id = ".$mid." and status=0 for update");
    //    if ($skMoneyRes["result"]) {
    //        $isGetMoney = yield $this->_mysqlSrv->select('money_id')->from('sk_divide_money')->where('user_id', $uid)->andwhere("money_id", $mid)->go($id);
    //        if (empty($isGetMoney["result"])) {
    //            $moneyNoKey = self::SK_MONEY_NO_KEY.":".$mid;
    //            $moneyNo = yield $this->_redisSrv->incr($moneyNoKey); // 将redis值加1
    //            $divideCount = $skMoneyRes["result"][0]["divide_count"];
    //            $returnVal = "";
    //            if ($moneyNo <= $divideCount) {
    //                $updateRes = yield $this->_mysqlSrv->update("sk_divide_money")->set("user_id", $uid)->where("money_id", $mid)->andwhere("num", $moneyNo)->go($id);
    //                $selectRes = yield $this->_mysqlSrv->select("money")->from('sk_divide_money')->where("money_id", $mid)->andwhere("user_id", $uid)->andwhere("num", $moneyNo)->go($id);
    //                $divideMoney = $selectRes["result"][0]["money"];

    //                // 更新用户钱包
    //                $userInfo = yield $this->_mysqlSrv->select("wallet")->from("sk_user")->where("id", $uid)->go($id);
    //                $wallet = $userInfo["result"][0]["wallet"];
    //                yield $this->_mysqlSrv->update("sk_user")->set("wallet", round($wallet + $divideMoney, 2))->where("id", $uid)->go($id);
    //                $returnVal = $divideMoney;
    //            } else {
    //                yield $this->_redisSrv->del($moneyNoKey);
    //                yield $this->_mysqlSrv->update("sk_money")->set("status", 1)->where("id", $mid)->go($id);
    //                $returnVal = "红包抢完了";
    //            }
    //            yield $this->_mysqlSrv->goCommit($id);
    //            return $returnVal;
    //        } else {
    //            $errorMsg = "你已经抢过这个红包了";
    //        }
    //    } else {
    //        $errorMsg = "无效的红包";
    //    }
    //    yield $this->_mysqlSrv->goRollback($id);
    //    //} else {
    //    //    $errorMsg = "用户未登录";
    //    //}
    //    return $errorMsg;
    //}

    /**
     * @brief getMoney 获取拆分红包（单体模式，实时计算）
     *
     * @param $uid
     * @param $mid
     *
     * @return
     */
    public function getMoney($uid, $mid)
    {
        //$uid = yield $this->_redisSrv->get(User::LOG_SESSION_KEY.":".$uid);
        //if ($uid) {
        $errorMsg = "";
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
            return $divideMoney;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            yield $this->_mysqlSrv->goRollback($id);
        }
        //} else {
        //    $errorMsg = "用户未登录";
        //}
        return $errorMsg;
    }
    //public function actionSend()
    //{
    //    $total = $this->getContext()->getInput()->post("total");
    //    $divide = $this->getContext()->getInput()->post("divide");
    //    $total = round($total, 2);
    //    if ($total && $divide && intval($total)>0 && intval($divide)>0 && bccomp(round($total/$divide, 2), 0.01)==1) {
    //        $res = yield $this->_mysqlSrv->insert("sk_money")->set("money", $total)->set("balance", $total)->set("divide_count", $divide)->go();
    //        $divideRes = [];
    //        for ($i=0; $i<$divide; $i++) {
    //            if ($i==($divide-1)) {
    //                $divideMoney = $total;
    //            } else {
    //                $divideMoney = mt_rand(1, floor(($total*100/($divide-$i))*2)-1);
    //                $divideMoney = round($divideMoney/100, 2);
    //            }
    //            yield $this->_mysqlSrv->insert("sk_divide_money")->set("money_id", $res["insert_id"])->set("money", $divideMoney)->set("num", $i+1)->set("user_id", 0)->go();
    //            array_push($divideRes, $divideMoney);
    //            $total -= $divideMoney;
    //        }
    //        $this->outputJson("红包生成成功");
    //    } else {
    //        $this->outputJson("参数校验失败");
    //    }
    //}


    /**
     * @brief getRandomMoney 获取下一拆分红包的金额
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

    private $_response;

    private $_corr_id;

    /**
     * @brief actionGet 获取拆分红包
     *
     * @return
     */
    public function actionGet()
    {
        //$uid = $this->getContext()->getInput()->post("uid");
        $uid = mt_rand(1, 2000);
        $mid = $this->getContext()->getInput()->post("mid");
        $moneyTask = $this->getObject(MoneyTask::class);
        $res = yield $moneyTask->get($uid, $mid);
        $this->output($res);
    }

    /**
     * @brief actionSend 生成红包
     *
     * @return
     */
    public function actionSend()
    {
        $total = $this->getContext()->getInput()->post("total");
        $divide = $this->getContext()->getInput()->post("divide");
        $total = round($total, 2);
        if ($total && $divide && intval($total)>0 && intval($divide)>0 && bccomp(round($total/$divide, 2), 0.01)==1) {
            $res = yield $this->_mysqlSrv->insert("sk_money")->set("money", $total)->set("balance", $total)->set("divide_count", $divide)->go();
            if ($res["result"] && $res["affected_rows"]>0) {
                $this->outputJson("红包生成成功");
            } else {
                $this->outputJson("红包生成失败");
            }
        } else {
            $this->outputJson("参数校验失败");
        }
    }
}
