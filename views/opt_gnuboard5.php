<label>Export Type</label>
<div class="rdx-checkbox-container">
	<label><input type="checkbox" name="export_type[]" value="member" checked /> Member</label>
	<label><input type="checkbox" name="export_type[]" value="message" checked /> Message</label>
	<label><input type="checkbox" name="export_type[]" value="board" /> Board</label>
</div>

<label>Include Attachments</label>
<div class="rdx-checkbox-container">
	<label><input type="radio" name="include_attachments" value="Y" /> Yes</label>
	<label><input type="radio" name="include_attachments" value="N" checked /> No</label>
</div>
<p class="rdx-note">
	Including attachments may increase the export time and file size significantly.
</p>

<label>Select Boards</label>
<select name="boards" multiple>
	<?php foreach ($boards as $board): ?>
		<option value="<?php echo escape($board['bo_table']); ?>"><?php echo escape($board['name'], false); ?></option>
	<?php endforeach; ?>
</select>
