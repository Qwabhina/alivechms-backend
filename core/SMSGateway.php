<?php

/**
 * SMS Delivery Gateway – Pluggable Multi-Provider Architecture
 *
 * Fully abstracted SMS delivery system supporting multiple providers.
 * Designed for extensibility: any developer can add a new provider
 * by implementing the simple SmsProviderInterface.
 *
 * Default providers included:
 * - Hubtel (recommended for Ghana 2025)
 * - TextMe Ghana
 * - Generic HTTP (for any API with JSON POST)
 *
 * Provider is selected via .env: SMS_PROVIDER=hubtel|textme|generic
 *
 * @package  AliveChMS\Core
 * @version  1.0.2
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

/**
 * Interface for SMS providers.
 */
interface SmsProviderInterface
{
   /**
    * Send an SMS message.
    *
    * @param string $to Recipient phone number.
    * @param string $message Message content.
    * @return bool True on success.
    */
   public function send(string $to, string $message): bool;

   /**
    * Retrieve the last error message.
    *
    * @return string|null
    */
   public function getLastError(): ?string;
}

/**
 * Base class for common provider functionality.
 */
abstract class BaseSmsProvider implements SmsProviderInterface
{
   /** @var string|null $lastError Holds last provider error. */
   protected ?string $lastError = null;

   /**
    * @inheritdoc
    */
   public function getLastError(): ?string
   {
      return $this->lastError;
   }

   /**
    * Normalize a Ghanaian phone number to international format.
    *
    * @param string $phone
    * @return string
    */
   protected function normalizePhone(string $phone): string
   {
      $phone = preg_replace('/\D/', '', $phone);
      if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
         $phone = '233' . substr($phone, 1);
      }
      return $phone;
   }
}

/**
 * Hubtel SMS Provider.
 */
class HubtelProvider extends BaseSmsProvider
{
   /** @var string Hubtel API URL */
   private string $url = 'https://smsc.hubtel.com/v1/messages/send';

   /**
    * @inheritdoc
    */
   public function send(string $to, string $message): bool
   {
      $to = $this->normalizePhone($to);
      if (strlen($to) !== 12 || !str_starts_with($to, '233')) {
         $this->lastError = "Invalid Ghana phone number: $to";
         return false;
      }

      $payload = [
         'from'          => $_ENV['HUBTEL_SENDER'] ?? 'AliveChMS',
         'to'            => $to,
         'content'       => $message,
         'clientid'      => $_ENV['HUBTEL_CLIENT_ID'] ?? '',
         'clientsecret'  => $_ENV['HUBTEL_CLIENT_SECRET'] ?? ''
      ];

      return $this->makeRequest($payload);
   }

   /**
    * Make API request to Hubtel.
    *
    * @param array $payload
    * @return bool
    */
   private function makeRequest(array $payload): bool
   {
      $ch = curl_init($this->url);
      curl_setopt_array($ch, [
         CURLOPT_POST           => true,
         CURLOPT_POSTFIELDS     => http_build_query($payload),
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_TIMEOUT        => 15,
         CURLOPT_SSL_VERIFYPEER => true,
         CURLOPT_FOLLOWLOCATION => true,
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error    = curl_error($ch);
      $ch = null;

      if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
         return true;
      }

      $this->lastError = "Hubtel failed | Code: $httpCode | Error: $error | Response: " . substr($response ?: '', 0, 200);
      return false;
   }
}

/**
 * TextMe Ghana SMS Provider.
 */
class TextMeProvider extends BaseSmsProvider
{
   /** @var string TextMe API URL */
   private string $url = 'https://api.textme.com.gh/sms/send';

