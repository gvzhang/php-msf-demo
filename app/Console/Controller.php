<?php
/**
 * Console Controller基类
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace App\Console;

use PG\MSF\Controllers\Controller as BController;

/**
 * Class Controller
 * @package PG\MSF\Console
 */
class Controller extends BController
{
    /**
     * Controller constructor.
     *
     * @param string $controllerName 控制器名称
     * @param string $methodName 控制器方法名
     */
    public function __construct($controllerName, $methodName)
    {
        parent::__construct($controllerName, $methodName);
    }

    /**
     * 请求结束销毁
     */
    public function destroy()
    {
        $action = "";
        if ($this->getContext()) {
            $this->getContext()->getLog()->pushLog('params', $this->getContext()->getInput()->getAllPostGet());
            $this->getContext()->getLog()->pushLog('status', '200');
            $action = strtolower($this->getContext()->getControllerName()."/".$this->getContext()->getActionName());
        }
        parent::destroy();
        clearTimes();
        getInstance()->log->error("enter destroy ".$action);
        if ($action == "batch/actionmoney") {
            // 处理数达到一定数量重启进程解决内存泄露
            getInstance()->log->error("restart consume process");
            $consumePidFile = getInstance()->config['server']['pid_path'] . "consume-process.pid";
            $consumePids = json_decode(file_get_contents($consumePidFile), true);
            $pid = getmypid();
            if (isset($consumePids[$pid])) {
                $queue = $consumePids[$pid];
                unset($consumePids[$pid]);
                $process = new \Swoole\Process(function (\Swoole\Process $childProcess) use ($queue) {
                    //$params = ['batch/run'];
                    $params = ['batch/money'];
                    $queue > 1 && array_push($params, $queue);
                    $childProcess->exec('/home/worker/data/www/seconds-kill-system/console.php', $params); // exec
                });
                $newPid = $process->start(); // 启动子进程
                $consumePids[$newPid] = $queue;
                file_put_contents($consumePidFile, json_encode($consumePids), LOCK_EX);
            }
        }
        exit();
    }
}
