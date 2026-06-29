<?php
declare(strict_types=1);

final class MailService
{
    public function sendAccountDecision(string $to, string $name, string $decision, string $message): array
    {
        $config = Database::config()['mail'] ?? [];
        if (empty($config['enabled'])) {
            return ['sent' => false, 'error' => 'Email is disabled.'];
        }

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['sent' => false, 'error' => 'PHPMailer is not installed.'];
        }

        try {
            $mailClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
            $mail = new $mailClass(true);
            $mail->isSMTP();
            $mail->Host = (string)($config['host'] ?? '');
            $mail->Port = (int)($config['port'] ?? 587);
            $mail->SMTPAuth = !empty($config['username']);
            $mail->Username = (string)($config['username'] ?? '');
            $mail->Password = (string)($config['password'] ?? '');
            $mail->SMTPSecure = (string)($config['encryption'] ?? 'tls');
            $mail->CharSet = 'UTF-8';

            $from = (string)($config['from'] ?? 'no-reply@quadbyte-lms.local');
            $fromName = (string)($config['from_name'] ?? 'SchoDex Library');
            $mail->setFrom($from, $fromName);
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = 'SchoDex account ' . $decision;
            $mail->Body = $this->htmlBody($name, $decision, $message);
            $mail->AltBody = "Hello {$name},\n\n{$message}\n\nSchoDex Library";
            $mail->send();

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    private function htmlBody(string $name, string $decision, string $message): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeDecision = htmlspecialchars($decision, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return '<div style="font-family:Arial,sans-serif;line-height:1.55;color:#1f2933">'
            . '<h2 style="margin:0 0 12px">SchoDex Account ' . $safeDecision . '</h2>'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>' . $safeMessage . '</p>'
            . '<p style="margin-top:24px;color:#64748b">SchoDex Library</p>'
            . '</div>';
    }
}
