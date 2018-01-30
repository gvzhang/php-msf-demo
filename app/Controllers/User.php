<?php
/**
* @file User.php
* @brief 用户业务
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version gzhang
* @date 2018-01-10
 */

namespace App\Controllers;

use PG\MSF\Controllers\Controller;

class User extends Controller
{
    /**
     * @brief 登录SESSION KEY
     */
    const LOG_SESSION_KEY="sk:login:user";

    /**
     * @brief actionLogin 用户登录
     *
     * @return
     */
    public function actionLogin()
    {
        $userName = $this->getContext()->getInput()->post("user_name");
        $password = $this->getContext()->getInput()->post("password");
        if ($userName && $password) {
            $res = yield $this->getMysqlPool('master')->select("id")->from("sk_user")->where("user_name", $userName)
                ->andWhere("password", $password)->go();
            $result = $res["result"];
            if ($result) {
                $uid = uniqid();
                yield $this->getRedisPool('p1')->set(self::LOG_SESSION_KEY.":".$uid, $result[0]["id"]);
                $this->outputJson($uid);
            } else {
                $this->outputJson(false);
            }
        } else {
            $this->outputJson(false);
        }
    }

    /**
     * @brief actionInfo 获取用户信息
     *
     * @return
     */
    public function actionInfo()
    {
        $uid = $this->getContext()->getInput()->get("uid");
        $val = yield $this->getRedisPool('p1')->get(self::LOG_SESSION_KEY.":".$uid);
        if ($val) {
            $this->outputJson($val);
        } else {
            $this->outputJson(false);
        }
    }
}
