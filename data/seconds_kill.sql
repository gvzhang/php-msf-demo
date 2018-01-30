SET NAMES utf8;

-- Create database
DROP DATABASE IF EXISTS seconds_kill;
CREATE DATABASE seconds_kill DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

USE seconds_kill;

-- ----------------------------
-- Table structure for sk_user
-- ----------------------------
DROP TABLE IF EXISTS `sk_user`;
CREATE TABLE `sk_user` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
      `user_name` varchar(30) NOT NULL COMMENT '用户名',
      `password` char(10) NOT NULL COMMENT '密码',
      `wallet`  decimal(18, 2) unsigned NOT NULL DEFAULT 0 COMMENT '钱包',
      PRIMARY KEY (`id`),
      KEY `user_name_pwd_idx` (`user_name`,`password`)

) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='用户表';

-- ----------------------------
-- Table structure for sk_goods
-- ----------------------------
DROP TABLE IF EXISTS `sk_goods`;
CREATE TABLE `sk_goods` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '商品ID',
      `goods_name` varchar(255) NOT NULL COMMENT '商品名称',
      `stock` int(10) unsigned NOT NULL COMMENT '商品库存',
      PRIMARY KEY (`id`)

) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='商品表';

-- ----------------------------
-- Table structure for sk_order
-- ----------------------------
DROP TABLE IF EXISTS `sk_order`;
CREATE TABLE `sk_order` (
      `order_no` bigint(20) unsigned NOT NULL  COMMENT '订单号',
      `goods_id` int(10) unsigned NOT NULL COMMENT '商品ID',
      `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
      PRIMARY KEY (`order_no`),
      key `goods_id_idx` (`goods_id`),
      key `user_id_idx` (`user_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单表';

-- Mock user data
CREATE PROCEDURE mock_user_data()
BEGIN
DECLARE i INT;
SET i = 1;
WHILE i <= 2000 DO
    INSERT INTO `sk_user`(`user_name`, `password`) VALUES(CONCAT('user', i), '123456');
    SET i = i + 1;
END WHILE;
END;

CALL mock_user_data;

DROP PROCEDURE mock_user_data;

-- Mock goods data
INSERT INTO `sk_goods`(`goods_name`, `stock`) VALUES("iPhone8", 5000);


-- ----------------------------
-- Table structure for sk_money
-- ----------------------------
DROP TABLE IF EXISTS `sk_money`;
CREATE TABLE `sk_money` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT  COMMENT '红包ID',
      `money` decimal(18, 2) unsigned NOT NULL COMMENT '红包金额',
      `divide_count` int(10) unsigned NOT NULL COMMENT '拆分个数',
      `balance` decimal(18, 2) unsigned NOT NULL COMMENT '红包结余',
      `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '红包状态',
      PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1  DEFAULT CHARSET=utf8 COMMENT='红包表';

-- ----------------------------
-- Table structure for sk_divide_money
-- ----------------------------
DROP TABLE IF EXISTS `sk_divide_money`;
CREATE TABLE `sk_divide_money` (
      `money_id` int(10) unsigned NOT NULL COMMENT '红包ID',
      `money` decimal(18, 2) unsigned NOT NULL COMMENT '拆分红包金额',
      `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
      PRIMARY KEY (`money_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='红包拆分表';

-- ----------------------------
-- Table structure for sk_message
-- ----------------------------
DROP TABLE IF EXISTS `sk_message`;
CREATE TABLE `sk_message` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '消息ID',
      `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
      `content` text NOT NULL COMMENT '消息内容',
      PRIMARY KEY (`id`),
      KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='消息通知表';
