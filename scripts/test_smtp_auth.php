<?php
declare(strict_types=1);

/**
 * Diagnose SMTP auth without printing secrets.
 * Run: C:\xampp\php\php.exe scripts/test_smtp_auth.php
 */

require __DIR__ . '/../config/bootstrap.php';

$host = getenv('SMTP_HOST') ?: '';
$port = (int)(getenv('SMTP_PORT') ?: '0');
$user = getenv('SMTP_USER') ?: '';
$passRaw = getenv('SMTP_PASS') ?: '';
$secure = getenv('SMTP_SECURE') ?: 'ssl';
$from = getenv('SMTP_FROM') ?: $user;

echo "MAIL_MODE=" . (getenv('MAIL_MODE') ?: '') . PHP_EOL;
echo "SMTP_HOST={$host}\n";
echo "SMTP_PORT={$port}\n";
echo "SMTP_SECURE={$secure}\n";
echo "SMTP_USER={$user}\n";
echo "SMTP_FROM={$from}\n";
echo 'SMTP_PASS_len=' . strlen($passRaw) . ' has_spaces=' . (str_contains($passRaw, ' ') ? 'yes' : 'no') . PHP_EOL;

function smtpAuth(string $host, int $port, string $secure, string $user, string $pass): string
{
    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20);
    if (!$sock) {
        return "connect_fail: {$errno} {$errstr}";
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
    $code = static fn(string $r): int => (int)substr($r, 0, 3);
    $write = static function (string $cmd) use ($sock): void {
        fwrite($sock, $cmd . "\r\n");
    };

    try {
        if ($code($read()) !== 220) {
            return 'bad_greeting';
        }
        $write('EHLO localhost');
        if ($code($read()) !== 250) {
            return 'ehlo_fail';
        }
        if ($secure === 'tls') {
            $write('STARTTLS');
            if ($code($read()) !== 220) {
                return 'starttls_fail';
            }
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return 'tls_fail';
            }
            $write('EHLO localhost');
            if ($code($read()) !== 250) {
                return 'ehlo2_fail';
            }
        }
        $write('AUTH LOGIN');
        if ($code($read()) !== 334) {
            return 'auth_login_rejected';
        }
        $write(base64_encode($user));
        if ($code($read()) !== 334) {
            return 'username_rejected';
        }
        $write(base64_encode($pass));
        $authResp = $read();
        $authCode = $code($authResp);
        $write('QUIT');
        $read();
        fclose($sock);
        if ($authCode === 235) {
            return 'auth_ok';
        }
        return 'password_rejected code=' . $authCode . ' msg=' . trim(preg_replace('/\s+/', ' ', $authResp));
    } catch (Throwable $e) {
        fclose($sock);
        return 'exception:' . $e->getMessage();
    }
}

echo 'auth_with_raw_pass=' . smtpAuth($host, $port, $secure, $user, $passRaw) . PHP_EOL;
$noSpace = preg_replace('/\s+/', '', $passRaw) ?? $passRaw;
if ($noSpace !== $passRaw) {
    echo 'auth_with_nospaces=' . smtpAuth($host, $port, $secure, $user, $noSpace) . PHP_EOL;
}
