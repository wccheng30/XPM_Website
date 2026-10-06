<?php
/**
 * Country-code phone input. The saved value is one string, for example "+60 123456789".
 */

function phone_country_codes() {
    return [
        '+60' => 'Malaysia',
        '+65' => 'Singapore',
        '+62' => 'Indonesia',
        '+673' => 'Brunei',
        '+66' => 'Thailand',
        '+63' => 'Philippines',
        '+84' => 'Vietnam',
        '+86' => 'China',
        '+852' => 'Hong Kong',
        '+81' => 'Japan',
        '+82' => 'South Korea',
        '+91' => 'India',
        '+61' => 'Australia',
        '+64' => 'New Zealand',
        '+44' => 'United Kingdom',
        '+1' => 'United States / Canada',
    ];
}

function combine_phone($code, $local) {
    $codes = phone_country_codes();
    $code = trim((string) $code);
    if (!isset($codes[$code])) {
        $code = '+60';
    }

    $local = preg_replace('/\D+/', '', (string) $local);
    $local = ltrim($local, '0');
    if ($local === '') {
        return '';
    }

    return $code . ' ' . $local;
}

function render_phone_field($id, $name, $required = false, $code = '+60', $local = '') {
    $codes = phone_country_codes();
    $code = isset($codes[$code]) ? $code : '+60';
    $required_attr = $required ? ' required' : '';
    ?>
    <div class="phone-field">
        <select id="<?php echo htmlspecialchars($id); ?>_code" name="<?php echo htmlspecialchars($name); ?>_code" aria-label="Country code">
            <?php foreach ($codes as $dial => $country): ?>
                <option value="<?php echo htmlspecialchars($dial); ?>" <?php echo $dial === $code ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dial . ' ' . $country); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="tel" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($local); ?>" placeholder="123456789" inputmode="numeric" autocomplete="tel-national"<?php echo $required_attr; ?>>
    </div>
    <p class="phone-field-hint">Example: +60 123456789</p>
    <?php
}
