<?php
class SMSGateway
{
   // GHANA-PROVEN PROVIDERS (2025)
   private const PROVIDERS = [
      'hubtel' => [
         'url' => 'https://smsc.hubtel.com/v1/messages/send',
         'key' => 'YOUR_HUBTEL_CLIENT_ID',
         'secret' => 'YOUR_HUBTEL_CLIENT_SECRET',
         'sender' => 'AliveChMS'
      ],
      'textme' => [
         'url' => 'https://api.textme.com.gh/sms/send',
         'api_key' => 'YOUR_TEXTME_KEY',
         'sender' => 'AliveChMS'
      ],
      'mtn' => [ // MTN MoMo SMS (via developer portal)
         'url' => 'https://sms.mtn.com.gh/api/send',
         'token' => 'YOUR_MTN_TOKEN'
      ]
   ];

   public static function send(string $phone, string $message, string $provider = 'hubtel'): bool
   {
      $phone = preg_replace('/\D/', '', $phone);
      if (strlen($phone) === 10) $phone = '233' . substr($phone, 1); // Fix 024 → 23324
      if (strlen($phone) !== 12) return false;

      $config = self::PROVIDERS[$provider] ?? self::PROVIDERS['hubtel'];

      $payload = match ($provider) {
         'hubtel' => [
            'from' => $config['sender'],
            'to' => $phone,
            'content' => $message,
            'clientid' => $config['key'],
            'clientsecret' => $config['secret']
         ],
         'textme' => [
            'to' => $phone,
            'message' => $message,
            'sender_id' => $config['sender'],
            'api_key' => $config['api_key']
         ],
         default => []
      };

      $ch = curl_init($config['url']);
      curl_setopt_array($ch, [
         CURLOPT_POST => true,
         CURLOPT_POSTFIELDS => http_build_query($payload),
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_TIMEOUT => 10,
         CURLOPT_SSL_VERIFYPEER => true
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return $httpCode >= 200 && $httpCode < 300;
   }
}
