<?php

/**
 * LINE Messaging API helpers
 *  - sendRepairNotification(): push Flex Message เข้า group Lab Boy
 */

function lineConfig(string $key, bool $required = true): string
{
   $value = getenv($key);
   if ($value === false || $value === '') {
      $value = $_ENV[$key] ?? '';
   }
   $value = trim((string)$value);
   if ($required && $value === '') {
      throw new RuntimeException($key . ' is not configured');
   }
   return $value;
}

function lineApiPost(string $url, array $payload): void
{
   if (filter_var(getenv('REPS_E2E') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
      throw new RuntimeException('LINE notifications are disabled in E2E mode');
   }
   if (!function_exists('curl_init')) {
      throw new RuntimeException('The PHP cURL extension is not enabled');
   }
   $token = lineConfig('LINE_CHANNEL_ACCESS_TOKEN');
   $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

   $handle = curl_init($url);
   if ($handle === false) {
      throw new RuntimeException('Could not initialize LINE request');
   }
   $configured = curl_setopt_array($handle, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
         'Content-Type: application/json',
         'Accept: application/json',
         'Authorization: Bearer ' . $token,
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => $json,
      CURLOPT_USERAGENT => 'REPS LINE notifier/1.0',
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
   ]);
   if (!$configured) {
      curl_close($handle);
      throw new RuntimeException('Could not configure LINE request');
   }

   $response = curl_exec($handle);
   $curlError = curl_error($handle);
   $curlErrno = curl_errno($handle);
   $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
   curl_close($handle);

   if ($response === false) {
      throw new RuntimeException('LINE request failed (' . $curlErrno . '): ' . ($curlError !== '' ? $curlError : 'unknown cURL error'));
   }
   if ($httpCode !== 200) {
      $errorBody = json_decode($response, true);
      if (is_array($errorBody)) {
         $errorMessage = trim((string)($errorBody['message'] ?? ''));
         $errorDetails = $errorBody['details'] ?? null;
         if (is_array($errorDetails)) {
            $detailMessages = [];
            foreach ($errorDetails as $detail) {
               if (is_array($detail) && isset($detail['message'])) {
                  $detailMessages[] = (string)$detail['message'];
               }
            }
            if ($detailMessages) {
               $errorMessage .= ($errorMessage !== '' ? ' · ' : '') . implode(' · ', $detailMessages);
            }
         }
         if ($errorMessage !== '') {
            throw new RuntimeException('LINE API returned HTTP ' . $httpCode . ': ' . $errorMessage);
         }
      }
      throw new RuntimeException('LINE API returned HTTP ' . $httpCode . ' Body: ' . $response);
   }
}

function lineBuildTicketText(array $ticket): string
{
   $urgencyLabels = ['urgent' => 'ด่วน', 'normal' => 'ปกติ'];
   $urgency = (string)$ticket['urgency'];
   $requester = trim((string)$ticket['first_name'] . ' ' . (string)$ticket['last_name']);
   $symptom = lineMainSymptom((string)$ticket['symptom']);
   $text = implode("\n", [
      'REPS · งานซ่อมใหม่',
      'เลขที่ใบงาน: #' . (string)$ticket['ticket_no'],
      'ความเร่งด่วน: ' . ($urgencyLabels[$urgency] ?? $urgency),
      'หมวดหมู่: ' . (string)$ticket['category_name'],
      'สถานที่: ' . (string)$ticket['location_name'],
      'ผู้แจ้ง: ' . ($requester !== '' ? $requester : '-'),
      'รายละเอียด: ' . $symptom,
   ]);

   return mb_substr($text, 0, 5000, 'UTF-8');
}

function lineMainSymptom(string $symptom): string
{
   $symptom = trim($symptom);
   $detailsMarker = "\n\nรายละเอียด:";
   $detailsPosition = strpos($symptom, $detailsMarker);

   if ($detailsPosition !== false) {
      return trim(substr($symptom, $detailsPosition + strlen($detailsMarker)));
   }

   // รองรับข้อมูลเก่าที่อาจมีเฉพาะบรรทัด "อาการย่อย: ..."
   if (str_starts_with($symptom, 'อาการย่อย:')) {
      return trim((string)preg_replace('/^.*\R/u', '', $symptom));
   }

   return $symptom;
}

