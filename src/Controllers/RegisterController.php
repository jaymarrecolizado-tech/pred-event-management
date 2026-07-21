<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\Uuid;
use App\Services\QrService;
use App\Services\Mailer;
use App\Services\ParticipantValidator;
use App\Services\RateLimiter;

class RegisterController
{
    private const SEXES = ['Female','Male','Other'];
    private const SECTORS = [
        'National Government Agency',
        'Local Government Unit',
        'Provincial Government Unit',
        'GOCCs',
        'State Universities and Colleges',
        'Water District',
    ];

    public function show(): void
    {
        $pdo = \App\Services\Database::pdo();
        $agencies = $pdo->query("SELECT DISTINCT agency FROM participants WHERE agency IS NOT NULL AND agency <> '' ORDER BY agency ASC LIMIT 500")->fetchAll();
        $designations = $pdo->query("SELECT DISTINCT designation FROM participants WHERE designation IS NOT NULL AND designation <> '' ORDER BY designation ASC LIMIT 500")->fetchAll();
        $sexes = self::SEXES;
        $sectors = self::SECTORS;
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register.php';
    }

    public function success(): void
    {
        $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
        if ($uuid === '') { http_response_code(400); echo 'Missing UUID'; return; }
        $pdo = \App\Services\Database::pdo();
        $stmt = $pdo->prepare('SELECT uuid, first_name, last_name FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo 'Not Found'; return; }
        $participant = [
            'uuid' => $row['uuid'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
        ];
        if (!isset($_SESSION['qr_allowed'])) $_SESSION['qr_allowed'] = [];
        $_SESSION['qr_allowed'][$uuid] = true;
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register_success.php';
    }

    public function submit(): void
    {
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::allow('register:' . $ip, 10, 300)) {
            http_response_code(429);
            $error = 'Too many registration attempts. Please try again in a few minutes.';
            require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register_error.php';
            return;
        }

        $agencySelected = $_POST['agency_select'] ?? ($_POST['agency'] ?? '');
        $designationSelected = $_POST['designation_select'] ?? ($_POST['designation'] ?? '');
        $agency = $this->resolveCustomSelect($agencySelected, $_POST['agency_other'] ?? '');
        $designation = $this->resolveCustomSelect($designationSelected, $_POST['designation_other'] ?? '');
        $sex = $this->allowList($_POST['sex'] ?? '', self::SEXES);
        $sector = $this->allowList($_POST['sector'] ?? '', self::SECTORS, allowCustom: true);

        $validation = ParticipantValidator::validateForRegistration([
            'first_name' => $_POST['first_name'] ?? '',
            'middle_name' => $_POST['middle_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'agency' => $agency,
            'sector' => $sector,
            'nickname' => $_POST['nickname'] ?? '',
            'sex' => $sex,
            'designation' => $designation,
            'office_email' => '',
            'contact_no' => $_POST['contact_no'] ?? '',
        ]);

        $clean = $validation['data'];
        $errors = $validation['errors'];
        if ($errors) {
            http_response_code(422);
            $error = 'Please fix the highlighted fields.';
            $errorsList = $errors;
            require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register_error.php';
            return;
        }

        $pdo = Database::pdo();
        try {
            if (!empty($clean['email'])) {
                $chk = $pdo->prepare('SELECT id FROM participants WHERE email = ?');
                $chk->execute([$clean['email']]);
                if ($chk->fetch()) {
                    http_response_code(409);
                    $error = 'Email already registered';
                    $errorsList = [];
                    require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register_error.php';
                    return;
                }
            }
            $attempts = 0;
            $max = 5;
            $uuid = '';
            while ($attempts < $max) {
                $uuid = Uuid::v4();
                try {
                    $stmt = $pdo->prepare('INSERT INTO participants (uuid,email,first_name,middle_name,last_name,nickname,sex,sector,agency,designation,office_email,contact_no,registration_status,qr_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([
                        $uuid,
                        $clean['email'],
                        $clean['first_name'],
                        $clean['middle_name'],
                        $clean['last_name'],
                        $clean['nickname'],
                        $clean['sex'],
                        $clean['sector'],
                        $clean['agency'],
                        $clean['designation'],
                        $clean['office_email'],
                        $clean['contact_no'],
                        'Registered',
                        null,
                    ]);
                    break;
                } catch (\PDOException $e) {
                    $attempts++;
                    if ($attempts >= $max) throw $e;
                }
            }
            $payload = 'PART|' . $uuid;
            $qrPath = QrService::generate($payload, $uuid);
            $up = $pdo->prepare('UPDATE participants SET qr_path=? WHERE uuid=?');
            $up->execute([$qrPath, $uuid]);
        } catch (\PDOException $e) {
            http_response_code(500);
            $error = 'Registration failed';
            require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'register_error.php';
            return;
        }

        $to = trim((string)($clean['email'] ?? ''));
        $mailSent = false;
        if ($to !== '' && !empty($qrPath) && is_file($qrPath)) {
            $first = htmlspecialchars((string)($clean['first_name'] ?? ''), ENT_QUOTES);
            $subject = 'DICT AI ROADSHOW 2026 — Your registration QR code';
            $body = '<div style="font-family:Arial,sans-serif;color:#1a1a1a;line-height:1.5">'
                . '<p>Hi ' . $first . ',</p>'
                . '<p>Thank you for registering for <strong>DICT AI ROADSHOW 2026</strong>.</p>'
                . '<p>Your check-in QR code is attached. This is a <strong>multi-day event</strong> — keep the same QR and present it each day at the welcome desk.</p>'
                . '<p style="margin:1.25rem 0"><img src="cid:registration-qr" alt="Registration QR code" width="240" height="240" style="display:block;border:1px solid #ddd;border-radius:8px;"></p>'
                . '<p>If the image above does not appear, open the attached PNG file <strong>DICT-AI-ROADSHOW-QR.png</strong>.</p>'
                . '<p style="color:#555;font-size:13px">This message was sent to ' . htmlspecialchars($to, ENT_QUOTES) . ' because it was used during registration.</p>'
                . '</div>';
            $mailSent = Mailer::send(
                $to,
                $subject,
                $body,
                $qrPath,
                'DICT-AI-ROADSHOW-QR.png',
                true
            );
            \App\Services\Logger::log(null, $mailSent ? 'email_sent' : 'email_failed', [
                'to' => $to,
                'uuid' => $uuid,
                'has_qr' => is_file($qrPath),
            ]);
            if (!$mailSent) {
                error_log('Registration QR email failed for ' . $to . ' uuid=' . $uuid);
            }
        } else {
            error_log('Registration QR email skipped: missing email or QR file');
        }

        if (!isset($_SESSION['qr_allowed'])) {
            $_SESSION['qr_allowed'] = [];
        }
        $_SESSION['qr_allowed'][$uuid] = true;
        $_SESSION['registration_email_sent'] = $mailSent;
        if (function_exists('csrf_rotate')) {
            csrf_rotate();
        }
        header('Location: ?r=register_success&uuid=' . urlencode($uuid));
        exit;
    }

    private function resolveCustomSelect(string $selected, string $other): string
    {
        $selected = trim($selected);
        $other = trim($other);
        if ($selected === 'other') {
            return $other;
        }
        return $selected;
    }

    private function allowList(string $value, array $allowed, bool $allowCustom = false): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (in_array($value, $allowed, true)) {
            return $value;
        }
        return $allowCustom ? $value : '';
    }
}