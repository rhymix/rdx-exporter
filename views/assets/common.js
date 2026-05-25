(function($) {
	$(function() {

		// CMS type selector
		$('#cms_type').on('change', function() {
			const value = $(this).val();
			$('.rdx-container .credentials').hide();
			$('.rdx-container .credentials[data-for-cms*="' + value + '"]').show().find('input,select').first().focus();
			$('.options-container').removeClass('active');
		}).trigger('change');

		// Validate CMS credentials
		$('.rdx-container .credentials').on('keypress', 'input', function(e) {
			if (e.which === 13) {
				$(this).closest('.credentials').find('.validate-button').trigger('click');
				return false;
			}
		});
		$('.rdx-container .credentials').on('click', '.validate-button', function() {
			const button = $(this).prop('disabled', true);
			button.data('originalText', button.text()).text(button.data('waitingText'));
			const container = $(this).closest('.credentials');
			const data = {
				action: 'validate',
				cms_type: $('#cms_type').val()
			};
			container.find('input,select,textarea').each(function() {
				if ((this.type === 'checkbox' || this.type === 'radio') && !$(this).is(':checked')) {
					return;
				} else if (String(this.name).indexOf('[]') !== -1) {
					if (!data[this.name]) {
						data[this.name] = [];
					}
					data[this.name].push($(this).val());
				} else {
					data[this.name] = $(this).val();
				}
			});
			$.ajax({
				url: './index.php',
				method: 'POST',
				data: data,
				dataType: 'json',
				success: function(data) {
					if (data.success && data.options_html) {
						$('.options-container .options').html(data.options_html).show();
						$('.options-container').addClass('active');
					} else {
						alert(data.message);
					}
					button.text(button.data('originalText')).prop('disabled', false);
				},
				error: function(xhr) {
					alert(xhr.responseText);
					button.text(button.data('originalText')).prop('disabled', false);
				}
			});
		});

		// Export CMS data
		$('.options-container .buttons').on('click', '.export-button', function() {
			const button = $(this).prop('disabled', true);
			button.data('originalText', button.text()).text(button.data('waitingText'));
			const data = {
				action: 'export',
				cms_type: $('#cms_type').val()
			};
			$('.rdx-container .credentials:visible').find('input,select,textarea').each(function() {
				if ((this.type === 'checkbox' || this.type === 'radio') && !$(this).is(':checked')) {
					return;
				} else if (String(this.name).indexOf('[]') !== -1) {
					if (!data[this.name]) {
						data[this.name] = [];
					}
					data[this.name].push($(this).val());
				} else {
					data[this.name] = $(this).val();
				}
			});
			$('.options-container .options').find('input,select,textarea').each(function() {
				if ((this.type === 'checkbox' || this.type === 'radio') && !$(this).is(':checked')) {
					return;
				} else if (String(this.name).indexOf('[]') !== -1) {
					if (!data[this.name]) {
						data[this.name] = [];
					}
					data[this.name].push($(this).val());
				} else {
					data[this.name] = $(this).val();
				}
			});
			$.ajax({
				url: './index.php',
				method: 'POST',
				data: data,
				dataType: 'json',
				success: function(data) {
					if (data.success && data.download_url) {
						document.getElementById('downloader').src = data.download_url;
					} else if (data.message) {
						alert(data.message);
					}
					button.text(button.data('originalText')).prop('disabled', false);
				},
				error: function(xhr) {
					alert(xhr.responseText);
					button.text(button.data('originalText')).prop('disabled', false);
				}
			});
		});
	});
})(jQuery);
