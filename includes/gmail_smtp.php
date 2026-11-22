<?php

/**
 * Small Gmail SMTP sender for password-reset messages.
 * Uses STARTTLS on smtp.gmail.com:587 and Google App Password authentication.
 */
function gmailSmtpIsConfigured(): bool
{
   $username = trim((string)(getenv('MAIL_USERNAME') ?: getenv('MAIL_FROM') ?: ''));
   return $username !== ''
      && preg_replace('/\s+/', '', trim((string)(getenv('MAIL_PASSWORD') ?: ''))) !== ''
      && filter_var($username, FILTER_VALIDATE_EMAIL) !== false
      && filter_var(trim((string)(getenv('MAIL_FROM') ?: '')), FILTER_VALIDATE_EMAIL) !== false
      && extension_loaded('openssl');
}

function gmailSmtpRead($socket, array $expectedCodes): string
{
   $response = '';
   while (($line = fgets($socket, 515)) !== false) {
      $response .= $line;
      if (strlen($line) < 4 || $line[3] !== '-') {
         $code = (int)substr($line, 0, 3);
         if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Gmail SMTP rejected a command (response ' . $code . ')');
         }
         return $response;
      }
   }

   throw new RuntimeException('No response from Gmail SMTP');
}

function gmailSmtpCommand($socket, string $command, array $expectedCodes): string
{
   if (fwrite($socket, $command . "\r\n") === false) {
      throw new RuntimeException('Could not write to Gmail SMTP');
   }
   return gmailSmtpRead($socket, $expectedCodes);
}

function sendGmailSmtpMail(string $recipient, string $subject, string $body): void
{
   $username = trim((string)(getenv('MAIL_USERNAME') ?: getenv('MAIL_FROM') ?: ''));
   $password = preg_replace('/\s+/', '', trim((string)(getenv('MAIL_PASSWORD') ?: '')));
   $from = trim((string)(getenv('MAIL_FROM') ?: ''));

   if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('A valid recipient, MAIL_FROM, and MAIL_USERNAME are required');
   }
   if ($password === '' || !extension_loaded('openssl')) {
      throw new RuntimeException('Gmail SMTP credentials or OpenSSL are not configured');
   }

   $host = 'smtp.gmail.com';
   $context = stream_context_create([
      'ssl' => [
         'verify_peer' => true,
         'verify_peer_name' => true,
         'peer_name' => $host,
      ],
   ]);
   $socket = @stream_socket_client('tcp://' . $host . ':587', $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
   if (!is_resource($socket)) {
      throw new RuntimeException('Could not connect to Gmail SMTP (error ' . $errno . ')');
   }

   stream_set_timeout($socket, 15);
   try {
      gmailSmtpRead($socket, [220]);
      gmailSmtpCommand($socket, 'EHLO reps.local', [250]);
      gmailSmtpCommand($socket, 'STARTTLS', [220]);
      if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
         throw new RuntimeException('Could not establish TLS with Gmail SMTP');
      }

      gmailSmtpCommand($socket, 'EHLO reps.local', [250]);
      gmailSmtpCommand($socket, 'AUTH LOGIN', [334]);
      gmailSmtpCommand($socket, base64_encode($username), [334]);
      gmailSmtpCommand($socket, base64_encode($password), [235]);
      gmailSmtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
      gmailSmtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
      gmailSmtpCommand($socket, 'DATA', [354]);

      $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
      $headers = [
         'From: REPS <' . $from . '>',
         'To: ' . $recipient,
         'Subject: ' . $encodedSubject,
         'Date: ' . date(DATE_RFC2822),
         'MIME-Version: 1.0',
         'Content-Type: text/plain; charset=UTF-8',
         'Content-Transfer-Encoding: 8bit',
         'X-Content-Type-Options: nosniff',
      ];
      $normalizedBody = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
      $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
      $message = preg_replace('/(?m)^\./', '..', $message);
      if (fwrite($socket, $message . "\r\n.\r\n") === false) {
         throw new RuntimeException('Could not send message data to Gmail SMTP');
      }
      gmailSmtpRead($socket, [250]);
      gmailSmtpCommand($socket, 'QUIT', [221]);
   } finally {
      fclose($socket);
   }
}