function lineBuildTicketFlex(array $ticket): array
{
   $urgencyLabels = ['urgent' => 'ด่วน', 'normal' => 'ปกติ'];
   $urgencyColors = ['urgent' => '#FCA5A5', 'normal' => '#FDBA74'];

   $urgency = (string)$ticket['urgency'];
   $urgencyLabel = $urgencyLabels[$urgency] ?? $urgency;
   $urgencyColor = $urgencyColors[$urgency] ?? '#CBD5E1';
   $symptom = lineMainSymptom((string)$ticket['symptom']);
   if (mb_strlen($symptom, 'UTF-8') > 240) {
      $symptom = mb_substr($symptom, 0, 237, 'UTF-8') . '...';
   }
   $requester = trim($ticket['first_name'] . ' ' . $ticket['last_name']);
   $phone = trim((string)($ticket['phone_number'] ?? ''));

   $row = static function (string $label, string $value): array {
      return [
         'type' => 'box',
         'layout' => 'baseline',
         'spacing' => 'sm',
         'contents' => [
            ['type' => 'text', 'text' => $label, 'size' => 'sm', 'color' => '#8C8C8C', 'flex' => 2],
            ['type' => 'text', 'text' => $value !== '' ? $value : '-', 'size' => 'sm', 'color' => '#172033', 'wrap' => true, 'flex' => 5],
         ],
      ];
   };

   $footerButtons = [];
   $baseUrl = rtrim(lineConfig('APP_BASE_URL', false), '/');
   if (preg_match('#^https?://#i', $baseUrl)) {
      $footerButtons[] = [
         'type' => 'button',
         'style' => 'link',
         'height' => 'sm',
         'action' => [
            'type' => 'uri',
            'label' => 'ดูในระบบ',
            'uri' => $baseUrl . '/admin/admin_manage_request.php',
         ],
      ];
   }

   $bubble = [
      'type' => 'bubble',
      'header' => [
         'type' => 'box',
         'layout' => 'vertical',
         'backgroundColor' => '#000000',
         'paddingAll' => 'lg',
         'contents' => [
            ['type' => 'text', 'text' => 'REPS  ·  งานซ่อมใหม่', 'color' => '#94A3B8', 'weight' => 'bold', 'size' => 'xs'],
            ['type' => 'text', 'text' => '#' . (string)$ticket['ticket_no'], 'color' => '#FFFFFF', 'weight' => 'bold', 'size' => 'xl', 'margin' => 'sm'],
            [
               'type' => 'box',
               'layout' => 'horizontal',
               'margin' => 'md',
               'contents' => [
                  ['type' => 'text', 'text' => 'ความเร่งด่วน', 'color' => '#CBD5E1', 'size' => 'xs', 'flex' => 4],
                  ['type' => 'text', 'text' => $urgencyLabel, 'color' => $urgencyColor, 'weight' => 'bold', 'size' => 'sm', 'align' => 'end', 'flex' => 3],
               ],
            ],
         ],
      ],
      'body' => [
         'type' => 'box',
         'layout' => 'vertical',
         'spacing' => 'sm',
         'paddingAll' => 'lg',
         'contents' => [
            $row('หมวดหมู่', (string)$ticket['category_name']),
            $row('สถานที่', (string)$ticket['location_name']),
            [
               'type' => 'box',
               'layout' => 'vertical',
               'backgroundColor' => '#F8FAFC',
               'cornerRadius' => 'md',
               'margin' => 'md',
               'paddingAll' => 'md',
               'contents' => [
                  ['type' => 'text', 'text' => 'รายละเอียดอาการ', 'size' => 'xs', 'color' => '#64748B', 'weight' => 'bold'],
                  ['type' => 'text', 'text' => $symptom !== '' ? $symptom : '-', 'size' => 'sm', 'color' => '#172033', 'wrap' => true, 'margin' => 'sm'],
               ],
            ],
            ['type' => 'separator', 'margin' => 'md'],
            $row('ผู้แจ้ง', $requester),
            ...($phone !== '' ? [$row('โทร', $phone)] : []),
         ],
      ],
   ];
   if ($footerButtons) {
      $bubble['footer'] = [
         'type' => 'box',
         'layout' => 'vertical',
         'spacing' => 'sm',
         'contents' => $footerButtons,
      ];
   }

   return [
      'type' => 'flex',
      'altText' => mb_substr('งานซ่อมใหม่ #' . $ticket['ticket_no'] . ' · ' . $urgencyLabel . ' · ' . $ticket['location_name'], 0, 390, 'UTF-8'),
      'contents' => $bubble,
   ];
}

function sendRepairNotification(mysqli $conn, int $ticketId): void
{
   $targetId = lineConfig('LINE_TARGET_ID');

   $stmt = $conn->prepare(
      'SELECT t.ticket_no, t.symptom, t.urgency,
              u.first_name, u.last_name, u.phone_number,
              c.name AS category_name,
              t.location_note AS location_name
         FROM tickets t
         JOIN users u ON u.user_id = t.requester_id
         JOIN categories c ON c.id = t.category_id
        WHERE t.id = ?
        LIMIT 1'
   );
   if (!$stmt) {
      throw new RuntimeException('Could not prepare LINE notification data query: ' . $conn->error);
   }
   $stmt->bind_param('i', $ticketId);
   if (!$stmt->execute()) {
      $error = $stmt->error;
      $stmt->close();
      throw new RuntimeException('Could not load LINE notification data: ' . $error);
   }
   $ticket = $stmt->get_result()->fetch_assoc();
   $stmt->close();

   if (!$ticket) {
      throw new RuntimeException('Ticket for LINE notification was not found');
   }

   try {
      lineApiPost('https://api.line.me/v2/bot/message/push', [
         'to' => $targetId,
         'messages' => [lineBuildTicketFlex($ticket)],
      ]);
   } catch (RuntimeException $flexError) {
      // A plain text message keeps notifications working if LINE rejects a Flex payload.
      // Authentication, target, and network errors are rethrown unchanged.
      if (!str_contains($flexError->getMessage(), 'LINE API returned HTTP 400')) {
         throw $flexError;
      }

      lineApiPost('https://api.line.me/v2/bot/message/push', [
         'to' => $targetId,
         'messages' => [
            ['type' => 'text', 'text' => lineBuildTicketText($ticket)],
         ],
      ]);
   }
}
