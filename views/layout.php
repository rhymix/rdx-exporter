<?php if (!defined('RDX_EXPORTER_PATH')) exit(); ?>
<!DOCTYPE html>
<html lang="<?php echo escape($current_lang); ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
	<title>RDX Exporter</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php if ($current_lang === 'ko'): ?>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap">
<?php else: ?>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap">
<?php endif; ?>
	<link rel="stylesheet" href="<?php echo asset('views/assets/common.css'); ?>">
	<script src="<?php echo asset('views/assets/jquery-3.7.1.min.js'); ?>"></script>
	<script src="<?php echo asset('views/assets/common.js'); ?>"></script>
</head>

<!-- BODY START -->
<body>

<?php echo $content; ?>

</body>
</html>
