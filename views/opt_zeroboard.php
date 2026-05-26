<label><?php echo lang('export_types'); ?></label>
<div class="rdx-checkbox-container">
	<label><input type="checkbox" name="export_type[]" value="member" checked /> <?php echo lang('export_type_member'); ?></label>
	<label><input type="checkbox" name="export_type[]" value="message" checked /> <?php echo lang('export_type_message'); ?></label>
	<label><input type="checkbox" name="export_type[]" value="board" /> <?php echo lang('export_type_board'); ?></label>
</div>

<label><?php echo lang('include_attachments'); ?></label>
<div class="rdx-checkbox-container">
	<label><input type="radio" name="include_attachments" value="Y" /> <?php echo lang('yes'); ?></label>
	<label><input type="radio" name="include_attachments" value="N" checked /> <?php echo lang('no'); ?></label>
</div>
<p class="rdx-note">
	<?php echo lang('include_attachments_desc'); ?>
</p>

<label><?php echo lang('select_boards'); ?></label>
<select name="boards" multiple>
	<?php foreach ($boards as $board): ?>
		<option value="<?php echo escape($board['table_name']); ?>"><?php echo escape($board['name'], false); ?></option>
	<?php endforeach; ?>
</select>

<label><?php echo lang('db_charset'); ?></label>
<select name="db_charset">
	<option value="euckr" <?php if ($charset === 'euckr') echo 'selected'; ?>>EUC-KR</option>
	<option value="utf8" <?php if ($charset === 'utf8') echo 'selected'; ?>>UTF-8</option>
</select>

<label><?php echo lang('zip_password'); ?></label>
<input type="password" id="zip_password" name="zip_password" autocomplete="new-password" />
<p class="rdx-note">
	<?php echo lang('zip_password_desc'); ?>
</p>
