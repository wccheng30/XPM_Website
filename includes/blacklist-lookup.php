<?php

function load_blacklist_lookup(PDO $pdo): array
{
    $lookup = [];

    try {
        $stmt = $pdo->query('SELECT id, name, phone_number, reason FROM blacklist');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = strtolower(trim((string)($row['name'] ?? '')));
            $phone = trim((string)($row['phone_number'] ?? ''));
            if ($name === '' && $phone === '') {
                continue;
            }
            if ($name !== '' && $phone !== '') {
                $lookup[$name . '|' . $phone] = $row;
            }
            if ($name !== '' && !isset($lookup[$name])) {
                $lookup[$name] = $row;
            }
        }
    } catch (PDOException $e) {
        return [];
    }

    return $lookup;
}

function blacklist_person_exists(PDO $pdo, string $name, string $phone): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM blacklist WHERE name = ? AND phone_number = ?');
    $stmt->execute([$name, $phone]);
    return (int)$stmt->fetchColumn() > 0;
}

function blacklist_add_person(PDO $pdo, string $name, string $phone, string $position, string $reason): void
{
    $name = trim($name);
    $phone = trim($phone);
    if ($name === '' || blacklist_person_exists($pdo, $name, $phone)) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO blacklist (name, position_applied, phone_number, date, reason) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $position, $phone, date('Y-m-d'), $reason]);
}

function blacklist_remove_person(PDO $pdo, string $name, string $phone): void
{
    $stmt = $pdo->prepare('DELETE FROM blacklist WHERE name = ? AND phone_number = ?');
    $stmt->execute([trim($name), trim($phone)]);
}

function blacklist_source(PDO $pdo, string $source, int $id): ?array
{
    if ($source === 'application') {
        $stmt = $pdo->prepare("SELECT a.name, a.phone, COALESCE(NULLIF(j.title, ''), 'Job application') AS position
            FROM applications a
            LEFT JOIN jobs j ON a.job_id = j.id
            WHERE a.id = ?");
        $reason = 'Blacklisted from a job application';
    } elseif ($source === 'registration') {
        $stmt = $pdo->prepare("SELECT pr.name, pr.phone, COALESCE(NULLIF(p.title, ''), NULLIF(pr.position, ''), 'Programme registration') AS position
            FROM programme_registrations pr
            LEFT JOIN programmes p ON pr.programme_id = p.id
            WHERE pr.id = ?");
        $reason = 'Blacklisted from a programme registration';
    } elseif ($source === 'contact') {
        $stmt = $pdo->prepare("SELECT name, phone, 'Contact' AS position FROM contacts WHERE id = ?");
        $reason = 'Blacklisted from a contact';
    } else {
        return null;
    }

    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || trim((string)($row['name'] ?? '')) === '') {
        return null;
    }

    return [
        'name' => (string)$row['name'],
        'phone' => (string)($row['phone'] ?? ''),
        'position' => (string)($row['position'] ?? ''),
        'reason' => $reason,
    ];
}

function blacklist_still_marked(PDO $pdo, string $name, string $phone): bool
{
    $tables = ['applications', 'programme_registrations', 'contacts'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'blacklisted' AND name = ? AND phone = ?");
        $stmt->execute([$name, $phone]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }
    }
    return false;
}

function blacklist_sync_status(PDO $pdo, string $source, int $id, string $previousStatus, string $newStatus): void
{
    $record = blacklist_source($pdo, $source, $id);
    if (!$record) {
        return;
    }

    if ($newStatus === 'blacklisted') {
        blacklist_add_person($pdo, $record['name'], $record['phone'], $record['position'], $record['reason']);
        return;
    }

    if ($previousStatus === 'blacklisted' && !blacklist_still_marked($pdo, $record['name'], $record['phone'])) {
        blacklist_remove_person($pdo, $record['name'], $record['phone']);
    }
}

function blacklist_import_marked(PDO $pdo): void
{
    $applications = $pdo->query("SELECT a.id FROM applications a WHERE a.status = 'blacklisted'");
    foreach ($applications->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $record = blacklist_source($pdo, 'application', (int)$id);
        if ($record) {
            blacklist_add_person($pdo, $record['name'], $record['phone'], $record['position'], $record['reason']);
        }
    }

    $registrations = $pdo->query("SELECT id FROM programme_registrations WHERE status = 'blacklisted'");
    foreach ($registrations->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $record = blacklist_source($pdo, 'registration', (int)$id);
        if ($record) {
            blacklist_add_person($pdo, $record['name'], $record['phone'], $record['position'], $record['reason']);
        }
    }

    $contacts = $pdo->query("SELECT id FROM contacts WHERE status = 'blacklisted'");
    foreach ($contacts->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $record = blacklist_source($pdo, 'contact', (int)$id);
        if ($record) {
            blacklist_add_person($pdo, $record['name'], $record['phone'], $record['position'], $record['reason']);
        }
    }
}

function blacklist_clear_marked(PDO $pdo, string $name, string $phone): void
{
    $name = trim($name);
    $phone = trim($phone);
    $pdo->prepare("UPDATE applications SET status = 'pending' WHERE status = 'blacklisted' AND name = ? AND phone = ?")->execute([$name, $phone]);
    $pdo->prepare("UPDATE programme_registrations SET status = 'pending' WHERE status = 'blacklisted' AND name = ? AND phone = ?")->execute([$name, $phone]);
    $pdo->prepare("UPDATE contacts SET status = 'new' WHERE status = 'blacklisted' AND name = ? AND phone = ?")->execute([$name, $phone]);
}

function blacklist_match(array $lookup, string $name, ?string $phone = null): ?array
{
    $nameKey = strtolower(trim($name));
    $phoneKey = trim((string)$phone);

    if ($nameKey !== '' && $phoneKey !== '' && isset($lookup[$nameKey . '|' . $phoneKey])) {
        return $lookup[$nameKey . '|' . $phoneKey];
    }

    if ($nameKey !== '' && isset($lookup[$nameKey])) {
        return $lookup[$nameKey];
    }

    return null;
}
