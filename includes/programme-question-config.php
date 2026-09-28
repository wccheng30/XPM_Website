<?php

function ensureProgrammeQuestionTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS programme_registration_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            programme_id INT NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            input_type ENUM('text', 'email', 'tel', 'textarea', 'select') NOT NULL DEFAULT 'text',
            field_options TEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_programme_field (programme_id, field_key),
            INDEX idx_programme_enabled (programme_id, is_enabled),
            CONSTRAINT fk_programme_registration_questions_programme
                FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function getDefaultProgrammeQuestions(): array
{
    return [
        ['field_key' => 'name', 'field_label' => 'Full Name', 'input_type' => 'text', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 1],
        ['field_key' => 'email', 'field_label' => 'Email Address', 'input_type' => 'email', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 2],
        ['field_key' => 'phone', 'field_label' => 'Phone Number', 'input_type' => 'tel', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 3],
        ['field_key' => 'ic_number', 'field_label' => 'IC Number', 'input_type' => 'text', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 4],
        ['field_key' => 'company', 'field_label' => 'Company / Organization', 'input_type' => 'text', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 5],
        ['field_key' => 'position', 'field_label' => 'Position / Title', 'input_type' => 'text', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 6],
        ['field_key' => 'billing_address', 'field_label' => 'Billing Address', 'input_type' => 'textarea', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 7],
        ['field_key' => 'billing_contact_no', 'field_label' => 'Billing Contact Number', 'input_type' => 'tel', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 8],
        ['field_key' => 'payment_method', 'field_label' => 'Payment Method', 'input_type' => 'select', 'field_options' => "hrdf_claim|HRDF Claim\nnon_hrdf_claim|Non HRDF Claim", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 9],
        ['field_key' => 'vehicle_type', 'field_label' => 'Vehicle Type', 'input_type' => 'select', 'field_options' => "car|Car\nmotorcycle|Motorcycle\nother|Other", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 10],
        ['field_key' => 'plate_number', 'field_label' => 'Plate Number', 'input_type' => 'text', 'field_options' => '', 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 11],
        ['field_key' => 'how_did_you_hear', 'field_label' => 'How Did You Hear About This Class?', 'input_type' => 'select', 'field_options' => "website|Website\nsocial_media|Social Media\nreferral|Referral\nemail|Email\nadvertisement|Advertisement\nother|Other", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 12],
        ['field_key' => 'spicy_preference', 'field_label' => 'Spicy Preference', 'input_type' => 'select', 'field_options' => "spicy|Spicy\nno_spicy|No Spicy", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 13],
        ['field_key' => 'dietary_preference', 'field_label' => 'Dietary Preference', 'input_type' => 'select', 'field_options' => "none|No dietary restrictions\nvegetarian|Vegetarian\nvegan|Vegan\nhalal|Halal\nother|Other", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 14],
        ['field_key' => 'allergies', 'field_label' => 'Any Allergies?', 'input_type' => 'select', 'field_options' => "none|No allergies\nnuts|Nuts\ndairy|Dairy\ngluten|Gluten\nseafood|Seafood\neggs|Eggs\nother|Other", 'is_required' => 1, 'is_enabled' => 1, 'sort_order' => 15],
        ['field_key' => 'interested_programs', 'field_label' => 'Interested Programs', 'input_type' => 'select', 'field_options' => "laws_compliance|Laws and Compliance Training\nsoft_skills|Soft Skills Development\nother|Other", 'is_required' => 0, 'is_enabled' => 1, 'sort_order' => 16],
        ['field_key' => 'additional_notes', 'field_label' => 'Additional Notes', 'input_type' => 'textarea', 'field_options' => '', 'is_required' => 0, 'is_enabled' => 1, 'sort_order' => 17],
    ];
}

function saveProgrammeQuestionConfigs(PDO $pdo, int $programmeId, array $questions): void
{
    ensureProgrammeQuestionTable($pdo);
    $defaultsByKey = [];
    foreach (getDefaultProgrammeQuestions() as $question) {
        $defaultsByKey[$question['field_key']] = $question;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM programme_registration_questions WHERE programme_id = ?")->execute([$programmeId]);

        $insertStmt = $pdo->prepare("
            INSERT INTO programme_registration_questions
            (programme_id, field_key, field_label, input_type, field_options, is_required, is_enabled, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questions as $index => $question) {
            $fieldKey = trim((string)($question['field_key'] ?? ''));
            if (!isset($defaultsByKey[$fieldKey])) {
                continue;
            }

            $fieldLabel = trim((string)($question['field_label'] ?? $defaultsByKey[$fieldKey]['field_label']));
            $inputType = trim((string)($question['input_type'] ?? $defaultsByKey[$fieldKey]['input_type']));
            if (!in_array($inputType, ['text', 'email', 'tel', 'textarea', 'select'], true)) {
                $inputType = $defaultsByKey[$fieldKey]['input_type'];
            }

            $fieldOptions = trim((string)($question['field_options'] ?? $defaultsByKey[$fieldKey]['field_options']));
            if ($inputType !== 'select') {
                $fieldOptions = '';
            }

            $isRequired = !empty($question['is_required']) ? 1 : 0;
            $isEnabled = !empty($question['is_enabled']) ? 1 : 0;

            $insertStmt->execute([
                $programmeId,
                $fieldKey,
                $fieldLabel !== '' ? $fieldLabel : $defaultsByKey[$fieldKey]['field_label'],
                $inputType,
                $fieldOptions,
                $isRequired,
                $isEnabled,
                $index + 1
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getProgrammeQuestionConfigs(PDO $pdo, int $programmeId, bool $enabledOnly = false): array
{
    ensureProgrammeQuestionTable($pdo);

    $baseSql = "SELECT field_key, field_label, input_type, field_options, is_required, is_enabled, sort_order
                FROM programme_registration_questions WHERE programme_id = ?";
    $params = [$programmeId];

    if ($enabledOnly) {
        $baseSql .= " AND is_enabled = 1";
    }

    $baseSql .= " ORDER BY sort_order ASC, id ASC";
    $stmt = $pdo->prepare($baseSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!empty($rows)) {
        return $rows;
    }

    $defaults = getDefaultProgrammeQuestions();
    saveProgrammeQuestionConfigs($pdo, $programmeId, $defaults);
    return $enabledOnly ? array_values(array_filter($defaults, static fn($q) => !empty($q['is_enabled']))) : $defaults;
}

