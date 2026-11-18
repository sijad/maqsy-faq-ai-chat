import { createRoot, useEffect, useState } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import QuizBuilder from './QuizBuilder';
import './style.css';

/**
 * Keeps the hidden `maqsy_quiz_data` input (saved by Maqsy_Quiz_Admin::save_post())
 * in sync with the builder, and warns before leaving with unsaved edits.
 *
 * @param {Object}           props
 * @param {HTMLInputElement} props.input The hidden input.
 */
function App({ input }) {
	const [quiz, setQuiz] = useState(() => JSON.parse(input.value));
	const [dirty, setDirty] = useState(false);

	useEffect(() => {
		input.value = JSON.stringify(quiz);
	}, [input, quiz]);

	useEffect(() => {
		if (!dirty) {
			return;
		}
		const form = input.form;
		const onSubmit = () =>
			window.removeEventListener('beforeunload', onBeforeUnload);
		function onBeforeUnload(event) {
			event.preventDefault();
			event.returnValue = '';
		}
		window.addEventListener('beforeunload', onBeforeUnload);
		form?.addEventListener('submit', onSubmit);
		return () => {
			window.removeEventListener('beforeunload', onBeforeUnload);
			form?.removeEventListener('submit', onSubmit);
		};
	}, [dirty, input]);

	return (
		<QuizBuilder
			quiz={quiz}
			onChange={(next) => {
				setQuiz(next);
				setDirty(true);
			}}
		/>
	);
}

domReady(() => {
	const mount = document.getElementById('maqsy-quiz-builder');
	const input = document.getElementById('maqsy-quiz-data');

	if (!mount || !input) {
		return;
	}

	// Enter in a builder text field must not submit (and save) the post form.
	mount.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
			event.preventDefault();
		}
	});

	createRoot(mount).render(<App input={input} />);
});
