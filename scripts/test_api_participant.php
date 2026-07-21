<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Controllers/ParticipantController.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$uuid = $pdo->query('SELECT uuid FROM participants ORDER BY id DESC LIMIT 1')->fetch()['uuid'] ?? '';
$_GET['uuid'] = $uuid;
(new \App\Controllers\ParticipantController())->getByUuidJson();