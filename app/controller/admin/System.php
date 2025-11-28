<?php

namespace app\controller\admin;


use app\BaseController;
use think\facade\Db;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Cache;

class System extends BaseController
{
    
    /**
     * 获取极验配置
     */
    public function geetest()
    {
        $captcha_id = config_get('captcha_id', '');
        $captcha_key = config_get('captcha_key', '');
        
        // 如果数据库中没有，返回默认值
        if (empty($captcha_id)) {
            $captcha_id = 'ad35133e87f4581eb3d720a810c0fc31';
        }
        if (empty($captcha_key)) {
            $captcha_key = 'd442cdb6fbe12004ccb6bdec74830979';
        }
        
        return msg('ok', 'success', [
            'captcha_id' => $captcha_id,
            'captcha_key' => $captcha_key
        ]);
    }
    
    /**
     * 设置极验配置
     */
    public function setGeetest()
    {
        $captcha_id = Request::param('captcha_id', '', 'trim');
        $captcha_key = Request::param('captcha_key', '', 'trim');
        
        if (empty($captcha_id) || empty($captcha_key)) {
            return msg('error', '极验ID和KEY不能为空');
        }
        
        // 验证格式（极验ID和KEY通常是32位十六进制字符串）
        if (!preg_match('/^[a-fA-F0-9]{32}$/', $captcha_id)) {
            return msg('error', '极验ID格式不正确');
        }
        
        if (!preg_match('/^[a-fA-F0-9]{32}$/', $captcha_key)) {
            return msg('error', '极验KEY格式不正确');
        }
        
        // 限制长度
        if (strlen($captcha_id) > 100 || strlen($captcha_key) > 100) {
            return msg('error', '极验ID或KEY过长');
        }
        
        config_set('captcha_id', $captcha_id);
        config_set('captcha_key', $captcha_key);
        cache('configs', NULL);
        
        return msg('ok', '保存成功', [
            'captcha_id' => $captcha_id,
            'captcha_key' => $captcha_key
        ]);
    }

    /**
     * 获取配置（仅支持 admin_username）
     */
    public function get()
    {
        $key = Request::param('key', '', 'trim');
        
        // 只允许获取 admin_username 配置
        if ($key !== 'admin_username') {
            return msg('error', '不允许获取该配置项');
        }
        
        $value = config_get($key, '');
        return msg('ok', 'success', $value);
    }

    /**
     * 设置配置（已移除模板设置功能）
     */
    public function set()
    {
        return msg('error', '此功能已移除');
    }

    public function setpwd()
    {
        $params = Request::param();
        if(isset($params['username']))$params['username']=trim($params['username']);
        if(isset($params['oldpwd']))$params['oldpwd']=trim($params['oldpwd']);
        if(isset($params['newpwd']))$params['newpwd']=trim($params['newpwd']);
        if(isset($params['newpwd2']))$params['newpwd2']=trim($params['newpwd2']);

        $validate = Validate::rule([
            'username|用户名' => 'require|chsAlphaNum',
        ]);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        config_set('admin_username', $params['username']);

        if(!empty($params['oldpwd']) && !empty($params['newpwd']) && !empty($params['newpwd2'])){
            $oldpwd = config_get('admin_password');
            if($oldpwd && !password_verify($params['oldpwd'], $oldpwd)){
                return msg('error', '旧密码不正确');
            }
            if($params['newpwd'] != $params['newpwd2']){
                return msg('error', '两次新密码输入不一致');
            }
            config_set('admin_password', password_hash($params['newpwd'], PASSWORD_DEFAULT));
        }
        cache('configs', NULL);
        cookie('admin_token', null);
        return msg();
    }

    /**
     * 清理缓存
     */
    public function clear()
    {
        Cache::clear();
        reset_opcache();
        return msg('ok', '缓存清理成功');
    }

