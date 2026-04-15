/**
 * Labor Intel — Admin JavaScript
 *
 * @package LaborIntel
 * @since   1.0.0
 */

/* global jQuery, laborIntel */
(function ($) {
	'use strict';

	var $modal       = $('#li-workspace-modal');
	var $form        = $('#li-workspace-form');
	var $modalTitle  = $('#li-modal-title');
	var $saveBtn     = $('#li-save-workspace');
	var $notices     = $('#li-notices');
	var $idField     = $('#li-workspace-id');
	var $nameField   = $('#li-workspace-name');
	var $descField   = $('#li-workspace-desc');

	/* =============================================
	 * Notices
	 * ============================================= */
	function showNotice(message, type) {
		type = type || 'success';
		var $notice = $('<div class="li-notice li-notice--' + type + '"><p>' + $('<span>').text(message).html() + '</p></div>');
		$notices.html($notice);
		setTimeout(function () {
			$notice.fadeOut(300, function () {
				$(this).remove();
			});
		}, 4000);
	}

	/* =============================================
	 * Modal
	 * ============================================= */
	function openModal(mode, data) {
		$form[0].reset();
		$idField.val('');

		if (mode === 'edit' && data) {
			$modalTitle.text('Edit Workspace');
			$saveBtn.text('Update Workspace');
			$idField.val(data.id);
			$nameField.val(data.name);
			$descField.val(data.description);
		} else {
			$modalTitle.text('Create Workspace');
			$saveBtn.text('Create Workspace');
		}

		$modal.show();
		$nameField.focus();
	}

	function closeModal() {
		$modal.hide();
		$form[0].reset();
		$idField.val('');
	}

	/* Open modal — header button */
	$(document).on('click', '#li-open-create-modal, #li-empty-create-btn', function (e) {
		e.preventDefault();
		openModal('create');
	});

	/* Close modal */
	$(document).on('click', '#li-close-modal, #li-cancel-modal', function (e) {
		e.preventDefault();
		closeModal();
	});

	/* Close modal on overlay click */
	$modal.on('click', function (e) {
		if ($(e.target).is('.li-modal-overlay')) {
			closeModal();
		}
	});

	/* Close on ESC */
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $modal.is(':visible')) {
			closeModal();
		}
	});

	/* =============================================
	 * Create / Update Workspace
	 * ============================================= */
	$form.on('submit', function (e) {
		e.preventDefault();

		var id   = $idField.val();
		var name = $nameField.val().trim();
		var desc = $descField.val().trim();

		if (!name) {
			$nameField.focus();
			return;
		}

		var action = id ? 'labor_intel_update_workspace' : 'labor_intel_create_workspace';
		var btnText = $saveBtn.text();

		$saveBtn.prop('disabled', true).text(laborIntel.i18n.creating);

		$.post(laborIntel.ajaxUrl, {
			action:       action,
			nonce:        laborIntel.nonce,
			workspace_id: id,
			name:         name,
			description:  desc
		})
		.done(function (response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				closeModal();
				// Reload to reflect changes.
				location.reload();
			} else {
				showNotice(response.data.message || laborIntel.i18n.error, 'error');
			}
		})
		.fail(function () {
			showNotice(laborIntel.i18n.error, 'error');
		})
		.always(function () {
			$saveBtn.prop('disabled', false).text(btnText);
		});
	});

	/* =============================================
	 * Edit Workspace
	 * ============================================= */
	$(document).on('click', '.li-edit-workspace', function (e) {
		e.preventDefault();
		var $btn = $(this);
		openModal('edit', {
			id:          $btn.data('id'),
			name:        $btn.data('name'),
			description: $btn.data('description')
		});
	});

	/* =============================================
	 * Delete Workspace
	 * ============================================= */
	$(document).on('click', '.li-delete-workspace', function (e) {
		e.preventDefault();

		if (!confirm(laborIntel.i18n.confirmDelete)) {
			return;
		}

		var $btn = $(this);
		var id   = $btn.data('id');

		$btn.prop('disabled', true).text(laborIntel.i18n.deleting);

		$.post(laborIntel.ajaxUrl, {
			action:       'labor_intel_delete_workspace',
			nonce:        laborIntel.nonce,
			workspace_id: id
		})
		.done(function (response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				$('tr[data-workspace-id="' + id + '"]').fadeOut(300, function () {
					$(this).remove();
					// Show empty state if no more rows.
					if ($('#li-workspaces-tbody tr').length === 0) {
						location.reload();
					}
				});
			} else {
				showNotice(response.data.message || laborIntel.i18n.error, 'error');
				$btn.prop('disabled', false).text('Delete');
			}
		})
		.fail(function () {
			showNotice(laborIntel.i18n.error, 'error');
			$btn.prop('disabled', false).text('Delete');
		});
	});

	/* =============================================
	 * Control Panel & Pricing Forms
	 * ============================================= */
	$(document).on('submit', '#li-control-panel-form', function (e) {
		e.preventDefault();
		saveConfigForm($(this), 'labor_intel_save_control_panel', '#li-save-control-panel');
	});

	$(document).on('submit', '#li-pricing-form', function (e) {
		e.preventDefault();
		saveConfigForm($(this), 'labor_intel_save_pricing', '#li-save-pricing');
	});

	/* Live calculation for Pricing computed fields */
	$(document).on('change input', '#employee_count, #site_count, #pricing_model, #pepm, #annual_site_fee, #value_fee_pct, #value_fee_cap', function () {
		calcPricing();
	});

	function calcPricing() {
		var model        = $('#pricing_model').val();
		var employees    = parseFloat($('#employee_count').val()) || 0;
		var sites        = parseFloat($('#site_count').val()) || 0;
		var pepm         = parseFloat($('#pepm').val()) || 0;
		var siteFee      = parseFloat($('#annual_site_fee').val()) || 0;
		var valueFeePct  = parseFloat($('#value_fee_pct').val()) || 0;
		var valueFeeCap  = parseFloat($('#value_fee_cap').val()) || 0;
		var ebitdaLift   = parseFloat($('#modeled_ebitda_lift').val().replace(/,/g, '')) || 0;

		var fee = 0;
		if (model === 'PEPM') {
			fee = employees * pepm * 12;
		} else if (model === 'Site') {
			fee = sites * siteFee;
		} else if (model === 'Value') {
			fee = ebitdaLift > 0 ? Math.min((valueFeePct / 100) * ebitdaLift, valueFeeCap) : valueFeeCap;
		}

		$('#annual_platform_fee').val(fee ? fee.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');
		$('#roi_multiple').val(fee > 0 && ebitdaLift > 0 ? (ebitdaLift / fee).toFixed(1) + 'x' : '');
		$('#breakeven_months').val(ebitdaLift > 0 && fee > 0 ? (fee / (ebitdaLift / 12)).toFixed(1) : '');
	}

	function saveConfigForm($form, action, btnSelector) {
		var $btn = $(btnSelector);
		var btnText = $btn.text();
		var formData = $form.serializeArray();
		var data = { action: action, nonce: laborIntel.nonce };

		$.each(formData, function (i, field) {
			data[field.name] = field.value;
		});

		$btn.prop('disabled', true).text('Saving...');

		$.post(laborIntel.ajaxUrl, data)
			.done(function (response) {
				if (response.success) {
					showNotice(response.data.message, 'success');
				} else {
					showNotice(response.data.message || laborIntel.i18n.error, 'error');
				}
				$('html, body').animate({ scrollTop: 0 }, 300);
			})
			.fail(function () {
				showNotice(laborIntel.i18n.error, 'error');
				$('html, body').animate({ scrollTop: 0 }, 300);
			})
			.always(function () {
				$btn.prop('disabled', false).text(btnText);
			});
	}

	/* =============================================
	 * File Upload — Dropzone & Preview
	 * ============================================= */
	var $dropzone    = $('#li-dropzone');
	var $fileInput   = $('#li-file-input');
	var $preview     = $('#li-upload-preview');
	var $uploadBtn   = $('#li-upload-btn');
	var $uploadForm  = $('#li-upload-form');
	var selectedFile = null;

	// Only init upload logic if we're on the upload page.
	if ($dropzone.length) {
		// Click dropzone to open file browser.
		$dropzone.on('click', function (e) {
			// Avoid re-triggering when click comes from file input or browse button.
			if (e.target === $fileInput[0] || $(e.target).closest('#li-browse-btn').length) {
				return;
			}
			$fileInput.trigger('click');
		});

		// Browse button.
		$('#li-browse-btn').on('click', function (e) {
			e.stopPropagation();
			$fileInput.trigger('click');
		});

		// Drag events.
		$dropzone.on('dragover dragenter', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('li-dragover');
		}).on('dragleave drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('li-dragover');
		}).on('drop', function (e) {
			var files = e.originalEvent.dataTransfer.files;
			if (files.length) {
				handleFileSelect(files[0]);
			}
		});

		// File input change.
		$fileInput.on('change', function () {
			if (this.files.length) {
				handleFileSelect(this.files[0]);
			}
		});

		// Remove selected file.
		$('#li-remove-file').on('click', function () {
			clearFileSelection();
		});

		// Form submit.
		$uploadForm.on('submit', function (e) {
			e.preventDefault();
			if (!selectedFile) return;
			uploadFile();
		});
	}

	function handleFileSelect(file) {
		var allowed = ['xlsx', 'xls', 'csv'];
		var ext = file.name.split('.').pop().toLowerCase();

		if (allowed.indexOf(ext) === -1) {
			showNotice(laborIntel.i18n.invalidFile || 'Please select a valid Excel or CSV file.', 'error');
			return;
		}

		selectedFile = file;
		$('#li-preview-filename').text(file.name);
		$('#li-preview-filesize').text(formatFileSize(file.size));
		$dropzone.hide();
		$preview.show();
		$uploadBtn.prop('disabled', false);
	}

	function clearFileSelection() {
		selectedFile = null;
		$fileInput.val('');
		$preview.hide();
		$dropzone.show();
		$uploadBtn.prop('disabled', true);
		$('#li-upload-progress').hide();
		$('#li-progress-fill').css('width', '0%');
		$('#li-progress-text').text('0%');
	}

	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		var k = 1024;
		var sizes = ['Bytes', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
	}

	/**
	 * Open a popup window to display validation errors.
	 *
	 * @param {string} title    Title for the error window.
	 * @param {Array}  errors   Array of error message strings.
	 * @param {string} fileName Name of the uploaded file.
	 */
	function openValidationErrorsWindow(title, errors, fileName) {
		var errorHtml = errors.map(function (err, idx) {
			return '<tr><td class="li-err-num">' + (idx + 1) + '</td><td class="li-err-msg">' + $('<div>').text(err).html() + '</td></tr>';
		}).join('');

		var htmlContent = '<!DOCTYPE html>' +
			'<html lang="en">' +
			'<head>' +
			'<meta charset="UTF-8">' +
			'<meta name="viewport" content="width=device-width, initial-scale=1.0">' +
			'<title>Validation Errors - Labor Intel</title>' +
			'<style>' +
			'* { box-sizing: border-box; }' +
			'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; margin: 0; padding: 20px; background: #f0f0f1; color: #1d2327; }' +
			'.li-err-container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }' +
			'.li-err-header { background: #d63638; color: #fff; padding: 20px 24px; border-radius: 8px 8px 0 0; }' +
			'.li-err-header h1 { margin: 0 0 6px 0; font-size: 20px; font-weight: 600; }' +
			'.li-err-header p { margin: 0; opacity: 0.9; font-size: 14px; }' +
			'.li-err-warning { background: #fcf9e8; border: 1px solid #dba617; padding: 14px 24px; display: flex; align-items: flex-start; gap: 10px; }' +
			'.li-err-warning svg { flex-shrink: 0; margin-top: 2px; }' +
			'.li-err-warning p { margin: 0; font-size: 13px; line-height: 1.5; }' +
			'.li-err-actions { padding: 16px 24px; border-bottom: 1px solid #e0e0e0; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }' +
			'.li-err-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 13px; font-weight: 500; border-radius: 4px; cursor: pointer; border: none; text-decoration: none; }' +
			'.li-err-btn-primary { background: #2271b1; color: #fff; }' +
			'.li-err-btn-primary:hover { background: #135e96; }' +
			'.li-err-btn-secondary { background: #f0f0f1; color: #1d2327; border: 1px solid #c3c4c7; }' +
			'.li-err-btn-secondary:hover { background: #e0e0e0; }' +
			'.li-err-count { margin-left: auto; font-size: 13px; color: #646970; }' +
			'.li-err-table-wrap { max-height: 500px; overflow-y: auto; }' +
			'.li-err-table { width: 100%; border-collapse: collapse; font-size: 13px; }' +
			'.li-err-table th { background: #f6f7f7; position: sticky; top: 0; padding: 12px 16px; text-align: left; font-weight: 600; border-bottom: 1px solid #e0e0e0; }' +
			'.li-err-table td { padding: 10px 16px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }' +
			'.li-err-table tr:last-child td { border-bottom: none; }' +
			'.li-err-num { width: 50px; color: #646970; text-align: center; }' +
			'.li-err-msg { word-break: break-word; }' +
			'.li-err-footer { padding: 16px 24px; background: #f6f7f7; border-radius: 0 0 8px 8px; font-size: 12px; color: #646970; }' +
			'</style>' +
			'</head>' +
			'<body>' +
			'<div class="li-err-container">' +
			'<div class="li-err-header">' +
			'<h1>' + $('<div>').text(title).html() + '</h1>' +
			'<p>File: ' + $('<div>').text(fileName).html() + '</p>' +
			'</div>' +
			'<div class="li-err-warning">' +
			'<svg width="20" height="20" viewBox="0 0 20 20" fill="#dba617"><path d="M10 2L1 18h18L10 2zm0 3.5l6.5 11.5h-13L10 5.5zM9 8v4h2V8H9zm0 5v2h2v-2H9z"/></svg>' +
			'<p><strong>Important:</strong> Please copy these errors before closing this window. ' +
			'Refreshing the page or navigating away will clear this list. Fix the issues in your file and upload again.</p>' +
			'</div>' +
			'<div class="li-err-actions">' +
			'<button class="li-err-btn li-err-btn-primary" onclick="copyErrors()"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2zm2 0v8h8V2H6zM2 4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-1h-2v1H2V6h1V4H2z"/></svg> Copy All Errors</button>' +
			'<button class="li-err-btn li-err-btn-secondary" onclick="window.close()">Close Window</button>' +
			'<span class="li-err-count">' + errors.length + ' error(s) found</span>' +
			'</div>' +
			'<div class="li-err-table-wrap">' +
			'<table class="li-err-table">' +
			'<thead><tr><th>#</th><th>Error Description</th></tr></thead>' +
			'<tbody>' + errorHtml + '</tbody>' +
			'</table>' +
			'</div>' +
			'<div class="li-err-footer">' +
			'Generated on ' + new Date().toLocaleString() + ' &bull; Labor Intel Plugin' +
			'</div>' +
			'</div>' +
			'<script>' +
			'function copyErrors() {' +
			'var errors = ' + JSON.stringify(errors) + ';' +
			'var text = "Validation Errors for " + ' + JSON.stringify(fileName) + ' + "\\n\\n";' +
			'errors.forEach(function(err, idx) { text += (idx + 1) + ". " + err + "\\n"; });' +
			'navigator.clipboard.writeText(text).then(function() {' +
			'var btn = document.querySelector(".li-err-btn-primary");' +
			'var origText = btn.innerHTML;' +
			'btn.innerHTML = "<svg width=\\"16\\" height=\\"16\\" viewBox=\\"0 0 16 16\\" fill=\\"currentColor\\"><path d=\\"M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0z\\"/></svg> Copied!";' +
			'setTimeout(function() { btn.innerHTML = origText; }, 2000);' +
			'});' +
			'}' +
			'</script>' +
			'</body>' +
			'</html>';

		var errorWindow = window.open('', '_blank', 'width=800,height=650,scrollbars=yes,resizable=yes');
		if (errorWindow) {
			errorWindow.document.write(htmlContent);
			errorWindow.document.close();
			errorWindow.focus();
		} else {
			// Popup blocked — fall back to showing message.
			showNotice('Validation failed. Please allow popups to see error details, or check console.', 'error');
			console.error('Validation Errors:', errors);
		}
	}

	function uploadFile() {
		var formData = new FormData();
		formData.append('action', 'labor_intel_upload_file');
		formData.append('nonce', laborIntel.nonce);
		formData.append('workspace_id', $uploadForm.find('[name="workspace_id"]').val());
		formData.append('file_type', $uploadForm.find('[name="file_type"]').val());
		formData.append('file', selectedFile);

		$uploadBtn.prop('disabled', true).text(laborIntel.i18n.uploading || 'Uploading...');
		$('#li-upload-progress').show();

		$.ajax({
			url: laborIntel.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			xhr: function () {
				var xhr = new window.XMLHttpRequest();
				xhr.upload.addEventListener('progress', function (e) {
					if (e.lengthComputable) {
						var pct = Math.round((e.loaded / e.total) * 100);
						$('#li-progress-fill').css('width', pct + '%');
						$('#li-progress-text').text(pct + '%');
					}
				});
				return xhr;
			}
		})
		.done(function (response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				// Redirect back to workspace detail after short delay.
				var backUrl = $uploadForm.find('a.button').attr('href');
				setTimeout(function () {
					window.location.href = backUrl;
				}, 1200);
			} else {
				// Check if we have validation errors to display in popup.
				if (response.data && response.data.validation_errors && response.data.validation_errors.length > 0) {
					var fileType = $uploadForm.find('[name="file_type"]').val() || 'file';
					var fileTypeLabel = laborIntel.fileTypeLabels && laborIntel.fileTypeLabels[fileType] ? laborIntel.fileTypeLabels[fileType] : fileType;
					openValidationErrorsWindow(
						'Validation Errors - ' + fileTypeLabel,
						response.data.validation_errors,
						selectedFile ? selectedFile.name : 'Unknown File'
					);
					showNotice(response.data.message || 'Validation failed. See popup window for details.', 'error');
				} else {
					showNotice(response.data.message || laborIntel.i18n.error, 'error');
				}
				clearFileSelection();
				$uploadBtn.prop('disabled', false).text(laborIntel.i18n.uploadBtn || 'Upload File');
			}
		})
		.fail(function () {
			showNotice(laborIntel.i18n.error, 'error');
			clearFileSelection();
			$uploadBtn.prop('disabled', false).text(laborIntel.i18n.uploadBtn || 'Upload File');
		});
	}

	/* =============================================
	 * Start Processing Button
	 * ============================================= */
	$(document).on('click', '#li-start-processing', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var workspaceId = $btn.data('workspace-id');
		var btnHtml = $btn.html();

		if (!confirm('Are you sure you want to start processing this workspace? This action cannot be undone.')) {
			return;
		}

		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-top: 5px;"></span> Starting...');

		$.post(laborIntel.ajaxUrl, {
			action: 'labor_intel_start_processing',
			nonce: laborIntel.nonce,
			workspace_id: workspaceId
		})
		.done(function (response) {
			if (response.success) {
				showNotice(response.data.message, 'success');
				// Reload page to show updated status.
				setTimeout(function () {
					window.location.reload();
				}, 1500);
			} else {
				showNotice(response.data.message || 'Failed to start processing.', 'error');
				$btn.prop('disabled', false).html(btnHtml);
			}
		})
		.fail(function () {
			showNotice('An error occurred while starting processing.', 'error');
			$btn.prop('disabled', false).html(btnHtml);
		});
	});

})(jQuery);
