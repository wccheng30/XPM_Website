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
