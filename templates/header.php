<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title : 'Cendana Kargo'; ?></title>
    <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/CendanaKargo/');
    ?>
    <link rel="stylesheet" href="<?= BASE_URL; ?>Bootstrap/css/bootstrap.min.css">
</head>
<body>
