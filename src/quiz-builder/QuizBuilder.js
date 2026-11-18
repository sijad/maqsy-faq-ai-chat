import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Notice,
	TabPanel,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import GenerateModal from './GenerateModal';
import OutcomeCard from './OutcomeCard';
import PreviewTab from './PreviewTab';
import QuestionCard from './QuestionCard';
import SettingsTab from './SettingsTab';
import {
	config,
	move,
	newId,
	newOutcome,
	newQuestion,
	normalize,
	validate,
} from './utils';

function readTitle() {
	const input = document.getElementById('title');
	return input ? input.value : '';
}

export default function QuizBuilder({ quiz, onChange }) {
	const [expanded, setExpanded] = useState(
		() => new Set(quiz.questions.length ? [] : ['new'])
	);
	const [showGenerate, setShowGenerate] = useState(false);
	const [notice, setNotice] = useState('');
	// A brand-new quiz is empty by definition; hold back problems until the
	// first edit. Saved quizzes show them straight away.
	const [touched, setTouched] = useState(config.status !== 'auto-draft');

	// Every edit goes through normalize(), so deleting or reordering never
	// leaves a dangling branch, condition, or score behind.
	const update = (next) => {
		setTouched(true);
		onChange(normalize(next));
	};
	const toggle = (id) =>
		setExpanded((current) => {
			const next = new Set(current);
			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}
			return next;
		});
	const expand = (id) => setExpanded((current) => new Set(current).add(id));

	const limits = config.limits || {};
	const problems = touched ? validate(quiz) : [];

	const addQuestion = (type) => {
		const question = newQuestion(type);
		update({ ...quiz, questions: [...quiz.questions, question] });
		expand(question.id);
	};

	const setQuestion = (index, question) =>
		update({
			...quiz,
			questions: quiz.questions.map((q, i) =>
				i === index ? question : q
			),
		});

	const duplicateQuestion = (index) => {
		const source = quiz.questions[index];
		const copy = {
			...source,
			id: newId('q'),
			// New option IDs; the copy's own conditions point at earlier questions,
			// which stay earlier, so they carry over unchanged.
			options: source.options.map((o) => ({ ...o, id: newId('a') })),
		};
		const questions = quiz.questions.slice();
		questions.splice(index + 1, 0, copy);
		update({ ...quiz, questions });
		expand(copy.id);
	};

	const deleteQuestion = (index) => {
		const question = quiz.questions[index];
		const referenced =
			quiz.questions.some((q) =>
				q.conditions.rules.some((r) => r.question === question.id)
			) ||
			quiz.outcomes.some((o) =>
				o.conditions.rules.some((r) => r.question === question.id)
			);
		const message = referenced
			? __(
					'Delete this question? Conditions that use its answers will be removed too.',
					'maqsy-faq-ai-chat'
				)
			: __('Delete this question?', 'maqsy-faq-ai-chat');
		// eslint-disable-next-line no-alert
		if ((question.text || referenced) && !window.confirm(message)) {
			return;
		}
		update({
			...quiz,
			questions: quiz.questions.filter((_, i) => i !== index),
		});
	};

	const addOutcome = () => {
		const outcome = newOutcome();
		update({
			...quiz,
			outcomes: [...quiz.outcomes, outcome],
			default_outcome: quiz.default_outcome || outcome.id,
		});
		expand(outcome.id);
	};

	const deleteOutcome = (index) => {
		if (
			quiz.outcomes[index].title &&
			// eslint-disable-next-line no-alert
			!window.confirm(
				__(
					'Delete this outcome? Answer points toward it will be removed.',
					'maqsy-faq-ai-chat'
				)
			)
		) {
			return;
		}
		update({
			...quiz,
			outcomes: quiz.outcomes.filter((_, i) => i !== index),
		});
	};

	const duplicateOutcome = (index) => {
		const source = quiz.outcomes[index];
		const copy = {
			...source,
			id: newId('o'),
			title: source.title
				? sprintf(
						/* translators: %s: outcome title. */
						__('%s (copy)', 'maqsy-faq-ai-chat'),
						source.title
					)
				: '',
		};
		const outcomes = quiz.outcomes.slice();
		outcomes.splice(index + 1, 0, copy);
		// Give the copy the same points as the original so it's a true duplicate.
		const questions = quiz.questions.map((q) => ({
			...q,
			options: q.options.map((o) =>
				o.scores?.[source.id]
					? {
							...o,
							scores: {
								...o.scores,
								[copy.id]: o.scores[source.id],
							},
						}
					: o
			),
		}));
		update({ ...quiz, outcomes, questions });
		expand(copy.id);
	};

	const applyGenerated = ({ quiz: generated, title }) => {
		// Keep how and where the quiz is offered; only the content is generated.
		const {
			start_label: startLabel,
			keywords,
			show_in_menu: showInMenu,
			suggest,
			allow_restart: allowRestart,
			notify_email: notifyEmail,
		} = quiz;
		update({
			...quiz,
			...generated,
			start_label: startLabel,
			keywords,
			show_in_menu: showInMenu,
			suggest,
			allow_restart: allowRestart,
			notify_email: notifyEmail,
		});
		const titleInput = document.getElementById('title');
		if (titleInput && !titleInput.value.trim() && title) {
			titleInput.value = title;
			// Hide WP's "Add title" overlay label, as typing would.
			document
				.getElementById('title-prompt-text')
				?.classList.add('screen-reader-text');
		}
		setExpanded(new Set());
		setShowGenerate(false);
		setNotice(
			__(
				'Draft generated. Review the questions, outcomes and points, try it in Preview, then save.',
				'maqsy-faq-ai-chat'
			)
		);
	};

	const tabs = [
		{
			name: 'questions',
			title: sprintf(
				/* translators: %d: number of questions. */
				__('Questions (%d)', 'maqsy-faq-ai-chat'),
				quiz.questions.length
			),
		},
		{
			name: 'outcomes',
			title: sprintf(
				/* translators: %d: number of outcomes. */
				__('Outcomes (%d)', 'maqsy-faq-ai-chat'),
				quiz.outcomes.length
			),
		},
		{ name: 'settings', title: __('Settings', 'maqsy-faq-ai-chat') },
		{ name: 'preview', title: __('Preview', 'maqsy-faq-ai-chat') },
	];

	const errors = problems.filter((p) => p.level === 'error');
	const warnings = problems.filter((p) => p.level === 'warning');

	return (
		<div className="maqsy-qb">
			<div className="maqsy-qb-toolbar">
				{config.canGenerate && (
					<Button
						variant="secondary"
						icon="superhero-alt"
						onClick={() => setShowGenerate(true)}
					>
						{__('Generate with AI', 'maqsy-faq-ai-chat')}
					</Button>
				)}
				{config.analyticsUrl && config.status !== 'auto-draft' && (
					<Button
						variant="tertiary"
						icon="chart-bar"
						href={config.analyticsUrl}
					>
						{__('View analytics', 'maqsy-faq-ai-chat')}
					</Button>
				)}
			</div>

			{notice && (
				<Notice status="success" onRemove={() => setNotice('')}>
					{notice}
				</Notice>
			)}

			{(errors.length > 0 || warnings.length > 0) && (
				<div className="maqsy-qb-problems" role="status">
					{errors.length > 0 && (
						<Notice status="error" isDismissible={false}>
							<strong>
								{sprintf(
									/* translators: %d: number of problems. */
									_n(
										'%d thing to fix before visitors can use this quiz properly:',
										'%d things to fix before visitors can use this quiz properly:',
										errors.length,
										'maqsy-faq-ai-chat'
									),
									errors.length
								)}
							</strong>
							<ul>
								{errors.map((p, i) => (
									<li key={i}>{p.message}</li>
								))}
							</ul>
						</Notice>
					)}
					{warnings.map((p, i) => (
						<Notice key={i} status="warning" isDismissible={false}>
							{p.message}
						</Notice>
					))}
				</div>
			)}

			<TabPanel className="maqsy-qb-tabs" tabs={tabs}>
				{(tab) => {
					if (tab.name === 'questions') {
						return (
							<div className="maqsy-qb-list">
								{quiz.questions.length === 0 && (
									<div className="maqsy-qb-empty">
										<p>
											{__(
												'Start with a question, or let AI draft the whole quiz from your goals.',
												'maqsy-faq-ai-chat'
											)}
										</p>
									</div>
								)}
								{quiz.questions.map((question, index) => (
									<QuestionCard
										key={question.id}
										question={question}
										index={index}
										quiz={quiz}
										expanded={expanded.has(question.id)}
										onToggle={() => toggle(question.id)}
										onChange={(next) =>
											setQuestion(index, next)
										}
										onMove={(direction) =>
											update({
												...quiz,
												questions: move(
													quiz.questions,
													index,
													index + direction
												),
											})
										}
										onDuplicate={() =>
											duplicateQuestion(index)
										}
										onDelete={() => deleteQuestion(index)}
									/>
								))}
								{quiz.questions.length <
									(limits.questions || 50) && (
									<DropdownMenu
										icon="plus"
										text={__(
											'Add question',
											'maqsy-faq-ai-chat'
										)}
										label={__(
											'Add question',
											'maqsy-faq-ai-chat'
										)}
										toggleProps={{
											variant: 'primary',
											className: 'maqsy-qb-add',
										}}
									>
										{({ onClose }) => (
											<MenuGroup>
												{Object.entries(
													config.questionTypes || {}
												).map(([type, label]) => (
													<MenuItem
														key={type}
														onClick={() => {
															addQuestion(type);
															onClose();
														}}
													>
														{label}
													</MenuItem>
												))}
											</MenuGroup>
										)}
									</DropdownMenu>
								)}
							</div>
						);
					}

					if (tab.name === 'outcomes') {
						return (
							<div className="maqsy-qb-list">
								<p className="description">
									{__(
										'Outcomes are the personalized results visitors get at the end. Each one is picked by rules (checked first, in order), then by the points answers give it, then the fallback.',
										'maqsy-faq-ai-chat'
									)}
								</p>
								{quiz.outcomes.map((outcome, index) => (
									<OutcomeCard
										key={outcome.id}
										outcome={outcome}
										index={index}
										quiz={quiz}
										isDefault={
											quiz.default_outcome === outcome.id
										}
										expanded={expanded.has(outcome.id)}
										onToggle={() => toggle(outcome.id)}
										onChange={(next) =>
											update({
												...quiz,
												outcomes: quiz.outcomes.map(
													(o, i) =>
														i === index ? next : o
												),
											})
										}
										onSetDefault={(checked) =>
											update({
												...quiz,
												default_outcome: checked
													? outcome.id
													: '',
											})
										}
										onMove={(direction) =>
											update({
												...quiz,
												outcomes: move(
													quiz.outcomes,
													index,
													index + direction
												),
											})
										}
										onDuplicate={() =>
											duplicateOutcome(index)
										}
										onDelete={() => deleteOutcome(index)}
									/>
								))}
								{quiz.outcomes.length <
									(limits.outcomes || 20) && (
									<Button
										variant="primary"
										icon="plus"
										className="maqsy-qb-add"
										onClick={addOutcome}
									>
										{__('Add outcome', 'maqsy-faq-ai-chat')}
									</Button>
								)}
							</div>
						);
					}

					if (tab.name === 'settings') {
						return <SettingsTab quiz={quiz} onChange={update} />;
					}

					return <PreviewTab quiz={quiz} title={readTitle()} />;
				}}
			</TabPanel>

			{showGenerate && (
				<GenerateModal
					hasContent={
						quiz.questions.length > 0 || quiz.outcomes.length > 0
					}
					onApply={applyGenerated}
					onClose={() => setShowGenerate(false)}
				/>
			)}
		</div>
	);
}
