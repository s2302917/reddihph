<?php $assetBaseUrl = rtrim(str_replace('\\', '/', APP_BASE_URL), '/'); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($config['app']['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl) ?>/assets/css/auth.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/auth.css') ?>">
</head>
<body>
<?= $content ?>
<script src="<?= htmlspecialchars($assetBaseUrl) ?>/assets/js/script.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/script.js') ?>" defer></script>
</body>
</html>
