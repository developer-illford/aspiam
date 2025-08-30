<?php
declare(strict_types=1);

/* ========= CONFIG ========= */
$toEmail         = 'vishnupriya.illforddigital@gmail.com'; // Receiver (your email)
$fromEmail       = 'admin@aspiam.com'; // Use your domain, not Gmail
$fromName        = 'ASPIAM Website';
$logo            = 'https://deepskyblue-louse-154940.hostingersite.com/images/Aspiam-New%20Logo%20500p.png'; // Absolute URL
$recaptchaSecret = '6LdpWK0rAAAAAC30VDRkaGMxBt73wt2e5JwUAA6y'; // From Google reCAPTCHA
/* ========================= */

function json_error(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize_string(?string $data): string {
    $data = trim((string)$data);
    $data = preg_replace('/[^\P{C}\t\r\n]+/u', '', $data) ?? '';
    return htmlspecialchars(strip_tags($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function sanitize_email(?string $email): string {
    $email = trim((string)$email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: '';
}
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Require POST + honeypot
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!empty($_POST['website'] ?? '')) { // Honeypot must be empty
    json_error('Access denied.', 403);
}

// Collect + sanitize inputs
$name    = sanitize_string($_POST['name'] ?? '');
$email   = sanitize_email($_POST['email'] ?? '');
$subject = sanitize_string($_POST['subject'] ?? 'Contact Form Submission');
$message = sanitize_string($_POST['message'] ?? '');

// Validation
$errors = [];
if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Name is required.';
if ($email === '') $errors[] = 'Valid email is required.';
if ($message === '' || mb_strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

// Verify Google reCAPTCHA
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
if ($recaptchaResponse === '') {
    $errors[] = 'reCAPTCHA verification failed.';
} else {
    $verifyUrl = "https://www.google.com/recaptcha/api/siteverify";
    $postData  = http_build_query([
        'secret'   => $recaptchaSecret,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 10,
        ]
    ];
    $result = @file_get_contents($verifyUrl, false, stream_context_create($opts));
    $decoded = @json_decode((string)$result, true);
    if (!($decoded['success'] ?? false)) {
        $errors[] = 'reCAPTCHA verification failed.';
    }
}
if (!empty($errors)) {
    json_error(implode(' ', $errors), 422);
}

// Safe escaped values
$escapedName    = e($name);
$escapedEmail   = e($email);
$escapedSubject = e($subject);
$escapedMsg     = nl2br(e($message), false);

// Wrap email template
function wrap_email(string $logoUrl, string $innerHtml): string {
    $logo = e($logoUrl);
    return "
    <div style='font-family:Arial,sans-serif;background-color:#f9f9f9;padding:20px;'>
      <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 0 10px rgba(0,0,0,.1);'>
        <div style='text-align:center;margin-bottom:16px;'>
          <img src='{$logo}' alt='Logo' style='max-height:60px;'>
        </div>
        {$innerHtml}
        <p style='font-size:12px;color:#888;margin-top:16px;'>This is an automated message from your website contact form.</p>
      </div>
    </div>";
}

// Build emails
$adminBody = wrap_email($logo, "
  <h2 style='color:#01796F;text-align:center;margin:0 0 12px;'>New Contact Form Submission</h2>
  <p><strong>Name:</strong> {$escapedName}</p>
  <p><strong>Email:</strong> {$escapedEmail}</p>
  <p><strong>Subject:</strong> {$escapedSubject}</p>
  <p><strong>Message:</strong></p>
  <blockquote style='background:#f4f4f4;padding:12px;border-left:3px solid #01796F;'>{$escapedMsg}</blockquote>
");

$autoBody = wrap_email($logo, "
  <h2 style='color:#01796F;text-align:center;'>Thank you, {$escapedName}!</h2>
  <p>We’ve received your message and will get back to you shortly.</p>
  <hr>
  <p><strong>Your Message:</strong></p>
  <blockquote style='background:#f4f4f4;padding:12px;border-left:3px solid #01796F;'>{$escapedMsg}</blockquote>
  <p style='font-size:12px;color:#888;'>This is an automated confirmation. Please do not reply.</p>
");

// Headers
$headersAdmin  = "MIME-Version: 1.0\r\n";
$headersAdmin .= "Content-type: text/html; charset=UTF-8\r\n";
$headersAdmin .= "From: {$fromName} <{$fromEmail}>\r\n";
$headersAdmin .= "Reply-To: {$escapedEmail}\r\n";

$headersAuto  = "MIME-Version: 1.0\r\n";
$headersAuto .= "Content-type: text/html; charset=UTF-8\r\n";
$headersAuto .= "From: {$fromName} <{$fromEmail}>\r\n";

// Send admin email
$ok1 = mail($toEmail, "New Message from {$escapedName}", $adminBody, $headersAdmin);

// Send auto-reply to user
$ok2 = mail($email, "We've received your message, {$escapedName}", $autoBody, $headersAuto);

if ($ok1 && $ok2) {
    echo "<script>window.location.href='thankyou.html';</script>";
    exit;
} else {
    json_error('Unable to send message. Please try again later.', 500);
}
