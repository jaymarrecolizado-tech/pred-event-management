<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class AdminImportController
{
    // Database field mappings with multiple possible CSV column name variations
    // Order matters: more specific matches should come first
    private array $fieldMappings = [
        'timestamp' => ['timestamp', 'date', 'time', 'created at', 'registration date'],
        'email' => ['email address', 'email', 'e-mail', 'e mail'],
        'first_name' => ['first name', 'firstname', 'fname', 'given name'],
        'middle_name' => ['middle name', 'middlename', 'mname', 'middle initial', 'middle'],
        'last_name' => ['last name', 'lastname', 'lname', 'surname', 'family name'],
        'full_name' => ['full name', 'fullname', 'name', 'complete name', 'participant name'],
        'nickname' => ['nickname', 'nick name', 'preferred name', 'alias'],
        'sex' => ['sex', 'gender', 'sex/gender'],
        'sector' => ['sector', 'industry', 'field'],
        'agency' => ['name of agency / organization', 'name of agency/organization', 'agency', 'organization', 'company', 'institution', 'employer', 'name of agency'],
        'designation' => ['position/designation', 'position', 'designation', 'title', 'job title', 'role'],
        'office_email' => ['office email', 'office e-mail', 'work email', 'business email', 'official email'],
        'contact_no' => ['mobile number', 'contact no', 'contact number', 'phone', 'phone number', 'mobile', 'cell', 'cellphone', 'telephone'],
    ];

    private array $requiredFields = ['first_name', 'last_name'];

    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return false; }
        return true;
    }

    public function form(): void
    {
        if (!$this->requireAdmin()) return;
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_import.php';
    }

    public function preview(): void
    {
        if (!$this->requireAdmin()) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ok = \App\Services\RateLimiter::allow('import_preview:'.$ip, 10, 60);
        if (!$ok) { http_response_code(429); echo 'Too Many Attempts'; return; }
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) { http_response_code(400); echo 'No file'; return; }
        $name = $_FILES['csv']['name'];
        $size = (int)$_FILES['csv']['size'];
        if ($size <= 0 || $size > 5_000_000) { http_response_code(400); echo 'Invalid size'; return; }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') { http_response_code(400); echo 'Invalid type'; return; }

        $tmp = $_FILES['csv']['tmp_name'];
        $fh = fopen($tmp, 'r');
        $header = fgetcsv($fh);
        $missing = [];
        if (!$this->validateHeader($header, $missing)) {
            fclose($fh);
            $message = $missing ? ('Missing required columns: ' . implode(', ', $missing)) : 'Header mismatch';
            $this->renderPreview([], [$message]);
            return;
        }

        $rows = [];
        $errors = [];
        $limit = 200;
        $pdo = Database::pdo();
        $map = $this->headerMap($header);
        $count = 0;
        while (($data = fgetcsv($fh)) !== false) {
            $count++;
            $row = $this->rowFromMap($map, $data);
            $status = $this->detectStatus($pdo, $row);
            $rows[] = ['rownum'=>$count, 'row'=>$row, 'status'=>$status];
            if (count($rows) >= $limit) break;
        }
        fclose($fh);

        $storeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports';
        if (!is_dir($storeDir)) mkdir($storeDir, 0775, true);
        $stored = $storeDir . DIRECTORY_SEPARATOR . time() . '_' . $_SESSION['admin_id'] . '.csv';
        move_uploaded_file($tmp, $stored);
        $_SESSION['import_file'] = $stored;
        $_SESSION['import_map'] = $map;
        $this->renderPreview($rows, $errors);
    }

    public function execute(): void
    {
        if (!$this->requireAdmin()) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ok = \App\Services\RateLimiter::allow('import_execute:'.$ip, 5, 60);
        if (!$ok) { http_response_code(429); echo 'Too Many Attempts'; return; }
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $strategy = $_POST['strategy'] ?? 'skip';
        $file = $_SESSION['import_file'] ?? '';
        $map = $_SESSION['import_map'] ?? null;
        if (!$file || !is_file($file) || !is_array($map)) { 
            http_response_code(400); 
            echo 'Missing preview state. Please upload the CSV file again.'; 
            return; 
        }
        
        // Validate map structure
        $requiredMapKeys = ['first_name', 'last_name'];
        $hasRequiredKeys = false;
        foreach ($requiredMapKeys as $key) {
            if (isset($map[$key]) && $map[$key] !== false) {
                $hasRequiredKeys = true;
                break;
            }
        }
        if (!$hasRequiredKeys && (!isset($map['full_name']) || $map['full_name'] === false)) {
            http_response_code(400);
            echo 'Invalid field mapping. Please upload the CSV file again.';
            return;
        }

        try {
            // Increase execution time and memory limits for large imports
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '256M');
            
            $pdo = Database::pdo();
            
            // Use SplFileObject for better CSV handling
            // Don't use SKIP_EMPTY as we want to process all rows including those with empty cells
            $fileObj = new \SplFileObject($file, 'r');
            $fileObj->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE);
            $fileObj->setCsvControl(',', '"', '"');
            
            // Read first line (header)
            $fileObj->rewind();
            $firstLine = $fileObj->current();
            
            // Check for BOM and remove if present
            if (is_array($firstLine) && !empty($firstLine[0]) && substr($firstLine[0], 0, 3) === "\xEF\xBB\xBF") {
                $firstLine[0] = substr($firstLine[0], 3);
            }
            
            // Read header
            $header = $firstLine;
            if (empty($header) || !is_array($header)) {
                throw new \RuntimeException('Failed to read CSV header or header is empty');
            }
            
            error_log("Import: Header columns: " . count($header));
            
            // Count total lines in file for logging
            $fileObj->seek(PHP_INT_MAX);
            $totalLines = $fileObj->key() + 1; // +1 because key is 0-indexed
            $fileObj->rewind();
            $fileObj->next(); // Move past header
            
            error_log("Import: Total lines in file: $totalLines (including header), Data rows: " . ($totalLines - 1));
            
            $changes = [];
            $inserted = 0; $updated = 0; $skipped = 0; $errored = 0;
            $rowCount = 0;
            $processedCount = 0;
            
            error_log("Import: Starting to process data rows");
            
            // Process all data rows using SplFileObject iterator
            $lineNumber = 0;
            foreach ($fileObj as $lineNum => $data) {
                $lineNumber++;
                
                // Skip header if we somehow get it again
                if ($lineNum === 0) {
                    error_log("Import: Skipping header at line $lineNumber");
                    continue;
                }
                
                error_log("Import: Reading line $lineNumber (file line " . ($lineNum + 1) . ")");
                
                // Check if we got valid data
                if (!is_array($data) || empty($data)) {
                    // Check if it's just an empty line
                    if ($data === null || (is_array($data) && count(array_filter($data)) === 0)) {
                        continue; // Skip empty lines
                    }
                    error_log('Import: Skipping invalid CSV line at line ' . ($lineNum + 1));
                    $errored++;
                    continue;
                }
                
                // Check if row has any non-empty data
                $hasData = false;
                foreach ($data as $cell) {
                    $cellValue = trim((string)$cell);
                    if ($cellValue !== '' && $cellValue !== 'null') {
                        $hasData = true;
                        break;
                    }
                }
                
                if (!$hasData) {
                    continue; // Skip completely empty rows
                }
                
                $rowCount++;
                
                // Log raw data for debugging
                error_log("Import: Processing row $rowCount, CSV data: " . json_encode(array_slice($data, 0, 5))); // First 5 columns for debugging
                
                try {
                    $row = $this->rowFromMap($map, $data);
                    
                    // Log mapped row data
                    error_log("Import: Row $rowCount mapped - first_name: '{$row['first_name']}', last_name: '{$row['last_name']}', email: '{$row['email']}', agency: '{$row['agency']}'");
                    
                    // Skip if no name data at all
                    if (empty($row['first_name']) && empty($row['last_name'])) {
                        error_log("Import: Row $rowCount REJECTED - no name data - first_name: '{$row['first_name']}', last_name: '{$row['last_name']}'");
                        $errored++;
                        continue;
                    }
                    
                    $status = $this->detectStatus($pdo, $row);
                    error_log("Import: Row $rowCount status: $status");
                    
                    if ($status === 'Error') { 
                        error_log("Import: Row $rowCount REJECTED - status Error (likely missing first_name or last_name)");
                        $errored++; 
                        continue; 
                    }
                    
                    $match = $this->findMatch($pdo, $row);
                    if (!$match) {
                        // Process insert immediately
                        try {
                            $result = $this->processSingleRow($pdo, 'insert', $row, null);
                            if ($result['success']) {
                                $inserted++;
                                $processedCount++;
                                if (!empty($result['changes'])) {
                                    $changes = array_merge($changes, $result['changes']);
                                }
                                error_log("Import: Row $rowCount INSERTED - {$row['first_name']} {$row['last_name']}");
                            } else {
                                $errored++;
                                error_log("Import: Row $rowCount INSERT FAILED - {$row['first_name']} {$row['last_name']}: " . $result['error']);
                            }
                        } catch (\Exception $e) {
                            $errored++;
                            error_log("Import: Row $rowCount INSERT EXCEPTION - {$row['first_name']} {$row['last_name']}: " . $e->getMessage());
                        }
                    } else {
                        if ($strategy === 'override_all' || ($strategy === 'override_duplicates' && ($status === 'Duplicate (email)' || $status === 'Duplicate (name+agency)'))) {
                            // Process update immediately
                            try {
                                $result = $this->processSingleRow($pdo, 'update', $row, $match);
                                if ($result['success']) {
                                    $updated++;
                                    $processedCount++;
                                    if (!empty($result['changes'])) {
                                        $changes = array_merge($changes, $result['changes']);
                                    }
                                    error_log("Import: Row $rowCount UPDATED - {$row['first_name']} {$row['last_name']}");
                                } else {
                                    $errored++;
                                    error_log("Import: Row $rowCount UPDATE FAILED - {$row['first_name']} {$row['last_name']}: " . $result['error']);
                                }
                            } catch (\Exception $e) {
                                $errored++;
                                error_log("Import: Row $rowCount UPDATE EXCEPTION - {$row['first_name']} {$row['last_name']}: " . $e->getMessage());
                            }
                        } else {
                            $skipped++;
                            error_log("Import: Row $rowCount SKIPPED (duplicate, strategy=$strategy) - {$row['first_name']} {$row['last_name']} (Status: $status)");
                            error_log("Import: To import this duplicate, use strategy 'override_all' or 'override_duplicates'");
                        }
                    }
                } catch (\Exception $e) {
                    error_log('Import row error at row ' . $rowCount . ' (line ' . $lineNumber . '): ' . $e->getMessage() . ' | Trace: ' . substr($e->getTraceAsString(), 0, 500));
                    $errored++;
                    continue;
                }
            }
            
            error_log("Import: Finished reading all rows. Total lines processed: $lineNumber, Valid data rows: $rowCount, Successfully processed: $processedCount");
            
            error_log("Import completed: Total data rows processed: $rowCount, Inserted: $inserted, Updated: $updated, Skipped: $skipped, Errored: $errored");
            error_log("Import summary: Processed=$processedCount (Inserted+Updated), Skipped=$skipped (duplicates), Errored=$errored (validation failures)");
            
            // Calculate what happened
            $totalAttempted = $rowCount;
            $totalSuccessful = $inserted + $updated;
            $totalNotImported = $skipped + $errored;
            error_log("Import breakdown: $totalAttempted rows attempted, $totalSuccessful successfully imported, $totalNotImported not imported ($skipped skipped, $errored errored)");
            
            // Debug: Log file info
            $fileSize = filesize($file);
            error_log("Import file info: Size=$fileSize bytes, Estimated rows=$totalRows");

            $summary = json_encode(['inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped,'errored'=>$errored,'changes'=>$changes,'stored_csv'=>$file], JSON_UNESCAPED_UNICODE);
            $log = $pdo->prepare('INSERT INTO import_logs (admin_id, file_name, action, duplicate_strategy, summary) VALUES (?,?,?,?,?)');
            $log->execute([(int)$_SESSION['admin_id'], basename($file), 'execute', $strategy, $summary]);

            unset($_SESSION['import_file'], $_SESSION['import_map']);
            header('Location: ?r=admin_import_history');
        } catch (\Exception $e) {
            error_log('Import execute error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (isset($fh) && is_resource($fh)) {
                fclose($fh);
            }
            http_response_code(500);
            echo 'Import failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public function history(): void
    {
        if (!$this->requireAdmin()) return;
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id, admin_id, file_name, action, duplicate_strategy, created_at FROM import_logs ORDER BY id DESC LIMIT 100')->fetchAll();
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_import_history.php';
    }

    private function validateHeader(?array $header, array &$missing = []): bool
    {
        if (!$header) {
            $missing = ['First Name', 'Last Name'];
            return false;
        }
        
        $map = $this->buildFieldMap($header);
        $missing = [];
        
        // Check if we have first_name and last_name (either directly or via full_name)
        $hasFirstName = isset($map['first_name']) && $map['first_name'] !== false;
        $hasLastName = isset($map['last_name']) && $map['last_name'] !== false;
        $hasFullName = isset($map['full_name']) && $map['full_name'] !== false;
        
        if (!$hasFirstName && !$hasFullName) {
            $missing[] = 'First Name';
        }
        if (!$hasLastName && !$hasFullName) {
            $missing[] = 'Last Name';
        }
        
        return count($missing) === 0;
    }

    /**
     * Builds a flexible field mapping from CSV headers to database fields
     */
    private function buildFieldMap(array $header): array
    {
        $map = [];
        $headerLower = [];
        
        // Normalize all headers to lowercase for matching
        foreach ($header as $index => $label) {
            $normalized = strtolower(trim((string)$label));
            $headerLower[$index] = $normalized;
        }
        
        // For each database field, find the best matching CSV column
        foreach ($this->fieldMappings as $dbField => $possibleNames) {
            $found = false;
            $bestMatch = null;
            $bestScore = 0;
            
            // First pass: look for exact matches (highest priority)
            foreach ($possibleNames as $possibleName) {
                if ($found) break;
                foreach ($headerLower as $index => $headerName) {
                    if ($headerName === $possibleName) {
                        $map[$dbField] = $index;
                        $found = true;
                        break;
                    }
                }
            }
            
            // Second pass: look for partial matches if no exact match found
            if (!$found) {
                foreach ($possibleNames as $possibleName) {
                    foreach ($headerLower as $index => $headerName) {
                        // Check if header contains the possible name or vice versa
                        if (strpos($headerName, $possibleName) !== false || strpos($possibleName, $headerName) !== false) {
                            // Score based on match length (longer matches are better)
                            $score = min(strlen($headerName), strlen($possibleName));
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $index;
                            }
                        }
                    }
                }
                
                if ($bestMatch !== null) {
                    $map[$dbField] = $bestMatch;
                    $found = true;
                }
            }
            
            if (!$found) {
                $map[$dbField] = false;
            }
        }
        
        return $map;
    }

    /**
     * Legacy method for backward compatibility
     */
    private function headerMap(array $header): array
    {
        return $this->buildFieldMap($header);
    }

    /**
     * Parses a full name into first, middle, and last name components
     */
    private function parseFullName(string $fullName): array
    {
        $fullName = trim($fullName);
        if (empty($fullName)) {
            return ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
        }
        
        // Remove extra whitespace and split by spaces
        $parts = preg_split('/\s+/', $fullName);
        $parts = array_filter($parts, function($p) { return trim($p) !== ''; });
        $parts = array_values($parts);
        
        $count = count($parts);
        
        if ($count === 0) {
            return ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
        } elseif ($count === 1) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => ''];
        } elseif ($count === 2) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => $parts[1]];
        } else {
            // 3 or more parts: first, middle(s), last
            $first = $parts[0];
            $last = $parts[count($parts) - 1];
            $middle = implode(' ', array_slice($parts, 1, -1));
            return ['first_name' => $first, 'middle_name' => $middle, 'last_name' => $last];
        }
    }

    /**
     * Extracts data from CSV row using flexible field mapping
     */
    private function rowFromMap(array $map, array $data): array
    {
        // Handle backward compatibility with old map format
        $isOldFormat = isset($map['First Name']) || isset($map['Last Name']);
        if ($isOldFormat) {
            // Convert old format to new format
            $oldToNew = [
                'Timestamp' => 'timestamp',
                'Email Address' => 'email',
                'First Name' => 'first_name',
                'Middle Name' => 'middle_name',
                'Last Name' => 'last_name',
                'Nickname' => 'nickname',
                'Sex' => 'sex',
                'Sector' => 'sector',
                'Agency' => 'agency',
                'Designation' => 'designation',
                'Office Email' => 'office_email',
                'Contact No' => 'contact_no',
            ];
            $newMap = [];
            foreach ($oldToNew as $oldKey => $newKey) {
                $newMap[$newKey] = $map[$oldKey] ?? false;
            }
            $map = $newMap;
        }
        
        $get = function(string $field) use ($map, $data) {
            if (!isset($map[$field]) || $map[$field] === false) {
                return '';
            }
            $index = $map[$field];
            return isset($data[$index]) ? trim((string)$data[$index]) : '';
        };
        
        // Get basic fields
        $timestamp = $get('timestamp');
        $email = $get('email');
        $fullName = $get('full_name');
        $firstName = $get('first_name');
        $middleName = $get('middle_name');
        $lastName = $get('last_name');
        $nickname = $get('nickname');
        $sex = $get('sex');
        $sector = $get('sector');
        $agency = $get('agency');
        $designation = $get('designation');
        $officeEmail = $get('office_email');
        $contactNo = $get('contact_no');
        
        // If we have full_name but not separate name parts, parse it
        if (!empty($fullName) && (empty($firstName) || empty($lastName))) {
            $parsed = $this->parseFullName($fullName);
            if (empty($firstName)) $firstName = $parsed['first_name'];
            if (empty($middleName)) $middleName = $parsed['middle_name'];
            if (empty($lastName)) $lastName = $parsed['last_name'];
        }
        
        // Handle email from multiple possible columns (CSV may have duplicate email columns)
        // If email mapping found a column but it's empty, try other email-like columns
        if (empty($email)) {
            // Try to find email in data array by checking if value is a valid email
            foreach ($data as $value) {
                $value = trim((string)$value);
                if (!empty($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $email = $value;
                    break;
                }
            }
        }
        
        return [
            'timestamp' => $timestamp,
            'email' => $email,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'nickname' => $nickname,
            'sex' => $sex,
            'sector' => $sector,
            'agency' => $agency,
            'designation' => $designation,
            'office_email' => $officeEmail,
            'contact_no' => $contactNo,
        ];
    }

    private function detectStatus(\PDO $pdo, array $row): string
    {
        if ($row['first_name'] === '' || $row['last_name'] === '') return 'Error';
        if ($row['email'] !== '') {
            $s = $pdo->prepare('SELECT id FROM participants WHERE email = ?');
            $s->execute([$row['email']]);
            if ($s->fetch()) return 'Duplicate (email)';
        } else {
            $s = $pdo->prepare('SELECT id FROM participants WHERE first_name = ? AND last_name = ? AND agency = ?');
            $s->execute([$row['first_name'], $row['last_name'], ($row['agency']!==''?$row['agency']:null)]);
            if ($s->fetch()) return 'Duplicate (name+agency)';
        }
        return 'New';
    }

    private function findMatch(\PDO $pdo, array $row): ?array
    {
        if ($row['email'] !== '') {
            $s = $pdo->prepare('SELECT * FROM participants WHERE email = ?');
            $s->execute([$row['email']]);
            $m = $s->fetch();
            if ($m) return $m;
        }
        $s = $pdo->prepare('SELECT * FROM participants WHERE first_name = ? AND last_name = ? AND agency = ?');
        $s->execute([$row['first_name'], $row['last_name'], ($row['agency']!==''?$row['agency']:null)]);
        $m = $s->fetch();
        return $m ?: null;
    }

    /**
     * Process a single row immediately (insert or update)
     */
    private function processSingleRow(\PDO $pdo, string $action, array $row, ?array $match): array
    {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'insert') {
                $uuid = \App\Services\Uuid::v4();
                $stmt = $pdo->prepare('INSERT INTO participants (uuid,email,first_name,middle_name,last_name,nickname,sex,sector,agency,designation,office_email,contact_no,qr_path,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([
                    $uuid,
                    $row['email'] !== '' ? $row['email'] : null,
                    $row['first_name'],
                    $row['middle_name'] !== '' ? $row['middle_name'] : null,
                    $row['last_name'],
                    $row['nickname'] !== '' ? $row['nickname'] : null,
                    $row['sex'] !== '' ? $row['sex'] : null,
                    $row['sector'] !== '' ? $row['sector'] : null,
                    $row['agency'] !== '' ? $row['agency'] : null,
                    $row['designation'] !== '' ? $row['designation'] : null,
                    $row['office_email'] !== '' ? $row['office_email'] : null,
                    $row['contact_no'] !== '' ? $row['contact_no'] : null,
                    null,
                    (int)$_SESSION['admin_id'],
                ]);
                $pdo->commit();
                return ['success' => true, 'changes' => []];
            } else {
                $old = $match;
                $fields = ['first_name','middle_name','last_name','nickname','sex','sector','agency','designation','office_email','contact_no'];
                $changed = [];
                foreach ($fields as $f) { 
                    if ((string)$old[$f] !== (string)($row[$f] !== '' ? $row[$f] : null)) { 
                        $changed[$f] = ['old'=>$old[$f],'new'=>$row[$f]]; 
                    } 
                }
                $stmt = $pdo->prepare('UPDATE participants SET first_name=?, middle_name=?, last_name=?, nickname=?, sex=?, sector=?, agency=?, designation=?, office_email=?, contact_no=? WHERE id=?');
                $stmt->execute([
                    $row['first_name'],
                    $row['middle_name'] !== '' ? $row['middle_name'] : null,
                    $row['last_name'],
                    $row['nickname'] !== '' ? $row['nickname'] : null,
                    $row['sex'] !== '' ? $row['sex'] : null,
                    $row['sector'] !== '' ? $row['sector'] : null,
                    $row['agency'] !== '' ? $row['agency'] : null,
                    $row['designation'] !== '' ? $row['designation'] : null,
                    $row['office_email'] !== '' ? $row['office_email'] : null,
                    $row['contact_no'] !== '' ? $row['contact_no'] : null,
                    (int)$old['id'],
                ]);
                $pdo->commit();
                $changes = $changed ? [['id'=>$old['id'],'fields'=>$changed]] : [];
                return ['success' => true, 'changes' => $changes];
            }
        } catch (\Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage(), 'changes' => []];
        }
    }

    private function runBatch(\PDO $pdo, array $batch): array
    {
        $pdo->beginTransaction();
        try {
            $inserted = 0; $updated = 0; $changes = [];
            foreach ($batch as $item) {
                if ($item['action'] === 'insert') {
                    $uuid = \App\Services\Uuid::v4();
                    $stmt = $pdo->prepare('INSERT INTO participants (uuid,email,first_name,middle_name,last_name,nickname,sex,sector,agency,designation,office_email,contact_no,qr_path,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([
                        $uuid,
                        $item['row']['email'] !== '' ? $item['row']['email'] : null,
                        $item['row']['first_name'],
                        $item['row']['middle_name'] !== '' ? $item['row']['middle_name'] : null,
                        $item['row']['last_name'],
                        $item['row']['nickname'] !== '' ? $item['row']['nickname'] : null,
                        $item['row']['sex'] !== '' ? $item['row']['sex'] : null,
                        $item['row']['sector'] !== '' ? $item['row']['sector'] : null,
                        $item['row']['agency'] !== '' ? $item['row']['agency'] : null,
                        $item['row']['designation'] !== '' ? $item['row']['designation'] : null,
                        $item['row']['office_email'] !== '' ? $item['row']['office_email'] : null,
                        $item['row']['contact_no'] !== '' ? $item['row']['contact_no'] : null,
                        null,
                        (int)$_SESSION['admin_id'],
                    ]);
                    $inserted++;
                } else {
                    $old = $item['match'];
                    $fields = ['first_name','middle_name','last_name','nickname','sex','sector','agency','designation','office_email','contact_no'];
                    $changed = [];
                    foreach ($fields as $f) { if ((string)$old[$f] !== (string)($item['row'][$f] !== '' ? $item['row'][$f] : null)) { $changed[$f] = ['old'=>$old[$f],'new'=>$item['row'][$f]]; } }
                    $stmt = $pdo->prepare('UPDATE participants SET first_name=?, middle_name=?, last_name=?, nickname=?, sex=?, sector=?, agency=?, designation=?, office_email=?, contact_no=? WHERE id=?');
                    $stmt->execute([
                        $item['row']['first_name'],
                        $item['row']['middle_name'] !== '' ? $item['row']['middle_name'] : null,
                        $item['row']['last_name'],
                        $item['row']['nickname'] !== '' ? $item['row']['nickname'] : null,
                        $item['row']['sex'] !== '' ? $item['row']['sex'] : null,
                        $item['row']['sector'] !== '' ? $item['row']['sector'] : null,
                        $item['row']['agency'] !== '' ? $item['row']['agency'] : null,
                        $item['row']['designation'] !== '' ? $item['row']['designation'] : null,
                        $item['row']['office_email'] !== '' ? $item['row']['office_email'] : null,
                        $item['row']['contact_no'] !== '' ? $item['row']['contact_no'] : null,
                        (int)$old['id'],
                    ]);
                    if ($changed) $changes[] = ['id'=>$old['id'],'fields'=>$changed];
                    $updated++;
                }
            }
            $pdo->commit();
            return [$inserted,$updated,$changes];
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function renderPreview(array $rows, array $errors): void
    {
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_import.php';
    }
}