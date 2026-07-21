<?php
declare(strict_types=1);

namespace App\Services;

class Mailer
{
    /**
     * @param string|null $attachmentName Friendly filename shown in the inbox (e.g. DICT-AI-ROADSHOW-QR.png)
     * @param bool $embedInline When true and attachment is an image, embed it via CID in the HTML body
     */
    public static function send(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        bool $embedInline = false
    ): bool {
        $mode = getenv('MAIL_MODE') ?: 'log';
        $attachmentName = $attachmentName ?: ($attachmentPath ? basename($attachmentPath) : null);
        $hasFile = $attachmentPath && is_file($attachmentPath) && filesize($attachmentPath) > 0;

        if ($mode === 'log') {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'outbox';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = $dir . DIRECTORY_SEPARATOR . time() . '_' . preg_replace('/[^a-z0-9]+/i', '_', $to) . '.eml';
            $content = "To: {$to}\nSubject: {$subject}\n\n{$body}\n";
            if ($hasFile) {
                $content .= "\nAttachment: {$attachmentPath} ({$attachmentName})\n";
            }
            return (bool)file_put_contents($name, $content);
        }

        if ($mode === 'smtp') {
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                return self::sendViaPhpMailer($to, $subject, $body, $hasFile ? $attachmentPath : null, $attachmentName, $embedInline);
            }
            return self::sendViaSmtpSocket($to, $subject, $body, $hasFile ? $attachmentPath : null, $attachmentName, $embedInline);
        }

