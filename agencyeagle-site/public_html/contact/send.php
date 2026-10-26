<?php
// Contact form handler: validates, drops bots (honeypot + timing-free), emails sales@.
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact/');
    exit;
}
$clean = static fn(string $k, int $max): string => trim(mb_substr(str_replace(["\r", "\n"], ' ', (string)($_POST[$k] ?? '')), 0, $max));

if (($_POST['website'] ?? '') !== '') {      // honeypot filled: pretend success
    header('Location: /contact/thanks/');
    exit;
}
$name = $clean('name', 100);
$email = $clean('email', 200);
$phone = $clean('phone', 40);
$message = trim(mb_substr((string)($_POST['message'] ?? ''), 0, 5000));

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo 'Please go back and fill in your name, a valid email and a message.';
    exit;
}

$body = "Name: $name\nEmail: $email\nPhone: $phone\n\n$message\n";
$headers = "From: Agency Eagle Website <no-reply@agencyeagle.com>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=utf-8";
mail('sales@agencyeagle.com', 'Website inquiry from ' . $name, $body, $headers);

header('Location: /contact/thanks/');
