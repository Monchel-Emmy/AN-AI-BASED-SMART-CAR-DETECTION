<?php
/**
 * WhatsApp notification via CallMeBot
 *
 * NOTE: CallMeBot blocks server-side curl requests from localhost.
 * On a real hosted server this works fine via sendWhatsApp().
 * For local development, the dashboard uses buildWhatsAppUrl() to
 * trigger the call from the browser instead (browser requests are allowed).
 */

define('WHATSAPP_PHONE',   '250780904149');  // No + prefix
define('WHATSAPP_API_KEY', '6737250');

/**
 * Build the CallMeBot URL for a given message.
 * Used by the dashboard JS to fire from the browser (bypasses localhost block).
 */
function buildWhatsAppUrl(string $message): string {
    $text = urlencode($message);
    return "https://api.callmebot.com/whatsapp.php"
         . "?phone=" . WHATSAPP_PHONE
         . "&text={$text}"
         . "&apikey=" . WHATSAPP_API_KEY;
}

/**
 * Send WhatsApp via server-side curl.
 * Works on real hosted servers. Returns false on localhost (403 from CallMeBot).
 */
function sendWhatsApp(string $message): bool {
    $url = buildWhatsAppUrl($message);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $log = date('Y-m-d H:i:s') . " | HTTP {$httpCode} | err: {$curlErr} | response: {$response}\n";
    file_put_contents(__DIR__ . '/whatsapp.log', $log, FILE_APPEND);

    return $httpCode === 200;
}

/**
 * Build the accident alert message text.
 */
function buildAccidentMessage(array $accident, array $driver, array $vehicle): string {
    $severity = strtoupper($accident['severity']);
    $plate    = $vehicle['plate_number'] ?? 'Unknown';
    $name     = $driver['full_name']     ?? 'Unknown Driver';
    $phone    = $driver['phone']         ?? 'N/A';
    $time     = $accident['created_at']  ?? date('Y-m-d H:i:s');
    $lat      = $accident['latitude']    ?? null;
    $lng      = $accident['longitude']   ?? null;

    $locationLink = ($lat && $lng)
        ? "https://maps.google.com/?q={$lat},{$lng}"
        : 'Location unavailable';

    // Plain ASCII only — no emojis or box-drawing chars (CallMeBot blocks them)
    return "ACCIDENT ALERT - {$severity}\n"
         . "Driver: {$name}\n"
         . "Phone: {$phone}\n"
         . "Plate: {$plate}\n"
         . "Time: {$time}\n"
         . "Location: {$locationLink}\n"
         . "Accel: X={$accident['accel_x']} Y={$accident['accel_y']} Z={$accident['accel_z']}\n"
         . "Gyro: X={$accident['gyro_x']} Y={$accident['gyro_y']} Z={$accident['gyro_z']}";
}
