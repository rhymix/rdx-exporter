<?php if (!class_exists('PDO')): ?>
	<div class="rdx-error">
		<h2>⚠️ PDO Extension Missing</h2>
		<p>The PDO extension is required for RDX exporter to function properly.</p>
	</div>
<?php endif; ?>

<?php if (!class_exists('ZipArchive')): ?>
	<div class="rdx-error">
		<h2>⚠️ ZipArchive Extension Missing</h2>
		<p>The ZipArchive extension is required for RDX exporter to function properly.</p>
	</div>
<?php endif; ?>

<?php if ((!file_exists(RDX_EXPORTER_PATH . '/temp') && !@mkdir(RDX_EXPORTER_PATH . '/temp', 0755, true)) || !is_writable(RDX_EXPORTER_PATH . '/temp')): ?>
	<div class="rdx-error">
		<h2>⚠️ Temp Directory Cannot Be Created</h2>
		<p>Please create a "temp" directory inside the RDX exporter path and change its permissions to 777.</p>
	</div>
<?php endif; ?>
