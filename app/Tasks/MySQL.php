<?php
/**
 * Demo Task
 *
 * 注意理论上本文件代码应该在Tasker进程中执行
 */

namespace App\Tasks;

use \PG\MSF\Tasks\Task;

/**
 * Class Demo
 * @package App\Tasks
 */
class MySQL extends Task
{
    /**
     * 连接池执行同步查询
     *
     * @return array
     */
    public function InitDB()
    {
        $config = getInstance()->config['mysql']['master']??[];
        $path = ROOT_PATH."/data/seconds_kill.sql";
        if (empty($config)) {
            getInstance()->log->error("数据库配置有误");
            return false;
        }
        if (file_exists($path) == false) {
            getInstance()->log->error("数据库初始文件不存在");
            return false;
        }
        try {
            // db connection
            $mysqli = new \mysqli($config["host"], $config["user"], $config["password"], $config["database"]);
            if ($mysqli->connect_errno) {
                throw new Exception("Connection Failed: [".$mysqli->connect_errno. "] : ".$mysqli->connect_error);
            }

            // read file.
            // This file has multiple sql statements.
            $file_sql = file_get_contents($path);

            if ($file_sql == "null" || empty($file_sql) || strlen($file_sql) <= 0) {
                throw new Exception("File is empty. I wont run it..");
            }

            //run the sql file contents through the mysqli's multi_query function.
            // here is where it gets complicated...
            // if the first query has errors, here is where you get it.
            $sqlFileResult = $mysqli->multi_query($file_sql);
            // this returns false only if there are errros on first sql statement, it doesn't care about the rest of the sql statements.

            $sqlCount = 1;
            if ($sqlFileResult == false) {
                throw new Exception("File: '".$fullpath."' , Query#[".$sqlCount."], [".$mysqli->errno."]: '".$mysqli->error."' }");
            }

            // so handle the errors on the subsequent statements like this.
            // while I have more results. This will start from the second sql statement. The first statement errors are thrown above on the $mysqli->multi_query("SQL"); line
            while ($mysqli->more_results()) {
                $sqlCount++;
                // load the next result set into mysqli's active buffer. if this fails the $mysqli->error, $mysqli->errno will have appropriate error info.
                if ($mysqli->next_result() == false) {
                    throw new Exception("File: '".$fullpath."' , Query#[".$sqlCount."], Error No: [".$mysqli->errno."]: '".$mysqli->error."' }");
                }
            }
            return true;
        } catch (Exception $e) {
            getInstance()->log->error($e->getMessage(). " <pre>".$e->getTraceAsString()."</pre>");
        }
        return false;
    }
}
