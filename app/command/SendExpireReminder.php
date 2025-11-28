<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\service\EmailService;
use app\service\TelegramService;

class SendExpireReminder extends Command
{
    protected function configure()
    {
        $this->setName('email:send-expire-reminder')
            ->setDescription('发送产品到期提醒邮件');
    }

    protected function execute(Input $input, Output $output)
    {
        $emailChannel = Db::name('notification')
            ->where('channel_type', 'email')
            ->where('enabled', 1)
            ->order('id', 'asc')
            ->find();

        $emailList = [];
        if ($emailChannel) {
            $recipients = json_decode($emailChannel['recipients'], true) ?: [];
            $emailList = array_filter($recipients, function($email) {
                return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            });
        } else {
            $autoRemind = config_get('email_auto_remind', '0');
            if ($autoRemind === '1') {
                $notifyEmails = config_get('email_notify_emails', '');
                if (!empty($notifyEmails)) {
                    $emailList = array_map('trim', explode(',', $notifyEmails));
                    $emailList = array_filter($emailList, function($email) {
                        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                    });
                }
            }
        }

        $remindDays = intval(config_get('email_remind_days', 3));
        if ($remindDays < 1) {
            $output->writeln('提醒天数配置不正确，跳过发送');
            return;
        }

        $today = date('Y-m-d');
        $targetDate = date('Y-m-d', strtotime("+{$remindDays} days"));

        $expiringRecords = Db::name('renew_record')
            ->alias('r')
            ->join('product p', 'r.product_id = p.product_id', 'LEFT')
            ->field('r.product_id, r.expire_date, p.product_name')
            ->where('r.expire_date', '>=', $today)
            ->where('r.expire_date', '<=', $targetDate)
            ->where('p.status', 1) // 只处理启用的产品
            ->order('r.expire_date', 'asc')
            ->select();

        if (empty($expiringRecords)) {
            $output->writeln('没有即将到期的产品');
            return;
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

        $telegramChannels = Db::name('notification')
            ->where('channel_type', 'telegram')
            ->where('enabled', 1)
            ->select();
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
            $output->writeln('没有配置有效的通知地址（邮箱或Telegram Chat ID），跳过发送');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($products as $product) {
            foreach ($emailList as $email) {
                $result = EmailService::sendExpireReminder(
                    $email,
                    $product['product_name'],
                    $product['expire_date']
                );

                if ($result) {
                    $output->writeln("成功发送提醒邮件: {$product['product_name']} -> {$email}");
                    $successCount++;
                } else {
                    $output->writeln("发送失败: {$product['product_name']} -> {$email}");
                    $failCount++;
                }
            }
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

                    $result = TelegramService::sendExpireReminder(
                        $chatId,
                        $product['product_name'],
                        $product['expire_date'],
                        $telegramChannel['id']
                    );

                    if ($result) {
                        $output->writeln("成功发送Telegram提醒: {$product['product_name']} -> {$chatId}");
                        $successCount++;
                    } else {
                        $output->writeln("发送失败: {$product['product_name']} -> {$chatId}");
                        $failCount++;
                    }
                }
            }
        }

        $output->writeln("发送完成: 成功 {$successCount} 条，失败 {$failCount} 条");
    }
}

