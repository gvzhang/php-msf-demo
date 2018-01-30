<?php
/**
 * @brief _getOrderNo 生成订单号
 *
 * @return
 */
function _getOrderNo()
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

$isSuccess = false;
$uid = "5a5ff298761e8";
$gid = 1;

$config['host']            = '192.168.10.10';
$config['port']            = 3306;
$config['user']            = 'root';
$config['password']        = '123456';
$config['charset']         = 'utf8';
$config['database']        = 'seconds_kill';

$logPath = "/home/vagrant/projects/seconds-kill-system/seconds-kill-system/www/test.log";

if (empty($config)) {
    file_put_contents($logPath, "数据库配置有误");
} else {
    $mysqli = null;
    $stmt = null;
    try {
        // db connection
        $mysqli = new \mysqli($config["host"], $config["user"], $config["password"], $config["database"]);
        if ($mysqli->connect_errno) {
            file_put_contents($logPath, "Connection Failed: [".$mysqli->connect_errno. "] : ".$mysqli->connect_error);
        } else {
            $error = "";
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
                            $orderNo = $this->_getOrderNo();
                            $updateStock = $curStock - 1;
                            $stmt = $mysqli->prepare("INSERT INTO sk_order (order_no, goods_id, user_id) VALUES (?, ?, ?)");
                            $stmt->bind_param("sis", $orderNo, $gid, $uid);
                            $exe_result = $stmt->execute();
                            if ($exe_result && $mysqli->affected_rows>0) {
                                //$curStock = 123;
                                //$stmt = $mysqli->prepare("UPDATE sk_goods SET stock = ? WHERE id = ? AND stock = ?");
                                //$stmt->bind_param("iii", $updateStock, $gid, $curStock);
                                $stmt = $mysqli->prepare("UPDATE sk_goods SET stock = ? WHERE id = ?");
                                $stmt->bind_param("ii", $updateStock, $gid);
                                $exe_result = $stmt->execute();
                                if ($exe_result && $mysqli->affected_rows>0) {
                                    $isSuccess = true;
                                    file_put_contents($logPath, "消费成功 OrderNo:".$orderNo);
                                }
                            }
                        } else {
                            $isSuccess = true;
                            file_put_contents($logPath, "消费成功 库存不足");
                        }
                    }
                }
            }
            if ($isSuccess) {
                $mysqli->commit();
            } else {
                $mysqli->rollback();
                $error = "mysqli_error:".$mysqli->error."  stmt_error:".($stmt?$stmt->error:"");
                file_put_contents($logPath, "事务提交失败  ".$error);
            }
        }
    } catch (Exception $e) {
        file_put_contents($logPath, $e->getMessage(). " <pre>".$e->getTraceAsString()."</pre>");
    }
    $stmt && $stmt->close();
    $mysqli && $mysqli->close();
}
if ($isSuccess) {
} else {
    file_put_contents($logPath, "order submit failed");
}
