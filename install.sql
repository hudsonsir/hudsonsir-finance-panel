-- 系统配置表
DROP TABLE IF EXISTS `panel_config`;
CREATE TABLE `panel_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL COMMENT '配置键',
  `value` text DEFAULT NULL COMMENT '配置值',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化系统配置
INSERT INTO `panel_config` (`key`, `value`) VALUES
('admin_password', ''),
('admin_username', ''),
('captcha_id', 'ad35133e87f4581eb3d720a810c0fc31'),
('captcha_key', 'd442cdb6fbe12004ccb6bdec74830979'),
('email_auto_remind', '0'),
('email_remind_days', '3');

-- 通知配置表
DROP TABLE IF EXISTS `panel_notification`;
CREATE TABLE `panel_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_type` enum('email','sms','wechat','dingtalk','telegram') NOT NULL DEFAULT 'email' COMMENT '通道类型',
  `channel_name` varchar(255) NOT NULL COMMENT '通道名称',
  `enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用（1=启用，0=停用）',
  `config` text DEFAULT NULL COMMENT '通道配置（JSON格式）',
  `recipients` text DEFAULT NULL COMMENT '接收地址列表（JSON格式）',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `channel_type` (`channel_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `panel_notification` (`channel_type`, `channel_name`, `enabled`, `config`, `recipients`, `created_at`, `updated_at`) VALUES
('email', '邮件通知', 0, '{"smtp_host":"","smtp_port":"465","smtp_secure":"ssl","smtp_username":"","smtp_password":"","from_email":"","from_name":"财务工具系统","subject":"产品到期提醒","template":""}', '[]', NOW(), NOW());

-- 续费管理 - 产品管理表
DROP TABLE IF EXISTS `panel_product`;
CREATE TABLE `panel_product` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) NOT NULL COMMENT '产品名称',
  `product_category` enum('服务器','域名','其他') NOT NULL DEFAULT '其他' COMMENT '产品分类',
  `purchase_url` varchar(500) DEFAULT NULL COMMENT '官方购买地址',
  `purchase_account` varchar(255) DEFAULT NULL COMMENT '购买账户（邮箱或用户名）',
  `currency` enum('CNY','USD','EUR') NOT NULL DEFAULT 'CNY' COMMENT '默认计费币种',
  `duration` varchar(50) DEFAULT NULL COMMENT '周期（月付/季付/半年付/年付）',
  `price` decimal(10,2) DEFAULT '0.00' COMMENT '当前周期预算费用',
  `renew_method` varchar(255) DEFAULT NULL COMMENT '续费方式',
  `remark` text DEFAULT NULL COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（1=启用，0=停用）',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`product_id`),
  KEY `product_category` (`product_category`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 续费管理 - 续费记录表
DROP TABLE IF EXISTS `panel_renew_record`;
CREATE TABLE `panel_renew_record` (
  `renew_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL COMMENT '产品ID',
  `start_date` date NOT NULL COMMENT '本周期开始日期',
  `expire_date` date NOT NULL COMMENT '本周期结束日期',
  `duration` varchar(50) DEFAULT NULL COMMENT '周期（月付/季付/半年付/年付）',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '真实支付金额',
  `currency` enum('CNY','USD','EUR') NOT NULL DEFAULT 'CNY' COMMENT '支付币种',
  `pay_method` varchar(255) DEFAULT NULL COMMENT '支付方式',
  `invoice_url` varchar(500) DEFAULT NULL COMMENT '发票或账单链接',
  `note` text DEFAULT NULL COMMENT '管理备注',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`renew_id`),
  KEY `product_id` (`product_id`),
  KEY `expire_date` (`expire_date`),
  KEY `currency` (`currency`),
  CONSTRAINT `fk_renew_product` FOREIGN KEY (`product_id`) REFERENCES `panel_product` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 续费管理 - 汇率管理表
DROP TABLE IF EXISTS `panel_exchange_rate`;
CREATE TABLE `panel_exchange_rate` (
  `rate_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_source` enum('exchangerate-api') NOT NULL DEFAULT 'exchangerate-api' COMMENT '汇率来源',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'API密钥',
  `rate_cny` decimal(10,6) NOT NULL DEFAULT '7.200000' COMMENT '人民币基准汇率（相对于USD，1 USD = 7.2 CNY）',
  `rate_usd` decimal(10,6) NOT NULL DEFAULT '1.000000' COMMENT '美元基准汇率（默认货币，1 USD = 1.0 USD）',
  `rate_eur` decimal(10,6) NOT NULL DEFAULT '0.910000' COMMENT '欧元基准汇率（相对于USD，1 USD = 0.91 EUR）',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化汇率表
INSERT INTO `panel_exchange_rate` (`api_source`, `api_key`, `rate_cny`, `rate_usd`, `rate_eur`, `updated_at`) VALUES
('exchangerate-api', 'cae4f2372db0138adff664e4', 7.200000, 1.000000, 0.910000, NOW());

