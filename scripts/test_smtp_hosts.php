<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$user = getenv('SMTP_USER') ?: '';
$pass = preg_replace('/\s+/', '', (string)(getenv('SMTP_PASS') ?: '')) ?? '';

function smtpAuth(string $host, int $port, string $secure, string $user, string $pass): string
{
    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20);
    if (!$sock) {
        return "connect_fail {$errno} {$errstr}";
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
        @$read();
        fclose($sock);
        return $authCode === 235 ? 'auth_ok' : ('fail ' . trim(preg_replace('/\s+/', ' ', $authResp)));
    } catch (Throwable $e) {
        @fclose($sock);
        return 'exception ' . $e->getMessage();
    }
}

$targets = [
    ['smtp.hostinger.com', 465, 'ssl'],
    ['smtp.gmail.com', 465, 'ssl'],
    ['smtp.gmail.com', 587, 'tls'],
];

echo "user={$user}\n";
echo 'pass_nospaces_len=' . strlen($pass) . PHP_EOL;
foreach ($targets as [$h, $p, $s]) {
    echo "{$h}:{$p}/{$s} => " . smtpAuth($h, $p, $s, $user, $pass) . PHP_EOL;
}
