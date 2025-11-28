<?php

namespace app\service;

use think\facade\Db;
use think\facade\Log;

class TelegramService
{
    /**
     * 发送消息到 Telegram
     * 
     * @param string $chatId Telegram Chat ID
     * @param string $message 消息内容
     * @param string $channelId 通道ID，默认为 'telegram-default'
     * @return bool
     */
    public static function send($chatId, $message, $channelId = 'telegram-default')
    {
        $channelConfig = self::getTelegramChannelConfig($channelId);
        if (!$channelConfig) {
            Log::error("Telegram通道 [{$channelId}] 配置不存在或未启用");
            return false;
        }

        $botToken = $channelConfig['bot_token'];
        if (empty($botToken)) {
            Log::error("Telegram Bot Token 未配置");
            return false;
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML', // 支持 HTML 格式
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("Telegram发送失败: {$error}");
            return false;
        }

        if ($httpCode !== 200) {
            Log::error("Telegram API返回错误: HTTP {$httpCode}, Response: {$response}");
            return false;
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            $errorMsg = $result['description'] ?? '未知错误';
            Log::error("Telegram发送失败: {$errorMsg}");
            return false;
        }

        return true;
    }

    /**
     * 发送到期提醒消息
     * 
     * @param string $chatId Telegram Chat ID
     * @param string $productName 产品名称
     * @param string $expireDate 到期日期
     * @param string $channelId 通道ID
     * @return bool
     */
    public static function sendExpireReminder($chatId, $productName, $expireDate, $channelId = 'telegram-default')
    {
        $channelConfig = self::getTelegramChannelConfig($channelId);
        if (!$channelConfig) {
            Log::error("Telegram通道 [{$channelId}] 配置不存在或未启用");
            return false;
        }

        $template = $channelConfig['template'] ?? '';
        if (empty($template)) {
            $template = self::getDefaultTemplate();
        }

        $message = str_replace('{{product}}', htmlspecialchars($productName), $template);
        $message = str_replace('{{expire_date}}', htmlspecialchars($expireDate), $message);

        return self::send($chatId, $message, $channelId);
    }

    /**
     * 获取默认消息模板
     * 
     * @return string
     */
    public static function getDefaultTemplate()
    {
        return <<<HTML
<b>产品到期提醒</b>

产品名称: {{product}}
到期日期: {{expire_date}}

请及时续费，避免服务中断。
HTML;
    }

    /**
     * 获取 Telegram 通道配置
     * 
     * @param string $channelId 通道ID，如果为 'telegram-default' 则查找第一个启用的通道
     * @return array|false
     */
    public static function getTelegramChannelConfig($channelId = 'telegram-default')
    {
        if ($channelId === 'telegram-default') {
            $channel = Db::name('notification')
                ->where('channel_type', 'telegram')
                ->where('enabled', 1)
                ->order('id', 'asc')
                ->find();
        } else {
            $channel = Db::name('notification')
                ->where('id', $channelId)
                ->where('channel_type', 'telegram')
                ->where('enabled', 1)
                ->find();
        }

        if ($channel) {
            $config = json_decode($channel['config'], true);
            return array_merge($config, [
                'recipients' => json_decode($channel['recipients'], true),
            ]);
        }

        return false;
    }

    /**
     * 测试 Bot Token 是否有效
     * 
     * @param string $botToken Bot Token
     * @return array ['success' => bool, 'message' => string, 'bot_info' => array|null]
     */
    public static function testBotToken($botToken)
    {
        if (empty($botToken)) {
            return ['success' => false, 'message' => 'Bot Token 不能为空', 'bot_info' => null];
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/getMe";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => "连接失败: {$error}", 'bot_info' => null];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => "API返回错误: HTTP {$httpCode}", 'bot_info' => null];
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            $errorMsg = $result['description'] ?? '未知错误';
            return ['success' => false, 'message' => "验证失败: {$errorMsg}", 'bot_info' => null];
        }

        return [
            'success' => true,
            'message' => 'Bot Token 验证成功',
            'bot_info' => $result['result'] ?? null
        ];
    }
}

