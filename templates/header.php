<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) : 'Cendana Kargo'; ?></title>
    <?php
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/CendanaKargo/');
    }
    ?>
    <link rel="stylesheet" href="<?= BASE_URL; ?>Bootstrap/css/bootstrap.min.css">
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL; ?>/assets/favicon.ico">
</head>
<body>
