<?php // views/partials/head.php ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <title><?= htmlspecialchars($title ?? 'SoDrink', ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="color-scheme" content="light dark">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- CSS avec BASE URL -->
  <link rel="stylesheet" href="<?= WEB_BASE ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?= WEB_BASE ?>/assets/css/layout.css">
  <link rel="stylesheet" href="<?= WEB_BASE ?>/assets/css/components.css">

  <!-- Expose BASE au JS -->
  <script>window.SODRINK_BASE = "<?= WEB_BASE ?>";</script>
</head>
