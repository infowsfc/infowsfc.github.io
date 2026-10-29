<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$allowedOrigins = ['https://infowsfc.github.io', 'https://wallstreetfoodcompany.nl', 'https://www.wallstreetfoodcompany.nl'];
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$json = strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$respond = static function (int $status, string $message) use ($json, $origin, $allowedOrigins): void {
    http_response_code($status);
    if ($json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $status === 200, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        $back = in_array($origin, $allowedOrigins, true) ? $origin : 'https://wallstreetfoodcompany.nl';
        echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Cateringaanvraag</title><body style="background:#0d0d0d;color:#fff;font:20px/1.6 Georgia,serif;max-width:720px;margin:10vh auto;padding:24px"><h1>Cateringaanvraag</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a style="color:#ceb563" href="' . htmlspecialchars($back . '/contact/', ENT_QUOTES, 'UTF-8') . '">Terug naar de website</a></body></html>';
    }
    exit;
};
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
} elseif ($origin !== '') {
    $respond(403, 'Verstuur uw aanvraag via onze website.');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    $respond(405, 'Alleen POST-verzoeken zijn toegestaan.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    $respond(413, 'Uw aanvraag is te lang. Kort de toelichting iets in en probeer opnieuw.');
}

if (!empty($_POST['website'] ?? '')) {
    $respond(200, 'Bedankt! Uw aanvraag is verzonden. Wij nemen contact met u op.');
}

$value = static function (string $key): string {
    $raw = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    return preg_replace('/[\r\n]+/', ' ', $raw) ?? '';
};

$name = $value('naam');
$email = filter_var($value('email'), FILTER_VALIDATE_EMAIL);
$message = is_string($_POST['bericht'] ?? null) ? trim($_POST['bericht']) : '';
$privacy = $value('privacy');

if ($name === '' || $email === false || $privacy !== 'akkoord') {
    $respond(422, 'Controleer uw naam, e-mailadres en privacytoestemming en probeer het opnieuw.');
}

// Keep only timestamps in the system temporary directory; never store form data.
$ratePath = sys_get_temp_dir() . '/wsfc-form-' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$rateFile = @fopen($ratePath, 'c+');
if ($rateFile && flock($rateFile, LOCK_EX)) {
    $times = json_decode(stream_get_contents($rateFile), true);
    $times = is_array($times) ? array_values(array_filter($times, static function ($time): bool { return is_int($time) && $time > time() - 900; })) : [];
    if (count($times) >= 5) {
        flock($rateFile, LOCK_UN);
        fclose($rateFile);
        header('Retry-After: 900');
        $respond(429, 'Er zijn kort achter elkaar meerdere aanvragen verstuurd. Probeer het over 15 minuten opnieuw of mail naar info@wsfc.nl.');
    }
    $times[] = time();
    ftruncate($rateFile, 0);
    rewind($rateFile);
    fwrite($rateFile, json_encode($times));
    flock($rateFile, LOCK_UN);
    fclose($rateFile);
} elseif ($rateFile) {
    fclose($rateFile);
}

$fields = [
    'Naam' => $name,
    'Organisatie' => $value('organisatie'),
    'E-mail' => (string) $email,
    'Telefoon' => $value('telefoon'),
    'Datum' => $value('datum'),
    'Locatie' => $value('locatie'),
    'Bezoekers' => $value('bezoekers'),
    'Concept' => $value('concept'),
    'Bericht' => $message,
];

$body = "Nieuwe cateringaanvraag via wallstreetfoodcompany.nl\n\n";
foreach ($fields as $label => $fieldValue) {
    $body .= $label . ': ' . $fieldValue . "\n";
}

$recipient = 'info@wsfc.nl';
$subject = 'Nieuwe cateringaanvraag van ' . $name;
$headers = [
    'From: WSFC Website <website@wallstreetfoodcompany.nl>',
    'Reply-To: ' . (string) $email,
    'Content-Type: text/plain; charset=UTF-8',
];

if (!@mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers))) {
    $respond(500, 'Het bericht kon niet worden verzonden. Probeer opnieuw of mail naar info@wsfc.nl.');
}

$respond(200, 'Bedankt! Uw aanvraag is verzonden. Wij nemen contact met u op.');
