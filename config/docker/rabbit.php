<?php
/**
 * RabbitMQ 配置
 * @author lingyun <niulingyun@camera360.com>
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 * Date: 27/10/2017
 */

$config['amqp'] = [
    'rabbit' => [
        'host' => 'rabbitmq',
        'port' => '5672',
        'login' => 'guest',
        'password' => 'guest',
        ''
    ]
];

return $config;
