<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private static function make(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME'] ?? '');
        return $mail;
    }

    public static function send(string $to, string $subject, string $html, string $plain = ''): void
    {
        $mail = self::make();
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain ?: strip_tags($html);
        $mail->send();
    }

    public static function sendOrderConfirmation(array $order, array $user): void
    {
        $id    = $order['id'];
        $total = number_format($order['total_price'], 2);
        $html  = "<h2>Order #{$id} Confirmed</h2>
                  <p>Hi {$user['name']}, your order has been received.</p>
                  <p><strong>Total:</strong> {$total} Ft</p>
                  <p>We'll notify you when it's on its way.</p>";

        self::send($user['email'], "Order #{$id} Confirmed", $html);
    }

    public static function sendPasswordReset(string $to, string $name, string $resetLink): void
    {
        $html = "<h2>Password Reset</h2>
                 <p>Hi {$name},</p>
                 <p>Click the link below to reset your password. It expires in 1 hour.</p>
                 <p><a href='{$resetLink}'>Reset Password</a></p>
                 <p>If you didn't request this, ignore this email.</p>";

        self::send($to, 'Reset Your Password', $html);
    }

    public static function sendEmailVerification(string $to, string $name, string $verifyLink): void
    {
        $html = "<h2>Verify Your Email</h2>
                 <p>Hi {$name}, thanks for registering!</p>
                 <p>Click the link below to verify your email address.</p>
                 <p><a href='{$verifyLink}'>Verify Email</a></p>
                 <p>If you didn't create an account, ignore this email.</p>";

        self::send($to, 'Verify Your Email Address', $html);
    }

    public static function sendLoginNotification(string $to, string $name, string $ip, array $device): void
    {
        $type    = $device['device_type'] ?? 'unknown';
        $browser = $device['browser']     ?? 'unknown';
        $os      = $device['os']          ?? 'unknown';
        $time    = date('Y-m-d H:i:s');

        $html = "<h2>New Login Detected</h2>
                 <p>Hi {$name},</p>
                 <p>A new login to your account was detected:</p>
                 <ul>
                     <li><strong>Time:</strong> {$time}</li>
                     <li><strong>IP:</strong> {$ip}</li>
                     <li><strong>Device:</strong> {$type}</li>
                     <li><strong>Browser:</strong> {$browser}</li>
                     <li><strong>OS:</strong> {$os}</li>
                 </ul>
                 <p>If this wasn't you, change your password immediately.</p>";

        self::send($to, 'New Login to Your Account', $html);
    }
}
