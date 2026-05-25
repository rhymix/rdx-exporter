<div class="rdx-header">
	<h1>RDX Exporter</h1>
</div>

<?php
	// Check the environment for required extensions and permissions.
	include 'check_env.php';

	// Try to detect CMS type and installation path from parent directory.
	list($cms_type, $cms_path) = auto_detect_cms();
?>

<div class="rdx-container">

	<div class="cms-selector">
		<label for="cms_type">Select CMS</label>
		<select id="cms_type" name="cms_type">
			<option value=""></option>
			<option value="Rhymix"<?php echo $cms_type === 'Rhymix' ? ' selected' : ''; ?>>Rhymix</option>
			<option value="XE1"<?php echo $cms_type === 'XE1' ? ' selected' : ''; ?>>XpressEngine 1.x</option>
			<option value="XE3"<?php echo $cms_type === 'XE3' ? ' selected' : ''; ?>>XpressEngine 3.x</option>
			<option value="Gnuboard5"<?php echo $cms_type === 'Gnuboard5' ? ' selected' : ''; ?>>Gnuboard 5</option>
			<option value="WordPress"<?php echo $cms_type === 'WordPress' ? ' selected' : ''; ?>>WordPress</option>
		</select>
	</div>

	<div class="credentials" data-for-cms="Rhymix XE1">
		<label for="xe1_install_path">Installation Path</label>
		<input type="text" id="xe1_install_path" name="install_path" value="<?php echo escape($cms_path); ?>" />
		<label for="xe1_db_password">Database Password</label>
		<input type="password" id="xe1_db_password" name="db_password" autocomplete="new-password" />
		<button class="validate-button">Validate</button>
	</div>

	<div class="credentials" data-for-cms="XE3 Gnuboard5 WordPress">
		미지원
	</div>

</div>

<div class="rdx-container options-container">
	<div class="options"></div>
	<div class="buttons">
		<button class="export-button">Export</button>
	</div>
</div>

<iframe id="downloader" style="display:none;"></iframe>