   /**
    * @inheritdoc
    */
   public function send(string $to, string $message): bool
   {
      $to = $this->normalizePhone($to);

      $payload = [
         'to'         => $to,
         'message'    => $message,
         'sender_id'  => $_ENV['TEXTME_SENDER'] ?? 'AliveChMS',
         'api_key'    => $_ENV['TEXTME_API_KEY'] ?? ''
      ];

      $ch = curl_init($this->url);
      curl_setopt_array($ch, [
         CURLOPT_POST           => true,
         CURLOPT_POSTFIELDS     => http_build_query($payload),
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_TIMEOUT        => 15,
         CURLOPT_SSL_VERIFYPEER => true,
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $ch = null;

      $success = $httpCode === 200;

      if (!$success) {
         $this->lastError = "TextMe failed | Code: $httpCode | Response: " . substr($response ?: '', 0, 200);
      }

      return $success;
   }
}

/**
 * Generic HTTP SMS Provider.
 */
class GenericHttpProvider extends BaseSmsProvider
{
   /**
    * @inheritdoc
    */
   public function send(string $to, string $message): bool
   {
      $url     = $_ENV['GENERIC_SMS_URL']     ?? '';
      $method  = $_ENV['GENERIC_SMS_METHOD']  ?? 'POST';
      $headers = $this->parseHeaders($_ENV['GENERIC_SMS_HEADERS'] ?? '');
      $body    = $this->parseBody($_ENV['GENERIC_SMS_BODY'] ?? '', $to, $message);

      if (empty($url)) {
         $this->lastError = 'GENERIC_SMS_URL not configured';
         return false;
      }

      $ch = curl_init($url);
      curl_setopt_array($ch, [
         CURLOPT_CUSTOMREQUEST  => $method,
         CURLOPT_POSTFIELDS     => is_array($body) ? json_encode($body) : $body,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_TIMEOUT        => 20,
         CURLOPT_SSL_VERIFYPEER => true,
         CURLOPT_HTTPHEADER     => $headers,
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error    = curl_error($ch);
      $ch = null;

      $success = $httpCode >= 200 && $httpCode < 300;

      if (!$success) {
         $this->lastError = "Generic SMS failed | Code: $httpCode | Error: $error";
      }

      return $success;
   }

   /**
    * Parse header block into an array.
    *
    * @param string $raw
    * @return array
    */
   private function parseHeaders(string $raw): array
   {
      $headers = [];
      foreach (explode("\n", $raw) as $line) {
         if (trim($line) && strpos($line, ':') !== false) {
            [$key, $val] = explode(':', $line, 2);
            $headers[] = trim($key) . ':' . trim($val);
         }
      }
      return $headers;
   }

   /**
    * Parse message body template.
    *
    * @param string $template
    * @param string $to
    * @param string $message
    * @return string|array
    */
   private function parseBody(string $template, string $to, string $message): array|string
   {
      $replacements = [
         '{to}'      => $to,
         '{message}' => $message,
         '{sender}'  => $_ENV['SMS_SENDER_ID'] ?? 'AliveChMS'
      ];

      return str_replace(array_keys($replacements), array_values($replacements), $template);
   }
}

/**
 * SMS Gateway Controller.
 */
class SMSGateway
{
   /** @var SmsProviderInterface|null Cached provider instance */
   private static ?SmsProviderInterface $provider = null;

   /**
    * Resolve the provider based on ENV configuration.
    *
    * @return SmsProviderInterface
    */
   private static function getProvider(): SmsProviderInterface
   {
      if (self::$provider !== null) {
         return self::$provider;
      }

      $providerName = strtoupper($_ENV['SMS_PROVIDER'] ?? 'HUBTEL');

      return match ($providerName) {
         'HUBTEL'  => self::$provider = new HubtelProvider(),
         'TEXTME'  => self::$provider = new TextMeProvider(),
         'GENERIC' => self::$provider = new GenericHttpProvider(),
         default   => self::$provider = new HubtelProvider(),
      };
   }

   /**
    * Send SMS using the configured provider.
    *
    * @param string $phone Recipient phone number.
    * @param string $message Message content.
    * @return bool True on success.
    */
   public static function send(string $phone, string $message): bool
   {
      $provider = self::getProvider();
      $success  = $provider->send($phone, $message);

      if (!$success) {
         Helpers::logError(
            "SMS delivery failed | Provider: " . get_class($provider) .
               " | Error: " . $provider->getLastError()
         );
      }

      return $success;
   }
}
