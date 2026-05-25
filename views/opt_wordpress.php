<label><?php echo lang('export_types'); ?></label>
<div class="rdx-checkbox-container">
	<label><input type="checkbox" name="export_type[]" value="user" checked /> <?php echo lang('export_type_member'); ?></label>
	<label><input type="checkbox" name="export_type[]" value="post" checked /> <?php echo lang('export_type_post'); ?></label>
</div>

<label><?php echo lang('include_attachments'); ?></label>
<div class="rdx-checkbox-container">
	<label><input type="radio" name="include_attachments" value="Y" /> <?php echo lang('yes'); ?></label>
	<label><input type="radio" name="include_attachments" value="N" checked /> <?php echo lang('no'); ?></label>
</div>
<p class="rdx-note">
	<?php echo lang('include_attachments_desc'); ?>
</p>
