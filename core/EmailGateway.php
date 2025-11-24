<?php

use PHPMailer\PHPMailer\PHPMailer;

class EmailGateway
{
   public static function send(string $to, string $subject, string $body): bool
   {
      $mail = new PHPMailer(true);
      try {
         // Use your church's Gmail or Zoho or SMTP
         $mail->isSMTP();
         $mail->Host       = $_ENV['SMTP_HOST'];        // e.g., smtp.gmail.com
         $mail->SMTPAuth   = true;
         $mail->Username   = $_ENV['SMTP_USER'];
         $mail->Password   = $_ENV['SMTP_PASS'];
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

         $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], 'AliveChMS');
         $mail->addAddress($to);
         $mail->isHTML(true);
         $mail->Subject = $subject;
         $mail->Body    = $body;

         $mail->send();
         return true;
      } catch (Exception $e) {
         Helpers::logError("Email failed: {$mail->ErrorInfo}");
         return false;
      }
   }
}