    /**
     * 获取邮件配置（从新表读取）
     */
    public function getEmailConfig()
    {
        // 从新表读取邮件通道配置
        $emailChannel = Db::name('notification')
            ->where('channel_type', 'email')
            ->order('id', 'asc')
            ->find();
        
        // 如果新表没有数据，尝试从旧配置迁移
        if (!$emailChannel) {
            $emailChannel = $this->migrateEmailConfigFromOld();
        }
        
        if (!$emailChannel) {
            // 如果还是没有，返回默认配置
            $defaultTemplate = \app\service\EmailService::getDefaultTemplate();
            return msg('ok', 'success', [
                'email_smtp_host' => '',
                'email_smtp_port' => '465',
                'email_smtp_secure' => 'ssl',
                'email_smtp_username' => '',
                'email_smtp_password' => '',
                'email_from_email' => '',
                'email_from_name' => '财务工具系统',
                'email_subject' => '产品到期提醒',
                'email_template' => $defaultTemplate,
                'email_notify_emails' => '',
                'email_enabled' => '0', // 通道默认关闭
                'email_auto_remind' => config_get('email_auto_remind', '0'), // 全局提醒设置
                'email_remind_days' => config_get('email_remind_days', '3'),
            ]);
        }
        
        $config = json_decode($emailChannel['config'], true) ?: [];
        $recipients = json_decode($emailChannel['recipients'], true) ?: [];
        
        $template = $config['template'] ?? '';
        if (empty($template)) {
            $template = \app\service\EmailService::getDefaultTemplate();
        }
        
        return msg('ok', 'success', [
            'email_smtp_host' => $config['smtp_host'] ?? '',
            'email_smtp_port' => $config['smtp_port'] ?? '465',
            'email_smtp_secure' => $config['smtp_secure'] ?? 'ssl',
            'email_smtp_username' => $config['smtp_username'] ?? '',
            'email_smtp_password' => $config['smtp_password'] ?? '',
            'email_from_email' => $config['from_email'] ?? '',
            'email_from_name' => $config['from_name'] ?? '财务工具系统',
            'email_subject' => $config['subject'] ?? '产品到期提醒',
            'email_template' => $template,
            'email_notify_emails' => implode(',', $recipients),
            'email_enabled' => $emailChannel['enabled'] ? '1' : '0', // 通道的启用状态
            'email_auto_remind' => config_get('email_auto_remind', '0'), // 全局提醒设置（从config表读取）
            'email_remind_days' => config_get('email_remind_days', '3'),
        ]);
    }
    
