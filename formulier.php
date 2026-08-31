<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Alleen POST-verzoeken zijn toegestaan.');
}

if (!empty($_POST['website'] ?? '')) {
    header('Location: /contact/?verzonden=1', true, 303);
    exit;
}

$value = static function (string $key): string {
    $raw = trim((string) ($_POST[$key] ?? ''));
    return preg_replace('/[\r\n]+/', ' ', $raw) ?? '';
};

$name = $value('naam');
$email = filter_var($value('email'), FILTER_VALIDATE_EMAIL);
$message = trim((string) ($_POST['bericht'] ?? ''));
$privacy = $value('privacy');

if ($name === '' || $email === false || $message === '' || $privacy !== 'akkoord') {
    http_response_code(422);
    exit('Controleer de verplichte velden en probeer het opnieuw.');
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

if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
    http_response_code(500);
    exit('Het bericht kon niet worden verzonden. Neem telefonisch of per e-mail contact met ons op.');
}

header('Location: /contact/?verzonden=1', true, 303);
exit;
