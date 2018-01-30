<?php
/**
 * Docker环境
 */
$config['mysql']['master']['host']            = '192.168.10.10';
$config['mysql']['master']['port']            = 3306;
$config['mysql']['master']['user']            = 'root';
$config['mysql']['master']['password']        = '123456';
$config['mysql']['master']['charset']         = 'utf8';
$config['mysql']['master']['database']        = 'seconds_kill';

$config['mysql']['slave1']['host']           = '192.168.10.10';
$config['mysql']['slave1']['port']           = 3306;
$config['mysql']['slave1']['user']           = 'root';
$config['mysql']['slave1']['password']       = '123456';
$config['mysql']['slave1']['charset']        = 'utf8';
$config['mysql']['slave1']['database']       = 'seconds_kill';

$config['mysql']['slave2']['host']           = '192.168.10.10';
$config['mysql']['slave2']['port']           = 3306;
$config['mysql']['slave2']['user']           = 'root';
$config['mysql']['slave2']['password']       = '123456';
$config['mysql']['slave2']['charset']        = 'utf8';
$config['mysql']['slave2']['database']       = 'seconds_kill';

$config['mysql_proxy']['master_slave'] = [
    'pools' => [
        'master' => 'master',
        'slaves' => ['slave1', 'slave2'],
    ],
    'mode' => \PG\MSF\Macro::MASTER_SLAVE,
];

return $config;