    /**
     * 从旧配置迁移到新表
     */
    private function migrateEmailConfigFromOld()
    {
        // 检查旧配置是否存在
        $oldConfig = [
            'smtp_host' => config_get('email_smtp_host', ''),
            'smtp_port' => config_get('email_smtp_port', '465'),
            'smtp_secure' => config_get('email_smtp_secure', 'ssl'),
            'smtp_username' => config_get('email_smtp_username', ''),
            'smtp_password' => config_get('email_smtp_password', ''),
            'from_email' => config_get('email_from_email', ''),
            'from_name' => config_get('email_from_name', '财务工具系统'),
            'subject' => config_get('email_subject', '产品到期提醒'),
            'template' => config_get('email_template', ''),
        ];
        
        $oldRecipients = config_get('email_notify_emails', '');
        $recipients = !empty($oldRecipients) ? array_map('trim', explode(',', $oldRecipients)) : [];
        $recipients = array_filter($recipients, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        // 如果模板为空，使用默认模板
        if (empty($oldConfig['template'])) {
            $oldConfig['template'] = \app\service\EmailService::getDefaultTemplate();
        }
        
        // 创建新记录
        $data = [
            'channel_type' => 'email',
            'channel_name' => '邮件通知',
            'enabled' => config_get('email_auto_remind', '0') === '1' ? 1 : 0,
            'config' => json_encode($oldConfig, JSON_UNESCAPED_UNICODE),
            'recipients' => json_encode($recipients, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        $id = Db::name('notification')->insertGetId($data);
        return Db::name('notification')->find($id);
    }

    /**
     * 设置邮件配置（保存到新表）
     */
    public function setEmailConfig()
    {
        $params = Request::param();
        
        // 验证提醒天数（始终验证）
        $remindDays = intval($params['email_remind_days'] ?? 3);
        if ($remindDays < 1 || $remindDays > 30) {
            return msg('error', '提醒天数范围不正确（1-30天）');
        }
        
        $emailEnabled = isset($params['email_enabled']) ? intval($params['email_enabled']) : null;
        $hasSmtpConfig = !empty($params['email_smtp_host']) || 
                         !empty($params['email_smtp_port']) || 
                         !empty($params['email_smtp_username']) || 
                         !empty($params['email_smtp_password']);
        
        // 如果邮箱通道被启用，或者提供了SMTP配置，则需要验证SMTP配置
        $needSmtpConfig = ($emailEnabled === 1) || ($hasSmtpConfig);
        
        if ($needSmtpConfig) {
            // 验证必填项
            if (empty($params['email_smtp_host'])) {
                return msg('error', 'SMTP服务器地址不能为空');
            }
            if (empty($params['email_smtp_port'])) {
                return msg('error', 'SMTP端口不能为空');
            }
            if (empty($params['email_smtp_username'])) {
                return msg('error', 'SMTP用户名不能为空');
            }
            if (empty($params['email_smtp_password'])) {
                return msg('error', 'SMTP密码不能为空');
            }
            
            // 验证端口范围
            $port = intval($params['email_smtp_port']);
            if ($port < 1 || $port > 65535) {
                return msg('error', 'SMTP端口范围不正确（1-65535）');
            }

            // 验证安全类型
            if (!in_array($params['email_smtp_secure'] ?? 'ssl', ['ssl', 'tls'])) {
                return msg('error', 'SMTP安全类型只能是ssl或tls');
            }
        }

        // 验证通知邮箱（仅在配置SMTP时验证）
        $notifyEmails = trim($params['email_notify_emails'] ?? '');
        $emailList = [];
        if ($needSmtpConfig && !empty($notifyEmails)) {
            $emailList = array_map('trim', explode(',', $notifyEmails));
            foreach ($emailList as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return msg('error', '通知邮箱格式不正确: ' . $email);
                }
            }
        }
        $emailList = array_filter($emailList);

        // 查找或创建邮件通道
        $emailChannel = Db::name('notification')
            ->where('channel_type', 'email')
            ->order('id', 'asc')
            ->find();
        
        // 如果需要配置SMTP，则保存/更新SMTP配置
        if ($needSmtpConfig) {
            // 构建配置JSON
            $port = intval($params['email_smtp_port']);
            $config = [
                'smtp_host' => trim($params['email_smtp_host']),
                'smtp_port' => (string)$port,
                'smtp_secure' => trim($params['email_smtp_secure'] ?? 'ssl'),
                'smtp_username' => trim($params['email_smtp_username']),
                'smtp_password' => trim($params['email_smtp_password']),
                'from_email' => trim($params['email_from_email'] ?? ''),
                'from_name' => trim($params['email_from_name'] ?? '财务工具系统'),
                'subject' => trim($params['email_subject'] ?? '产品到期提醒'),
                'template' => trim($params['email_template'] ?? ''),
            ];
            
            // 如果模板为空，使用默认模板
            if (empty($config['template'])) {
                $config['template'] = \app\service\EmailService::getDefaultTemplate();
            }
            
            // 通道的启用状态：如果明确传递了email_enabled，使用传递的值；否则保持原状态
            $channelEnabled = 0;
            if ($emailEnabled !== null) {
                $channelEnabled = $emailEnabled ? 1 : 0;
            } elseif ($emailChannel) {
                // 如果没有传递 email_enabled，保持原有状态
                $channelEnabled = $emailChannel['enabled'];
            }
            
            $data = [
                'channel_type' => 'email',
                'channel_name' => '邮件通知',
                'enabled' => $channelEnabled, // 使用通道的启用状态，而不是全局提醒设置
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'recipients' => json_encode($emailList, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($emailChannel) {
                // 更新现有记录
                Db::name('notification')
                    ->where('id', $emailChannel['id'])
                    ->update($data);
            } else {
                // 创建新记录
                $data['created_at'] = date('Y-m-d H:i:s');
                Db::name('notification')->insert($data);
            }
        } elseif ($emailEnabled === 0 && $emailChannel) {
            // 如果明确禁用了邮箱通道且通道存在，只更新启用状态
            Db::name('notification')
                ->where('id', $emailChannel['id'])
                ->update([
                    'enabled' => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
        
        // 无论是否配置SMTP，都更新全局提醒设置（保留在config表中）
        $autoRemind = isset($params['email_auto_remind']) ? (intval($params['email_auto_remind']) ? '1' : '0') : '0';
        config_set('email_auto_remind', $autoRemind);
        config_set('email_remind_days', $remindDays);
        cache('configs', NULL);
        
        return msg('ok', '保存成功');
    }

    /**
     * 测试邮件发送
     */
    public function testEmail()
    {
        $testEmail = Request::param('test_email', '', 'trim');
        
        if (empty($testEmail)) {
            return msg('error', '测试邮箱地址不能为空');
        }
        
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return msg('error', '邮箱地址格式不正确');
        }

        try {
            $result = \app\service\EmailService::sendExpireReminder(
                $testEmail,
                '测试产品',
                date('Y-m-d', strtotime('+3 days'))
            );
            
            if ($result) {
                return msg('ok', '测试邮件发送成功，请检查收件箱');
            } else {
                return msg('error', '测试邮件发送失败，请检查配置和日志');
            }
        } catch (\Exception $e) {
            return msg('error', '发送失败：' . $e->getMessage());
        }
    }

    /**
     * 手动触发发送到期提醒邮件
     */
    public function sendExpireReminders()
    {
        // 从新表获取邮件通道配置
        $emailChannel = Db::name('notification')
            ->where('channel_type', 'email')
            ->where('enabled', 1)
            ->order('id', 'asc')
            ->find();
        
        $recipients = [];
        if ($emailChannel) {
            $recipients = json_decode($emailChannel['recipients'], true) ?: [];
        }

        // 获取提醒天数（从config表）
        $remindDays = intval(config_get('email_remind_days', 3));
        if ($remindDays < 1) {
            return msg('error', '提醒天数配置不正确');
        }

        // 计算到期日期范围
        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$remindDays} days"));

        // 查询即将到期的续费记录
        $expiringRecords = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.product_id, r.expire_date, p.product_name')
            ->where('r.expire_date', '>=', $today)
            ->where('r.expire_date', '<=', $targetDate)
            ->where('p.status', 1)
            ->order('r.expire_date', 'asc')
            ->select();

        if (empty($expiringRecords)) {
            return msg('ok', '没有即将到期的产品', ['sent' => 0, 'failed' => 0]);
        }

        // 按产品分组，获取每个产品最新的到期时间
        $products = [];
        foreach ($expiringRecords as $record) {
            $productId = $record['product_id'];
            if (!isset($products[$productId])) {
                $products[$productId] = $record;
            } else {
                if (strtotime($record['expire_date']) > strtotime($products[$productId]['expire_date'])) {
                    $products[$productId] = $record;
                }
            }
        }

        // 使用从新表获取的接收地址列表
        $emailList = array_filter($recipients, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        // 获取所有启用的 Telegram 通知通道
        $telegramChannels = Db::name('notification')
            ->where('channel_type', 'telegram')
            ->where('enabled', 1)
            ->select();

        // 检查是否有有效的通知通道
        $hasValidTelegram = false;
        foreach ($telegramChannels as $telegramChannel) {
            $telegramRecipients = json_decode($telegramChannel['recipients'], true) ?: [];
            $telegramChatIds = array_filter($telegramRecipients, function($id) {
                return !empty($id) && preg_match('/^-?\d+$/', $id);
            });
            if (!empty($telegramChatIds)) {
                $hasValidTelegram = true;
                break;
            }
        }

        if (empty($emailList) && !$hasValidTelegram) {
            return msg('error', '没有配置有效的通知地址（邮箱或Telegram Chat ID）');
        }

        $successCount = 0;
        $failCount = 0;
        $details = [];

        // 为每个即将到期的产品发送通知
        foreach ($products as $product) {
            // 发送邮件通知
            foreach ($emailList as $email) {
                $result = \app\service\EmailService::sendExpireReminder(
                    $email,
                    $product['product_name'],
                    $product['expire_date']
                );

                if ($result) {
                    $details[] = [
                        'product' => $product['product_name'],
                        'expire_date' => $product['expire_date'],
                        'recipient' => $email,
                        'channel' => 'email',
                        'status' => 'success',
                        'message' => '发送成功'
                    ];
                    $successCount++;
                } else {
                    $details[] = [
                        'product' => $product['product_name'],
                        'expire_date' => $product['expire_date'],
                        'recipient' => $email,
                        'channel' => 'email',
                        'status' => 'failed',
                        'message' => '发送失败'
                    ];
                    $failCount++;
                }
            }

            // 发送 Telegram 通知
            foreach ($telegramChannels as $telegramChannel) {
                $config = json_decode($telegramChannel['config'], true);
                $botToken = $config['bot_token'] ?? '';
                if (empty($botToken)) {
                    continue;
                }

                $recipients = json_decode($telegramChannel['recipients'], true);
                foreach ($recipients as $chatId) {
                    if (empty($chatId) || !preg_match('/^-?\d+$/', $chatId)) {
                        continue;
                    }

                    $result = \app\service\TelegramService::sendExpireReminder(
                        $chatId,
                        $product['product_name'],
                        $product['expire_date'],
                        $telegramChannel['id']
                    );
                    if ($result) {
                        $details[] = [
                            'product' => $product['product_name'],
                            'expire_date' => $product['expire_date'],
                            'recipient' => $chatId,
                            'channel' => 'telegram',
                            'status' => 'success',
                            'message' => '发送成功'
                        ];
                        $successCount++;
                    } else {
                        $details[] = [
                            'product' => $product['product_name'],
                            'expire_date' => $product['expire_date'],
                            'recipient' => $chatId,
                            'channel' => 'telegram',
                            'status' => 'failed',
                            'message' => '发送失败'
                        ];
                        $failCount++;
                    }
                }
            }
        }

        return msg('ok', "发送完成: 成功 {$successCount} 条，失败 {$failCount} 条", [
            'sent' => $successCount,
            'failed' => $failCount,
            'details' => $details
        ]);
    }

    /**
     * 获取 Telegram 配置
     */
    public function getTelegramConfig()
    {
        $telegramChannel = Db::name('notification')
            ->where('channel_type', 'telegram')
            ->order('id', 'asc')
            ->find();

        if ($telegramChannel) {
            $config = json_decode($telegramChannel['config'], true) ?: [];
            $recipients = json_decode($telegramChannel['recipients'], true) ?: [];
            
            $template = $config['template'] ?? '';
            if (empty($template)) {
                $template = \app\service\TelegramService::getDefaultTemplate();
            }

            return msg('ok', 'success', [
                'telegram_bot_token' => $config['bot_token'] ?? '',
                'telegram_template' => $template,
                'telegram_chat_ids' => implode(',', $recipients),
                'telegram_enabled' => $telegramChannel['enabled'] ? '1' : '0',
            ]);
        }

        // 返回默认配置
        return msg('ok', 'success', [
            'telegram_bot_token' => '',
            'telegram_template' => \app\service\TelegramService::getDefaultTemplate(),
            'telegram_chat_ids' => '',
            'telegram_enabled' => '0',
        ]);
    }

    /**
     * 设置 Telegram 配置
     */
    public function setTelegramConfig()
    {
        $params = Request::param();

        // 验证必填项
        if (empty($params['telegram_bot_token'])) {
            return msg('error', 'Bot Token 不能为空');
        }

        // 验证 Chat IDs
        $chatIds = [];
        $chatIdsStr = trim($params['telegram_chat_ids'] ?? '');
        if (!empty($chatIdsStr)) {
            $chatIds = array_map('trim', explode(',', $chatIdsStr));
            $chatIds = array_filter($chatIds, function($id) {
                return !empty($id) && preg_match('/^-?\d+$/', $id);
            });
        }

        // 构建配置JSON
        $config = [
            'bot_token' => trim($params['telegram_bot_token']),
            'template' => trim($params['telegram_template'] ?? ''),
        ];

        // 如果模板为空，使用默认模板
        if (empty($config['template'])) {
            $config['template'] = \app\service\TelegramService::getDefaultTemplate();
        }

        // 查找或创建 Telegram 通道
        $telegramChannel = Db::name('notification')
            ->where('channel_type', 'telegram')
            ->order('id', 'asc')
            ->find();

        $data = [
            'channel_type' => 'telegram',
            'channel_name' => 'Telegram Bot',
            'enabled' => isset($params['telegram_enabled']) ? (intval($params['telegram_enabled']) ? 1 : 0) : 0,
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'recipients' => json_encode($chatIds, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($telegramChannel) {
            // 更新现有记录
            Db::name('notification')
                ->where('id', $telegramChannel['id'])
                ->update($data);
        } else {
            // 创建新记录
            $data['created_at'] = date('Y-m-d H:i:s');
            Db::name('notification')->insert($data);
        }

        cache('configs', NULL);
        return msg('ok', '保存成功');
    }

    /**
     * 测试 Telegram Bot Token
     */
    public function testTelegram()
    {
        $botToken = Request::param('bot_token', '', 'trim');

        if (empty($botToken)) {
            return msg('error', 'Bot Token 不能为空');
        }

        try {
            $result = \app\service\TelegramService::testBotToken($botToken);
            
            if ($result['success']) {
                $botInfo = $result['bot_info'];
                $botName = $botInfo['first_name'] ?? '未知';
                $botUsername = isset($botInfo['username']) ? '@' . $botInfo['username'] : '';
                return msg('ok', "Bot Token 验证成功\nBot名称: {$botName} {$botUsername}", $botInfo);
            } else {
                return msg('error', $result['message']);
            }
        } catch (\Exception $e) {
            return msg('error', '验证失败：' . $e->getMessage());
        }
    }

    /**
     * 测试发送 Telegram 消息
     */
    public function testTelegramMessage()
    {
        $chatId = Request::param('chat_id', '', 'trim');
        $channelId = Request::param('channel_id', 'telegram-default', 'trim');

        if (empty($chatId)) {
            return msg('error', 'Chat ID 不能为空');
        }

        if (!preg_match('/^-?\d+$/', $chatId)) {
            return msg('error', 'Chat ID 格式不正确');
        }

        try {
            $result = \app\service\TelegramService::sendExpireReminder(
                $chatId,
                '测试产品',
                date('Y-m-d', strtotime('+3 days')),
                $channelId
            );

            if ($result) {
                return msg('ok', '测试消息发送成功，请检查 Telegram');
            } else {
                return msg('error', '测试消息发送失败，请检查配置和日志');
            }
        } catch (\Exception $e) {
            return msg('error', '发送失败：' . $e->getMessage());
        }
    }

    /**
     * 获取运行目录信息
     */
    public function getRuntimeInfo()
    {
        $rootPath = app()->getRootPath();
        // 移除末尾的斜杠
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        
        return msg('ok', 'success', [
            'root_path' => $rootPath,
            'command' => 'php think email:send-expire-reminder'
        ]);
    }
}
