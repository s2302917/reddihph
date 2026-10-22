<?php

declare(strict_types=1);

/**
 * Sends the temporary password email with PHP's built-in mail().
 * Set the MAIL_FROM environment variable to change the sender address.
 */
final class MailService
{
    public static function sendTemporaryPassword(string $email, string $name, string $temporaryPassword): bool
    {
        $sender = getenv('MAIL_FROM') ?: 'noreply@reddiph.com';
        $body = "Hello {$name},\n\n"
            . "Your temporary ReddiPH password is: {$temporaryPassword}\n\n"
            . "Use it to sign in. You will be asked to create a new password immediately.\n"
            . "This temporary password expires in 24 hours.\n";
        $headers = "From: ReddiPH <{$sender}>\r\n"
            . "Reply-To: {$sender}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";

        $subject = 'Your temporary ReddiPH password';
        $sent = mail($email, $subject, $body, $headers);
        if (!$sent) {
            error_log('Temporary password email was not accepted by mail().');
        }

        return $sent;
    }
}
