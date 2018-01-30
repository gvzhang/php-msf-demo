<?php
/**
* @file Goods.php
* @brief 商品业务
* @author zhangguangjian <johnzhangbkb@gmail.com>
* @version gzhang
* @date 2018-01-10
 */

namespace App\Controllers;

use PG\MSF\Controllers\Controller;

class Goods extends Controller
{

    /**
     * @brief actionDetail 获取商品详情
     *
     * @return
     */
    public function actionDetail()
    {
        $gid = $this->getContext()->getInput()->get("id");
        if ($gid) {
            $goodsInfo  = yield $this->getMysqlPool('master')->select("*")->from('sk_goods')->where("id", $gid)->go();
            $this->outputJson($goodsInfo["result"][0]);
        } else {
            $this->outputJson("gid can't be null");
        }
    }
}
