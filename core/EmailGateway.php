<?php

/**
 * Email Delivery Gateway
 *
 * Secure, reusable SMTP email sender using PHPMailer.
 * Configured entirely from .env – supports Gmail, Zoho, custom SMTP.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailGateway
{
   /**
    * Send an email message
    *
    * @param string $to      Recipient email address
    * @param string $subject Email subject line
    * @param string $body    HTML body content
    * @return bool           True on success, false on failure
    */
   public static function send(string $to, string $subject, string $body): bool
   {
      $mail = new PHPMailer(true);
      try {
         $mail->isSMTP();
         $mail->Host       = $_ENV['SMTP_HOST']       ?? 'smtp.gmail.com';
         $mail->SMTPAuth   = true;
         $mail->Username   = $_ENV['SMTP_USER']       ?? '';
         $mail->Password   = $_ENV['SMTP_PASS']       ?? '';
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);

         $mail->setFrom($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@alivechms.org', 'AliveChMS');
         $mail->addAddress($to);
         $mail->isHTML(true);
         $mail->Subject = $subject;
         $mail->Body    = $body;

         $mail->send();
         return true;
      } catch (Exception $e) {
         Helpers::logError("Email delivery failed to $to: " . $mail->ErrorInfo);
         return false;
      }
   }
}