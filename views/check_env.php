<?php if (!class_exists('PDO')): ?>
	<div class="rdx-error">
		<h2>⚠️ <?php echo lang('pdo_missing'); ?></h2>
		<p><?php echo lang('pdo_missing_desc'); ?></p>
	</div>
<?php endif; ?>

<?php if (!class_exists('ZipArchive')): ?>
	<div class="rdx-error">
		<h2>⚠️ <?php echo lang('zip_missing'); ?></h2>
		<p><?php echo lang('zip_missing_desc'); ?></p>
	</div>
<?php endif; ?>

<?php if ((!file_exists(RDX_EXPORTER_PATH . '/temp') && !@mkdir(RDX_EXPORTER_PATH . '/temp', 0755, true)) || !is_writable(RDX_EXPORTER_PATH . '/temp')): ?>
	<div class="rdx-error">
		<h2>⚠️ <?php echo lang('temp_dir_error'); ?></h2>
		<p><?php echo lang('temp_dir_error_desc'); ?></p>
	</div>
<?php endif; ?>
