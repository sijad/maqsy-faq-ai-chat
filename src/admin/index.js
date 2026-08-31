import $ from 'jquery';

import './style.css';

$(function () {
	const $wrap = $('#maqsy');
	const tmpl = document.getElementById('maqsy-question-template');

	if (!$wrap.length || !tmpl) {
		return;
	}

	const $questions = $wrap.find('.maqsy-questions');

	// Textareas whose rich editor has been booted. TinyMCE is expensive, so we
	// only boot an answer's editor the first time the user interacts with it —
	// a FAQ with 20 questions would otherwise spin up 20 iframes on page load.
	const booted = new WeakSet();

	/**
	 * Ensure a textarea has an id (wp.editor keys everything by element id).
	 *
	 * @param {HTMLTextAreaElement} textarea The answer field.
	 * @return {string} The id.
	 */
	function ensureId(textarea) {
		if (!textarea.id) {
			textarea.id =
				'maqsy-answer-' + Math.random().toString(36).slice(2, 10);
		}
		return textarea.id;
	}

	/**
	 * Boot the TinyMCE/Quicktags editor on a textarea, once.
	 *
	 * @param {HTMLTextAreaElement} textarea    The answer field.
	 * @param {boolean}             focusOnInit Move focus into the editor once ready.
	 */
	function bootEditor(textarea, focusOnInit) {
		if (!textarea || booted.has(textarea)) {
			return;
		}
		booted.add(textarea);

		const id = ensureId(textarea);
		window.wp.editor.remove(id);
		window.wp.editor.initialize(id, {
			tinymce: {
				wpautop: true,
				toolbar1:
					'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
				setup(editor) {
					if (focusOnInit) {
						editor.on('init', () => editor.focus());
					}
				},
			},
			quicktags: { buttons: 'strong,em,ul,ol,li,link' },
			mediaButtons: false,
		});
	}

	/**
	 * Tear a booted editor down, syncing its contents back to the textarea first.
	 *
	 * jQuery UI Sortable detaches and re-inserts the row's DOM node; a live
	 * TinyMCE iframe does not survive that, so it must be removed before a drag
	 * and re-booted after.
	 *
	 * @param {HTMLTextAreaElement} textarea The answer field.
	 */
	function unbootEditor(textarea) {
		if (!textarea || !textarea.id || !booted.has(textarea)) {
			return;
		}
		const ed = window.tinymce && window.tinymce.get(textarea.id);
		if (ed) {
			ed.save();
		}
		window.wp.editor.remove(textarea.id);
		booted.delete(textarea);
	}

	// Lazy boot: mousedown fires before focus, so the editor is already mounting
	// by the time the click lands; focusin covers keyboard tab-in.
	$questions.on('mousedown focusin', 'textarea.maqsy-a', function () {
		bootEditor(this, true);
	});

	// Make sure any open editor's content is flushed to its textarea on save.
	$wrap.closest('form').on('submit', function () {
		if (window.tinymce) {
			window.tinymce.triggerSave();
		}
	});

	$wrap.on('click', '#maqsy-add-question', function () {
		const frag = tmpl.content.cloneNode(true);
		const row = frag.querySelector('.maqsy-question');
		$questions.append(row);
		row.querySelector('.maqsy-q').focus();
		row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	});

	$questions.on('click', '.maqsy-delete', function () {
		const row = $(this).closest('.maqsy-question')[0];
		const textarea = row.querySelector('textarea.maqsy-a');
		const hasContent =
			row.querySelector('.maqsy-q').value.trim() ||
			$(textarea).val().trim();

		// eslint-disable-next-line no-alert
		if (hasContent && !window.confirm('Delete this question?')) {
			return;
		}

		// Never leave the list empty — the last row is cleared instead.
		if ($questions.children('.maqsy-question').length === 1) {
			row.querySelector('.maqsy-q').value = '';
			const ed = window.tinymce && window.tinymce.get(textarea.id);
			if (ed) {
				ed.setContent('');
			} else {
				textarea.value = '';
			}
			return;
		}

		$(row).slideUp(150, function () {
			unbootEditor(textarea);
			row.remove();
		});
	});

	$questions.sortable({
		handle: '.maqsy-handle',
		items: '> .maqsy-question',
		axis: 'y',
		cursor: 'grabbing',
		opacity: 0.85,
		tolerance: 'pointer',
		forcePlaceholderSize: true,
		placeholder: 'maqsy-question-placeholder',
		start(e, ui) {
			const textarea = ui.item.find('textarea.maqsy-a')[0];
			if (booted.has(textarea)) {
				unbootEditor(textarea);
				ui.item.data('maqsyReboot', true);
			}
			ui.placeholder.height(ui.item.outerHeight());
		},
		stop(e, ui) {
			if (ui.item.data('maqsyReboot')) {
				ui.item.removeData('maqsyReboot');
				bootEditor(ui.item.find('textarea.maqsy-a')[0], false);
			}
		},
	});
});
