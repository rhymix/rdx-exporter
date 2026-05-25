<div class="rdx-header">
	<h1>RDX Exporter</h1>
	<p class="rdx-lang">
		<a href="javascript:setLang('ko');">한국어</a> |
		<a href="javascript:setLang('en');">English</a>
	</p>
</div>

<?php
	// Check the environment for required extensions and permissions.
	include 'check_env.php';

	// Try to detect CMS type and installation path from parent directory.
	list($cms_type, $cms_path) = auto_detect_cms();
?>

<div class="rdx-container">

	<div class="cms-selector">
		<label for="cms_type"><?php echo lang('select_cms'); ?></label>
		<select id="cms_type" name="cms_type">
			<option value=""></option>
			<option value="Rhymix"<?php echo $cms_type === 'Rhymix' ? ' selected' : ''; ?>>Rhymix</option>
			<option value="XE1"<?php echo $cms_type === 'XE1' ? ' selected' : ''; ?>>XpressEngine 1.x</option>
			<option value="XE3"<?php echo $cms_type === 'XE3' ? ' selected' : ''; ?>>XpressEngine 3.x</option>
			<option value="Gnuboard5"<?php echo $cms_type === 'Gnuboard5' ? ' selected' : ''; ?>>Gnuboard 5</option>
			<option value="WordPress"<?php echo $cms_type === 'WordPress' ? ' selected' : ''; ?>>WordPress</option>
		</select>
	</div>

	<div class="credentials" data-for-cms="Rhymix XE1 Gnuboard5 WordPress">
		<label for="xe1_install_path"><?php echo lang('install_path'); ?></label>
		<input type="text" id="xe1_install_path" name="install_path" value="<?php echo escape($cms_path); ?>" />
		<label for="xe1_db_password"><?php echo lang('db_password'); ?></label>
		<input type="password" id="xe1_db_password" name="db_password" autocomplete="new-password" />
		<button class="validate-button" data-waiting-text="<?php echo lang('validating'); ?>"><?php echo lang('validate'); ?></button>
	</div>

	<div class="credentials" data-for-cms="XE3">
		<?php echo lang('not_supported_yet'); ?>
	</div>

</div>

<div class="rdx-container options-container">
	<div class="options"></div>
	<div class="buttons">
		<button class="export-button" data-waiting-text="<?php echo lang('exporting'); ?>"><?php echo lang('export'); ?></button>
	</div>
</div>

<iframe id="downloader" style="display:none;"></iframe>
