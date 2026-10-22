<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../../vendor/autoload.php';

function sendTemporaryPasswordEmail(
    string $email,
    string $name,
    string $temporaryPassword
): bool {
    $senderEmail = getenv('MAIL_FROM') ?: 'noreply@reddiph.com';
    $smtpHost = getenv('MAIL_HOST');
    $smtpUsername = getenv('MAIL_USERNAME');
    $smtpPassword = getenv('MAIL_PASSWORD');
    $smtpPort = (int) (getenv('MAIL_PORT') ?: 587);
    $smtpSettingsPresent = $smtpHost || $smtpUsername || $smtpPassword;

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES, 'UTF-8');

    if ($smtpSettingsPresent) {
        if (!$smtpHost || !$smtpUsername || !$smtpPassword) {
            error_log('Temporary password email failed: SMTP configuration is incomplete.');
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            $mail->setFrom($senderEmail, 'ReddiPH');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Your temporary ReddiPH password';
            $mail->Body = "<p>Hello <strong>{$safeName}</strong>,</p>"
                . '<p>We received a password reset request for your ReddiPH account.</p>'
                . "<p>Your temporary password is:</p><p><strong style=\"font-size:22px\">{$safePassword}</strong></p>"
                . '<p>Use it to sign in. ReddiPH will ask you to create a new password immediately.</p>'
                . '<p>This temporary password expires in 24 hours.</p>';
            $mail->AltBody = "Your temporary ReddiPH password is {$temporaryPassword}. It expires in 24 hours.";
            $mail->send();
            return true;
        } catch (Exception $exception) {
            error_log('Temporary password email failed: ' . $mail->ErrorInfo);
            return false;
        }
    }

    if (!function_exists('mail')) {
        error_log('Temporary password email failed: neither SMTP nor PHP mail is available.');
        return false;
    }

    $subject = 'Your temporary ReddiPH password';
    $body = "Hello {$name},\n\n"
        . "Your temporary ReddiPH password is: {$temporaryPassword}\n\n"
        . "Use it to sign in. You will be asked to create a new password immediately.\n"
        . "This temporary password expires in 24 hours.\n";
    $headers = "From: ReddiPH <{$senderEmail}>\r\n"
        . "Reply-To: {$senderEmail}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = mail($email, $subject, $body, $headers);
    if (!$sent) {
        error_log('Temporary password email failed: PHP mail() did not accept the message.');
    }

    return $sent;
}