        $headers = 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/html; charset=UTF-8';
        return @mail($to, $subject, $body, $headers);
    }

    private static function sendViaPhpMailer(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath,
        ?string $attachmentName,
        bool $embedInline
    ): bool {
        $host = getenv('SMTP_HOST') ?: '';
        $port = (int)(getenv('SMTP_PORT') ?: '587');
        $user = getenv('SMTP_USER') ?: '';
        $pass = preg_replace('/\s+/', '', getenv('SMTP_PASS') ?: '') ?? '';
        $secure = getenv('SMTP_SECURE') ?: 'tls';
        $from = getenv('SMTP_FROM') ?: $user;
        if ($host === '' || $user === '' || $pass === '') {
            error_log('Mailer: SMTP credentials missing');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = $secure === 'ssl' ? 'ssl' : 'tls';
            $mail->setFrom($from, 'DICT AI ROADSHOW 2026');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;

            if ($embedInline && $attachmentPath) {
                $mail->addEmbeddedImage($attachmentPath, 'registration-qr', $attachmentName ?: 'qrcode.png');
                if (!str_contains($body, 'cid:registration-qr')) {
                    $body .= '<p><img src="cid:registration-qr" alt="Registration QR code" width="240" height="240" style="display:block;border:0;"></p>';
                }
            }
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $body));

            if ($attachmentPath) {
                $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
            }
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('Mailer PHPMailer failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendViaSmtpSocket(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentPath,
        ?string $attachmentName,
        bool $embedInline
    ): bool {
        $host = getenv('SMTP_HOST') ?: '';
        $port = (int)(getenv('SMTP_PORT') ?: '587');
        $user = getenv('SMTP_USER') ?: '';
        $pass = preg_replace('/\s+/', '', getenv('SMTP_PASS') ?: '') ?? '';
        $secure = getenv('SMTP_SECURE') ?: 'tls';
        $from = getenv('SMTP_FROM') ?: $user;
        if ($host === '' || $user === '' || $pass === '') {
            error_log('Mailer: SMTP credentials missing');
            return false;
        }

        $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
        $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20);
        if (!$sock) {
            error_log("Mailer SMTP connect failed: {$errno} {$errstr}");
            return false;
        }
        stream_set_timeout($sock, 20);

        $read = static function () use ($sock): string {
            $resp = '';
            do {
                $line = fgets($sock);
                if ($line === false) {
                    break;
                }
                $resp .= $line;
            } while (strlen($line) > 3 && isset($line[3]) && $line[3] === '-');
            return $resp;
        };
        $code = static fn(string $resp): int => (int)substr($resp, 0, 3);
        $write = static function (string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        try {
            if ($code($read()) !== 220) {
                throw new \RuntimeException('SMTP greeting failed');
            }
            $write('EHLO localhost');
            if ($code($read()) !== 250) {
                throw new \RuntimeException('EHLO failed');
            }
            if ($secure === 'tls') {
                $write('STARTTLS');
                if ($code($read()) !== 220) {
                    throw new \RuntimeException('STARTTLS failed');
                }
                if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS handshake failed');
                }
                $write('EHLO localhost');
                if ($code($read()) !== 250) {
                    throw new \RuntimeException('EHLO after TLS failed');
                }
            }
            $write('AUTH LOGIN');
            if ($code($read()) !== 334) {
                throw new \RuntimeException('AUTH LOGIN rejected');
            }
            $write(base64_encode($user));
            if ($code($read()) !== 334) {
                throw new \RuntimeException('SMTP username rejected');
            }
            $write(base64_encode($pass));
            if ($code($read()) !== 235) {
                throw new \RuntimeException('SMTP password rejected');
            }
            $write('MAIL FROM:<' . $from . '>');
            if ($code($read()) !== 250) {
                throw new \RuntimeException('MAIL FROM rejected');
            }
            $write('RCPT TO:<' . $to . '>');
            if ($code($read()) !== 250) {
                throw new \RuntimeException('RCPT TO rejected');
            }
            $write('DATA');
            if ($code($read()) !== 354) {
                throw new \RuntimeException('DATA rejected');
            }

            $boundary = 'bnd_' . bin2hex(random_bytes(8));
            $relatedBoundary = 'rel_' . bin2hex(random_bytes(8));
            $date = gmdate('D, d M Y H:i:s') . ' +0000';
            $msgId = bin2hex(random_bytes(8)) . '@dict.gov.ph';
            $filename = $attachmentName ?: ($attachmentPath ? basename($attachmentPath) : 'qrcode.png');
            $useRelated = $embedInline && $attachmentPath;

            if ($useRelated && !str_contains($body, 'cid:registration-qr')) {
                $body .= '<p><img src="cid:registration-qr" alt="Registration QR code" width="240" height="240" style="display:block;border:0;"></p>';
            }

            $headers = [
                'From: DICT AI ROADSHOW 2026 <' . $from . '>',
                'To: ' . $to,
                'Subject: ' . self::encodeHeader($subject),
                'Date: ' . $date,
                'Message-ID: <' . $msgId . '>',
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            ];

            $message = '';
            if ($useRelated) {
                $message .= '--' . $boundary . "\r\n";
                $message .= 'Content-Type: multipart/related; boundary="' . $relatedBoundary . '"' . "\r\n\r\n";
                $message .= '--' . $relatedBoundary . "\r\n";
                $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
                $message .= chunk_split(base64_encode($body), 76, "\r\n");
                $data = file_get_contents($attachmentPath);
                $message .= '--' . $relatedBoundary . "\r\n";
                $message .= 'Content-Type: image/png; name="' . $filename . '"' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $message .= 'Content-ID: <registration-qr>' . "\r\n";
                $message .= 'Content-Disposition: inline; filename="' . $filename . '"' . "\r\n\r\n";
                $message .= chunk_split(base64_encode($data), 76, "\r\n");
                $message .= '--' . $relatedBoundary . '--' . "\r\n";
            } else {
                $message .= '--' . $boundary . "\r\n";
                $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
                $message .= chunk_split(base64_encode($body), 76, "\r\n");
            }

            if ($attachmentPath) {
                $data = file_get_contents($attachmentPath);
                $message .= '--' . $boundary . "\r\n";
                $message .= 'Content-Type: image/png; name="' . $filename . '"' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
                $message .= chunk_split(base64_encode($data), 76, "\r\n");
            }

            $message .= '--' . $boundary . '--' . "\r\n";
            $dataOut = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.";
            $write($dataOut);
            if ($code($read()) !== 250) {
                throw new \RuntimeException('Message not accepted');
            }
            $write('QUIT');
            $read();
            fclose($sock);
            return true;
        } catch (\Throwable $e) {
            error_log('Mailer SMTP failed: ' . $e->getMessage());
            fclose($sock);
            return false;
        }
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
