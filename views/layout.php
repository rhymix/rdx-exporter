<?php if (!defined('RDX_EXPORTER_PATH')) exit(); ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
	<title>RDX Exporter</title>
	<link rel="stylesheet" href="<?php echo asset('views/assets/common.css'); ?>" />
	<script src="<?php echo asset('views/assets/jquery-3.7.1.min.js'); ?>"></script>
	<script src="<?php echo asset('views/assets/common.js'); ?>"></script>
</head>

<!-- BODY START -->
<body>

<?php echo $content; ?>

</body>
</html>
