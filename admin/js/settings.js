/*
 * Maqsy – AI Chat settings page behaviour.
 *
 * Wires up the "API base URL" preset/custom toggle and the "Fetch models"
 * button + autocomplete on the Model field. Configuration and translated
 * strings are passed in from PHP via wp_localize_script() as `maqsySettings`.
 */
(function ($) {
	'use strict';

	const config = window.maqsySettings || {};
	const i18n = config.i18n || {};

	/**
	 * "API base URL": show the free-text field only when "Custom" is picked,
	 * otherwise mirror the chosen preset into the (hidden) real input.
	 */
	function initBaseUrlToggle() {
		const select = document.getElementById('maqsy_base_url_preset');
		const custom = document.getElementById('maqsy_base_url_custom');
		const presetUrls = config.basePresetUrls || [];

		if (!select || !custom) {
			return;
		}

		select.addEventListener('change', function () {
			if ('custom' === select.value) {
				custom.style.display = 'inline-block';
				custom.focus();
			} else {
				custom.style.display = 'none';
				custom.value = select.value;
			}
		});

		if (-1 !== presetUrls.indexOf(custom.value)) {
			custom.style.display = 'none';
		}
	}

	/**
	 * "Model" field: jQuery UI Autocomplete populated by the "Fetch models"
	 * button, which asks the plugin's REST route for the configured provider's
	 * model list using whatever API key / base URL is currently in the form.
	 */
	function initModelField() {
		const $input = $('#maqsy_model_input');
		const $fetchButton = $('#maqsy_fetch_models');

		if (!$input.length || !$fetchButton.length) {
			return;
		}

		const $status = $('#maqsy_models_status');

		$input.autocomplete({ source: [], minLength: 0 });

		// Cap this widget instance's dropdown height without affecting other
		// autocomplete fields in wp-admin.
		$input.autocomplete('widget').css({
			maxHeight: '220px',
			overflowY: 'auto',
		});

		$input.on('focus', function () {
			$input.autocomplete('search', '');
		});

		$fetchButton.on('click', function () {
			const apiKey =
				$('[name="' + config.optionName + '[api_key]"]').val() || '';
			const baseUrl = $('#maqsy_base_url_custom').val() || '';

			$status.text(i18n.fetching || '');
			$fetchButton.prop('disabled', true);

			$.ajax({
				url: config.modelsEndpoint,
				method: 'POST',
				contentType: 'application/json',
				beforeSend(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', config.restNonce);
				},
				data: JSON.stringify({
					provider: config.provider,
					api_key: apiKey,
					base_url: baseUrl,
				}),
			})
				.done(function (result) {
					const models = (result && result.models) || [];
					$input.autocomplete('option', 'source', models);
					$status.text(
						models.length + ' ' + (i18n.modelsLoaded || '')
					);
				})
				.fail(function (xhr) {
					const message =
						xhr.responseJSON && xhr.responseJSON.message
							? xhr.responseJSON.message
							: i18n.fetchFailed || '';
					$status.text(message);
				})
				.always(function () {
					$fetchButton.prop('disabled', false);
				});
		});
	}

	$(function () {
		initBaseUrlToggle();
		initModelField();
	});
})(jQuery);
