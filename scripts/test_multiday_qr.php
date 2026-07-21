<?php
declare(strict_types=1);

/**
 * Verify same participant QR can check in on two different dates.
 * Run: C:\xampp\php\php.exe scripts/test_multiday_qr.php
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';

$pdo = \App\Services\Database::pdo();
$uuid = \App\Services\Uuid::v4();
$email = 'multiday_' . bin2hex(random_bytes(3)) . '@example.com';

$pdo->prepare(
    'INSERT INTO participants (uuid,email,first_name,last_name,sector,agency,contact_no,registration_status)
     VALUES (?,?,?,?,?,?,?,?)'
)->execute([$uuid, $email, 'Multi', 'Day', 'GOCCs', 'DICT', '+639171234567', 'Registered']);
$pid = (int)$pdo->lastInsertId();

$event = $pdo->query('SELECT id FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$eventId = $event ? (int)$event['id'] : null;

$ins = $pdo->prepare(
    "INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status)
     VALUES (?,?,?,?,?,'present')"
);

$day1 = '2026-07-21';
$day2 = '2026-07-22';
$ins->execute([$pid, $day1, '09:00:00', 'storage/signatures/test-day1.png', $eventId]);
$ins->execute([$pid, $day2, '09:05:00', 'storage/signatures/test-day2.png', $eventId]);

$cnt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE participant_id = ?');
$cnt->execute([$pid]);
$days = (int)$cnt->fetchColumn();

$dup = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE participant_id = ? AND attendance_date = ?');
$dup->execute([$pid, $day1]);
$sameDay = (int)$dup->fetchColumn();

echo "participant_id={$pid}\n";
echo "uuid={$uuid}\n";
echo "attendance_days={$days}\n";
echo "same_day_rows={$sameDay}\n";
echo ($days === 2 && $sameDay === 1) ? "PASS: same QR covers multiple days\n" : "FAIL\n";

$pdo->prepare('DELETE FROM attendance WHERE participant_id = ?')->execute([$pid]);
$pdo->prepare('DELETE FROM participants WHERE id = ?')->execute([$pid]);
exit(($days === 2 && $sameDay === 1) ? 0 : 1);
