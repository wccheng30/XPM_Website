<?php
/**
 * Shared <head> assets for public pages.
 * Optional before include: $xpm_page_styles = ['pages/jobs.css', ...]
 */
$xpm_asset_version = '20261028-1';
$xpm_page_styles = isset($xpm_page_styles) && is_array($xpm_page_styles) ? $xpm_page_styles : [];
?>
    <script>document.documentElement.classList.add('js');setTimeout(function(){if(!window.__xpmMotion)document.documentElement.classList.add('reveal-fallback');},2500);</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/xpm-tokens.css?v=<?php echo $xpm_asset_version; ?>">
    <link rel="stylesheet" href="assets/css/xpm-base.css?v=<?php echo $xpm_asset_version; ?>">
    <link rel="stylesheet" href="assets/css/xpm-components.css?v=<?php echo $xpm_asset_version; ?>">
    <link rel="stylesheet" href="assets/css/xpm-motion.css?v=<?php echo $xpm_asset_version; ?>">
<?php foreach ($xpm_page_styles as $xpm_style): ?>
    <link rel="stylesheet" href="assets/css/<?php echo htmlspecialchars($xpm_style); ?>?v=<?php echo $xpm_asset_version; ?>">
<?php endforeach; ?>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
