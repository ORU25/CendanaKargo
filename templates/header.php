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
    <link rel="stylesheet" href="<?= BASE_URL; ?>Bootstrap/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL; ?>/assets/favicon.ico">
    
    <!-- Cloudflare Turnstile CAPTCHA -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    <style>
        /* Fix button hover color issue */
        .btn-outline-primary:hover {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }
        .btn-outline-success:hover {
            background-color: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }
        .btn-outline-warning:hover {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #000 !important;
        }
        .btn-outline-info:hover {
            background-color: #0dcaf0 !important;
            border-color: #0dcaf0 !important;
            color: #000 !important;
        }
    </style>
</head>
<body>
