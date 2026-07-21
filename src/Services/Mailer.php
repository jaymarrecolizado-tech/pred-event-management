<?php
declare(strict_types=1);

namespace App\Services;

class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $attachmentPath = null): bool
    {
        $mode = getenv('MAIL_MODE') ?: 'log';
        if ($mode === 'log') {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'outbox';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = $dir . DIRECTORY_SEPARATOR . time() . '_' . preg_replace('/[^a-z0-9]+/i','_', $to) . '.eml';
            $content = "To: {$to}\nSubject: {$subject}\n\n{$body}\n";
            if ($attachmentPath && is_file($attachmentPath)) {
                $content .= "\nAttachment: {$attachmentPath}\n";
            }
            return (bool)file_put_contents($name, $content);
        }
        if ($mode === 'smtp') {
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                $host = getenv('SMTP_HOST') ?: '';
                $port = (int)(getenv('SMTP_PORT') ?: '587');
                $user = getenv('SMTP_USER') ?: '';
                $pass = getenv('SMTP_PASS') ?: '';
                $secure = getenv('SMTP_SECURE') ?: 'tls';
                $from = getenv('SMTP_FROM') ?: $user;
                if ($host === '' || $user === '' || $pass === '') return false;
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = $host;
                    $mail->Port = $port;
                    $mail->SMTPAuth = true;
                    $mail->Username = $user;
                    $mail->Password = $pass;
                    $mail->SMTPSecure = $secure === 'ssl' ? 'ssl' : 'tls';
                    $mail->setFrom($from);
                    $mail->addAddress($to);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    if ($attachmentPath && is_file($attachmentPath)) $mail->addAttachment($attachmentPath);
                    return $mail->send();
                } catch (\Exception $e) {
                    return false;
                }
            }
            $host = getenv('SMTP_HOST') ?: '';
            $port = (int)(getenv('SMTP_PORT') ?: '587');
            $user = getenv('SMTP_USER') ?: '';
            $pass = getenv('SMTP_PASS') ?: '';
            $secure = getenv('SMTP_SECURE') ?: 'tls';
            $from = getenv('SMTP_FROM') ?: $user;
            if ($host === '' || $user === '' || $pass === '') return false;
            $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
            $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
            if (!$sock) return false;
            stream_set_timeout($sock, 15);
            $read = function() use ($sock) { $line = ''; $resp = ''; do { $line = fgets($sock); if ($line === false) break; $resp .= $line; } while (strlen($line) > 3 && isset($line[3]) && $line[3] === '-'); return $resp; };
            $code = function($resp){ return (int)substr($resp,0,3); };
            $write = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
            if ($code($read()) !== 220) { fclose($sock); return false; }
            $write('EHLO localhost');
            if ($code($read()) !== 250) { fclose($sock); return false; }
            if ($secure === 'tls') {
                $write('STARTTLS');
                if ($code($read()) !== 220) { fclose($sock); return false; }
                if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($sock); return false; }
                $write('EHLO localhost');
                if ($code($read()) !== 250) { fclose($sock); return false; }
            }
            $write('AUTH LOGIN');
            if ($code($read()) !== 334) { fclose($sock); return false; }
            $write(base64_encode($user));
            if ($code($read()) !== 334) { fclose($sock); return false; }
            $write(base64_encode($pass));
            if ($code($read()) !== 235) { fclose($sock); return false; }
            $write('MAIL FROM:<' . $from . '>');
            if ($code($read()) !== 250) { fclose($sock); return false; }
            $write('RCPT TO:<' . $to . '>');
            if ($code($read()) !== 250) { fclose($sock); return false; }
            $write('DATA');
            if ($code($read()) !== 354) { fclose($sock); return false; }
            $boundary = 'bnd_' . bin2hex(random_bytes(8));
            $date = gmdate('D, d M Y H:i:s') . ' +0000';
            $msgId = bin2hex(random_bytes(8)) . '@localhost';
            $headers = [];
            $headers[] = 'From: ' . $from;
            $headers[] = 'To: ' . $to;
            $headers[] = 'Subject: ' . $subject;
            $headers[] = 'Date: ' . $date;
            $headers[] = 'Message-ID: <' . $msgId . '>';
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $message = '';
            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $message .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";
            $message .= $body . "\r\n";
            if ($attachmentPath && is_file($attachmentPath)) {
                $data = file_get_contents($attachmentPath);
                $filename = basename($attachmentPath);
                $message .= '--' . $boundary . "\r\n";
                $message .= 'Content-Type: image/png; name="' . $filename . '"' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
                $message .= chunk_split(base64_encode($data), 76, "\r\n") . "\r\n";
            }
            $message .= '--' . $boundary . '--' . "\r\n";
            $dataOut = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.";
            $write($dataOut);
            if ($code($read()) !== 250) { fclose($sock); return false; }
            $write('QUIT');
            $read();
            fclose($sock);
            return true;
        }
        $headers = 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/html; charset=UTF-8';
        return mail($to, $subject, $body, $headers);
    }
}