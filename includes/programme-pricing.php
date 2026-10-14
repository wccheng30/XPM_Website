<?php
/**
 * Programme price helpers. SST is 8%. Stored tax_amount is used when it is
 * already set; otherwise the amount is calculated from the fee.
 */

function programme_sst_amount($programme) {
    $price = isset($programme['price']) && is_numeric($programme['price']) ? (float) $programme['price'] : 0;
    if ($price <= 0) {
        return 0.0;
    }

    $stored = isset($programme['tax_amount']) && is_numeric($programme['tax_amount']) ? (float) $programme['tax_amount'] : 0;
    if ($stored > 0) {
        return $stored;
    }

    $inclusive = isset($programme['tax_inclusive']) && (int) $programme['tax_inclusive'] === 1;
    if ($inclusive) {
        return $price - ($price / 1.08);
    }

    return $price * 0.08;
}

function programme_sst_note($programme) {
    $amount = programme_sst_amount($programme);
    if ($amount <= 0) {
        return '';
    }

    $inclusive = isset($programme['tax_inclusive']) && (int) $programme['tax_inclusive'] === 1;
    $label = $inclusive ? 'Includes' : 'Excludes';
    return $label . ' RM ' . number_format($amount, 2) . ' SST';
}

function programme_registration_is_open($programme) {
    if (!is_array($programme)) {
        return false;
    }

    $active = isset($programme['is_active']) && (int) $programme['is_active'] === 1;
    $status = isset($programme['registration_status']) ? (string) $programme['registration_status'] : '';
    return $active && $status === 'open';
}

function programme_registration_block_message($programme) {
    if (!is_array($programme) || !isset($programme['is_active']) || (int) $programme['is_active'] !== 1) {
        return 'This programme is not open for registration.';
    }
    if (($programme['registration_status'] ?? '') === 'full') {
        return 'Registration for this programme is full.';
    }
    return 'Registration for this programme is closed.';
}
