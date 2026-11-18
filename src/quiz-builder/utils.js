import { __, sprintf } from '@wordpress/i18n';

export const config = window.maqsyQuizBuilder || {};

/**
 * Generate an ID in the same format the PHP sanitizer generates.
 *
 * @param {string} prefix One-letter prefix: q, a, or o.
 * @return {string} The ID.
 */
export function newId(prefix) {
	return prefix + '_' + Math.random().toString(36).slice(2, 10);
}

export function newOption(label = '') {
	return { id: newId('a'), label, scores: {}, goto: '' };
}

export function newQuestion(type = 'single') {
	return {
		id: newId('q'),
		type,
		text: '',
		help: '',
		required: false,
		lead_field: '',
		placeholder: '',
		options: type === 'text' ? [] : [newOption(), newOption()],
		next: '',
		conditions: { match: 'all', rules: [] },
	};
}

export function newOutcome() {
	return {
		id: newId('o'),
		title: '',
		message: '',
		offer: '',
		recommendations: [],
		cta_label: '',
		cta_url: '',
		redirect_url: '',
		auto_redirect: false,
		conditions: { match: 'all', rules: [] },
	};
}

/**
 * Return a copy of an array with one item moved.
 *
 * @param {Array}  items The array.
 * @param {number} from  Source index.
 * @param {number} to    Destination index.
 * @return {Array} The reordered copy.
 */
export function move(items, from, to) {
	const copy = items.slice();
	const [item] = copy.splice(from, 1);
	copy.splice(to, 0, item);
	return copy;
}

/**
 * A short label for a question, e.g. "Q3: How big is your team?".
 *
 * @param {Object} question The question.
 * @param {number} index    Its index.
 * @return {string} The label.
 */
export function questionLabel(question, index) {
	return sprintf(
		/* translators: 1: question number, 2: question text. */
		__('Q%1$d: %2$s', 'maqsy-faq-ai-chat'),
		index + 1,
		question.text || __('(untitled)', 'maqsy-faq-ai-chat')
	);
}

/**
 * Drop references that an edit has invalidated, mirroring the server-side
 * sanitizer, so the builder never shows (or saves) a dangling branch,
 * condition, or score. Branches may only jump forward; conditions may only
 * reference earlier questions.
 *
 * @param {Object} quiz The quiz definition.
 * @return {Object} The cleaned definition.
 */
export function normalize(quiz) {
	const questionIndex = Object.fromEntries(
		quiz.questions.map((q, i) => [q.id, i])
	);
	const outcomeIds = new Set(quiz.outcomes.map((o) => o.id));
	const validTarget = (target, index) =>
		target === 'end' ||
		(target in questionIndex && questionIndex[target] > index)
			? target
			: '';
	const cleanRules = (conditions, beforeIndex) => ({
		...conditions,
		rules: conditions.rules.filter((rule) => {
			const at = questionIndex[rule.question];
			if (at === undefined || at >= beforeIndex) {
				return false;
			}
			const target = quiz.questions[at];
			return !(
				['is', 'is_not'].includes(rule.operator) &&
				target.type !== 'text' &&
				!target.options.some((o) => o.id === rule.value)
			);
		}),
	});

	return {
		...quiz,
		default_outcome: outcomeIds.has(quiz.default_outcome)
			? quiz.default_outcome
			: '',
		questions: quiz.questions.map((question, index) => ({
			...question,
			next: validTarget(question.next, index),
			conditions: cleanRules(question.conditions, index),
			options: question.options.map((option) => ({
				...option,
				goto: validTarget(option.goto, index),
				scores: Object.fromEntries(
					Object.entries(option.scores || {}).filter(([id]) =>
						outcomeIds.has(id)
					)
				),
			})),
		})),
		outcomes: quiz.outcomes.map((outcome) => ({
			...outcome,
			conditions: cleanRules(outcome.conditions, quiz.questions.length),
		})),
	};
}

/**
 * Human-readable problems that would make the quiz confusing or unusable.
 *
 * @param {Object} quiz The quiz definition.
 * @return {{level: string, message: string}[]} Problems, errors first.
 */
export function validate(quiz) {
	const problems = [];
	const error = (message) => problems.push({ level: 'error', message });
	const warning = (message) => problems.push({ level: 'warning', message });

	if (!quiz.questions.length) {
		error(
			__(
				'Add at least one question. Quizzes without questions are not shown to visitors.',
				'maqsy-faq-ai-chat'
			)
		);
	}
	quiz.questions.forEach((question, index) => {
		const label = sprintf(
			/* translators: %d: question number. */
			__('Question %d', 'maqsy-faq-ai-chat'),
			index + 1
		);
		if (!question.text.trim()) {
			error(
				sprintf(
					/* translators: %s: e.g. "Question 2". */
					__('%s has no question text.', 'maqsy-faq-ai-chat'),
					label
				)
			);
		}
		const filled = question.options.filter((o) => o.label.trim());
		if (question.type !== 'text' && filled.length < 2) {
			error(
				sprintf(
					/* translators: %s: e.g. "Question 2". */
					__(
						'%s needs at least two answer options.',
						'maqsy-faq-ai-chat'
					),
					label
				)
			);
		}
	});

	if (!quiz.outcomes.length) {
		warning(
			__(
				'No outcomes yet: visitors will only see a generic thank-you message when they finish.',
				'maqsy-faq-ai-chat'
			)
		);
	} else {
		quiz.outcomes.forEach((outcome, index) => {
			if (!outcome.title.trim()) {
				error(
					sprintf(
						/* translators: %d: outcome number. */
						__('Outcome %d has no title.', 'maqsy-faq-ai-chat'),
						index + 1
					)
				);
			}
		});
		const hasScores = quiz.questions.some((q) =>
			q.options.some((o) =>
				Object.values(o.scores || {}).some((v) => Number(v) > 0)
			)
		);
		const hasRules = quiz.outcomes.some((o) => o.conditions.rules.length);
		if (quiz.outcomes.length > 1 && !hasScores && !hasRules) {
			warning(
				__(
					'No answer gives points to any outcome and no outcome has conditions, so every visitor gets the same result. Add points to answer options or conditions to outcomes.',
					'maqsy-faq-ai-chat'
				)
			);
		}
	}

	if (
		quiz.notify_email &&
		!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(quiz.notify_email)
	) {
		error(
			__(
				'The lead notification email address is not valid.',
				'maqsy-faq-ai-chat'
			)
		);
	}

	(quiz.keywords || []).forEach((keyword) => {
		const owner = (config.usedKeywords || {})[normalizeKeyword(keyword)];
		if (owner) {
			warning(
				sprintf(
					/* translators: 1: chat keyword, 2: another quiz's title. */
					__(
						'The keyword “%1$s” already starts “%2$s”, so it may not start this quiz.',
						'maqsy-faq-ai-chat'
					),
					keyword,
					owner
				)
			);
		}
	});

	return problems;
}

/**
 * Normalize a chat keyword the way the server matches it (see
 * Maqsy_Quiz_Schema::normalize_keyword).
 *
 * @param {string} keyword Keyword.
 * @return {string} Lower-case, trimmed, without surrounding punctuation.
 */
export function normalizeKeyword(keyword) {
	return keyword
		.toLowerCase()
		.replace(/\s+/gu, ' ')
		.replace(/^[\s\p{P}]+|[\s\p{P}]+$/gu, '');
}
