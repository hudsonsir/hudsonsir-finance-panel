<?php

namespace app\service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use think\facade\Log;
use think\facade\Db;

class EmailService
{
    /**
     * 获取邮件通道配置（从新表）
     * @return array|null
     */
    private static function getEmailChannelConfig()
    {
        $emailChannel = Db::name('notification')
            ->where('channel_type', 'email')
            ->where('enabled', 1)
            ->order('id', 'asc')
            ->find();
        
        if (!$emailChannel) {
            return null;
        }
        
        $config = json_decode($emailChannel['config'], true);
        return $config ?: null;
    }

    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body HTML邮件内容
     * @return bool
     */
    public static function send($to, $subject, $body)
    {
        $channelConfig = self::getEmailChannelConfig();

        if (!$channelConfig) {
            $smtpHost = config_get('email_smtp_host', '');
            $smtpPort = intval(config_get('email_smtp_port', 465));
            $smtpSecure = config_get('email_smtp_secure', 'ssl');
            $smtpUsername = config_get('email_smtp_username', '');
            $smtpPassword = config_get('email_smtp_password', '');
            $fromEmail = config_get('email_from_email', $smtpUsername);
            $fromName = config_get('email_from_name', '财务工具系统');
        } else {
            $smtpHost = $channelConfig['smtp_host'] ?? '';
            $smtpPort = intval($channelConfig['smtp_port'] ?? 465);
            $smtpSecure = $channelConfig['smtp_secure'] ?? 'ssl';
            $smtpUsername = $channelConfig['smtp_username'] ?? '';
            $smtpPassword = $channelConfig['smtp_password'] ?? '';
            $fromEmail = $channelConfig['from_email'] ?? $smtpUsername;
            $fromName = $channelConfig['from_name'] ?? '财务工具系统';
        }

        if (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword)) {
            Log::error('邮件配置不完整，无法发送邮件');
            return false;
        }

        if (empty($to)) {
            Log::error('收件人邮箱为空');
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port = $smtpPort;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName);

            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            Log::info("邮件发送成功: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("邮件发送失败: {$to}, 错误: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * 获取默认邮件模板
     * @return string
     */
    public static function getDefaultTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin:0; padding:0;
      background:#f5f1e6; /* 背景 */
      font-family: "Libre Baskerville","Lora","Segoe UI",Roboto,"Helvetica Neue",Arial,serif;
      color:#4a3f35;
    }
    .container {
      max-width:600px;
      margin:28px auto;
      background:#fffcf5; /* card */
      border:1px solid #dbd0ba;
      border-radius:6px;
      box-shadow:2px 3px 5px rgba(74,63,53,0.12);
      overflow:hidden;
    }
    .header {
      background:#a67c52; /* primary */
      color:#ffffff;
      padding:20px 24px;
      font-weight:700;
      letter-spacing:0.3px;
      font-size:17px;
    }
    .body {
      padding:24px;
    }
    h1 {
      margin:0 0 16px 0;
      font-size:20px;
      color:#4a3f35;
    }
    p {
      margin:0 0 16px 0;
      line-height:1.6;
      color:#5c4d3f;
    }
    .card {
      background:#fffdf9;
      border:1px solid #ece5d8;
      padding:16px;
      border-radius:4px;
      margin-bottom:18px;
    }
    .meta {
      font-size:14px;
      font-weight:600;
      color:#4a3f35;
    }
    .cta {
      display:inline-block;
      padding:12px 18px;
      border-radius:6px;
      text-decoration:none;
      background:#a67c52;
      color:#ffffff;
      font-weight:600;
      box-shadow:2px 3px 5px rgba(74,63,53,0.15);
    }
    .muted {
      font-size:12px;
      color:#7d6b56;
      margin-top:10px;
    }
    .footer {
      text-align:center;
      padding:16px;
      font-size:12px;
      background:#ece5d8;
      color:#4a3f35;
      border-top:1px solid #dbd0ba;
    }
    @media (max-width:420px) {
      .container { margin:12px; }
      .header, .body, .footer { padding-left:16px; padding-right:16px; }
    }
  </style>
</head>
<body>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center">
      <div class="container" role="article" aria-labelledby="title">
        <div class="header">财务统计工具</div>
        <div class="body">
          <h1 id="title">服务到期提醒 — {{product}}</h1>
          <div class="card" role="group" aria-label="到期信息">
            <div class="meta">产品：{{product}}</div>
            <div style="height:6px"></div>
            <div class="meta">到期时间：{{expire_date}}</div>
          </div>
          <p>您好，</p>
          <p>您的 <strong>{{product}}</strong> 将于 <strong>{{expire_date}}</strong> 到期。为了避免服务中断，请及时续费。</p>
          <p class="muted">若已续费，请忽略此邮件。</p>
        </div>
        <div class="footer">
          © ' . date('Y') . ' 财务系统
        </div>
      </div>
    </td>
  </tr>
</table>
</body>
</html>';
    }

    /**
     * 发送到期提醒邮件
     * @param string $to 收件人邮箱
     * @param string $productName 产品名称
     * @param string $expireDate 到期日期
     * @return bool
     */
    public static function sendExpireReminder($to, $productName, $expireDate)
    {
        // 从新表获取邮件模板和主题
        $channelConfig = self::getEmailChannelConfig();
        
        if ($channelConfig) {
            $template = $channelConfig['template'] ?? '';
            $subject = $channelConfig['subject'] ?? '产品到期提醒';
        } else {
            $template = config_get('email_template', '');
            $subject = config_get('email_subject', '产品到期提醒');
        }

        if (empty($template)) {
            $template = self::getDefaultTemplate();
        }

        $body = str_replace('{{product}}', htmlspecialchars($productName), $template);
        $body = str_replace('{{expire_date}}', htmlspecialchars($expireDate), $body);

        return self::send($to, $subject, $body);
    }
}
