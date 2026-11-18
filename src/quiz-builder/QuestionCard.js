import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import ConditionsEditor from './ConditionsEditor';
import { config, newOption, questionLabel } from './utils';

/**
 * Branch target choices for a question at `index`: continue, any later question, or end.
 *
 * @param {Object[]} questions    All questions.
 * @param {number}   index        The current question's index.
 * @param {string}   defaultLabel Label for the "no override" choice.
 * @return {Object[]} SelectControl options.
 */
function targetOptions(questions, index, defaultLabel) {
	return [
		{ value: '', label: defaultLabel },
		...questions.slice(index + 1).map((q, offset) => ({
			value: q.id,
			label: sprintf(
				/* translators: %s: question label, e.g. "Q4: What is your budget?". */
				__('Jump to %s', 'maqsy-faq-ai-chat'),
				questionLabel(q, index + 1 + offset)
			),
		})),
		{
			value: 'end',
			label: __('End the quiz (show result)', 'maqsy-faq-ai-chat'),
		},
	];
}

/**
 * Answer options to keep when switching a question to `type`.
 *
 * @param {string}   type    The new question type.
 * @param {Object[]} options The current options.
 * @return {Object[]} Options for the new type.
 */
function optionsForType(type, options) {
	if (type === 'text') {
		return [];
	}
	return options.length ? options : [newOption(), newOption()];
}

export default function QuestionCard({
	question,
	index,
	quiz,
	expanded,
	onToggle,
	onChange,
	onMove,
	onDuplicate,
	onDelete,
}) {
	const total = quiz.questions.length;
	const set = (patch) => onChange({ ...question, ...patch });
	const setOption = (optionIndex, patch) =>
		set({
			options: question.options.map((o, i) =>
				i === optionIndex ? { ...o, ...patch } : o
			),
		});
	const isChoice = question.type !== 'text';
	const hasConditions = question.conditions.rules.length > 0;
	const hasBranches =
		question.next !== '' || question.options.some((o) => o.goto);
	const typeLabels = config.questionTypes || {};
	const panelId = `maqsy-qb-question-${question.id}`;

	return (
		<div className={`maqsy-qb-card${expanded ? ' is-expanded' : ''}`}>
			<div className="maqsy-qb-card-header">
				<button
					type="button"
					className="maqsy-qb-card-toggle"
					aria-expanded={expanded}
					aria-controls={panelId}
					onClick={onToggle}
				>
					<span className="maqsy-qb-card-number">{index + 1}</span>
					<span className="maqsy-qb-card-title">
						{question.text || (
							<em>
								{__('Untitled question', 'maqsy-faq-ai-chat')}
							</em>
						)}
					</span>
					<span className="maqsy-qb-badges">
						<span className="maqsy-qb-badge">
							{typeLabels[question.type] || question.type}
						</span>
						{question.required && (
							<span className="maqsy-qb-badge">
								{__('Required', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{hasConditions && (
							<span className="maqsy-qb-badge is-info">
								{__('Conditional', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{hasBranches && (
							<span className="maqsy-qb-badge is-info">
								{__('Branches', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{question.lead_field && (
							<span className="maqsy-qb-badge is-success">
								{__('Lead', 'maqsy-faq-ai-chat')}
							</span>
						)}
					</span>
				</button>
				<div className="maqsy-qb-card-actions">
					<Button
						size="small"
						icon="arrow-up-alt2"
						label={__('Move up', 'maqsy-faq-ai-chat')}
						disabled={index === 0}
						onClick={() => onMove(-1)}
					/>
					<Button
						size="small"
						icon="arrow-down-alt2"
						label={__('Move down', 'maqsy-faq-ai-chat')}
						disabled={index === total - 1}
						onClick={() => onMove(1)}
					/>
					<Button
						size="small"
						icon="admin-page"
						label={__('Duplicate', 'maqsy-faq-ai-chat')}
						onClick={onDuplicate}
					/>
					<Button
						size="small"
						icon="trash"
						isDestructive
						label={__('Delete question', 'maqsy-faq-ai-chat')}
						onClick={onDelete}
					/>
				</div>
			</div>

			{expanded && (
				<div className="maqsy-qb-card-body" id={panelId}>
					<div className="maqsy-qb-grid">
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={__('Question', 'maqsy-faq-ai-chat')}
							value={question.text}
							maxLength={500}
							onChange={(text) => set({ text })}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={__('Type', 'maqsy-faq-ai-chat')}
							value={question.type}
							options={Object.entries(typeLabels).map(
								([value, label]) => ({ value, label })
							)}
							onChange={(type) =>
								set({
									type,
									options: optionsForType(
										type,
										question.options
									),
									lead_field:
										type === 'text'
											? question.lead_field
											: '',
								})
							}
						/>
					</div>

					<TextareaControl
						__nextHasNoMarginBottom
						label={__('Hint (optional)', 'maqsy-faq-ai-chat')}
						help={__(
							'Shown under the question in a smaller font.',
							'maqsy-faq-ai-chat'
						)}
						rows={2}
						value={question.help}
						onChange={(help) => set({ help })}
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Required', 'maqsy-faq-ai-chat')}
						help={
							question.required
								? __(
										'Visitors must answer to continue.',
										'maqsy-faq-ai-chat'
									)
								: __(
										'Visitors can skip this question.',
										'maqsy-faq-ai-chat'
									)
						}
						checked={question.required}
						onChange={(required) => set({ required })}
					/>

					{!isChoice && (
						<div className="maqsy-qb-grid">
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={__(
									'Save answer as lead contact detail',
									'maqsy-faq-ai-chat'
								)}
								help={__(
									'Email and phone answers are validated.',
									'maqsy-faq-ai-chat'
								)}
								value={question.lead_field}
								options={Object.entries(
									config.leadFields || {}
								).map(([value, label]) => ({ value, label }))}
								onChange={(value) => set({ lead_field: value })}
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={__(
									'Placeholder (optional)',
									'maqsy-faq-ai-chat'
								)}
								value={question.placeholder}
								onChange={(placeholder) => set({ placeholder })}
							/>
						</div>
					)}

					{isChoice && (
						<fieldset className="maqsy-qb-section">
							<legend>
								{__('Answer options', 'maqsy-faq-ai-chat')}
							</legend>
							{quiz.outcomes.length > 0 && (
								<p className="description">
									{__(
										'Give each option points toward the outcomes it suggests. The outcome with the most points wins (unless an outcome’s own conditions match first).',
										'maqsy-faq-ai-chat'
									)}
								</p>
							)}
							{question.options.map((option, optionIndex) => (
								<div
									className="maqsy-qb-option"
									key={option.id}
								>
									<div className="maqsy-qb-option-main">
										<TextControl
											__nextHasNoMarginBottom
											__next40pxDefaultSize
											label={sprintf(
												/* translators: %d: option number. */
												__(
													'Option %d',
													'maqsy-faq-ai-chat'
												),
												optionIndex + 1
											)}
											hideLabelFromVision
											placeholder={sprintf(
												/* translators: %d: option number. */
												__(
													'Option %d',
													'maqsy-faq-ai-chat'
												),
												optionIndex + 1
											)}
											value={option.label}
											maxLength={200}
											onChange={(label) =>
												setOption(optionIndex, {
													label,
												})
											}
										/>
										<SelectControl
											__nextHasNoMarginBottom
											__next40pxDefaultSize
											label={__(
												'When chosen',
												'maqsy-faq-ai-chat'
											)}
											hideLabelFromVision
											value={option.goto}
											options={targetOptions(
												quiz.questions,
												index,
												__(
													'Then: follow the question’s path',
													'maqsy-faq-ai-chat'
												)
											)}
											onChange={(goto) =>
												setOption(optionIndex, { goto })
											}
										/>
										<Button
											icon="no-alt"
											label={__(
												'Remove option',
												'maqsy-faq-ai-chat'
											)}
											disabled={
												question.options.length <= 1
											}
											onClick={() =>
												set({
													options:
														question.options.filter(
															(_, i) =>
																i !==
																optionIndex
														),
												})
											}
										/>
									</div>
									{quiz.outcomes.length > 0 && (
										<div className="maqsy-qb-scores">
											{quiz.outcomes.map(
												(outcome, outcomeIndex) => (
													<label
														className="maqsy-qb-score"
														key={outcome.id}
														htmlFor={`maqsy-qb-score-${option.id}-${outcome.id}`}
													>
														<span>
															{outcome.title ||
																sprintf(
																	/* translators: %d: outcome number. */
																	__(
																		'Outcome %d',
																		'maqsy-faq-ai-chat'
																	),
																	outcomeIndex +
																		1
																)}
														</span>
														<input
															id={`maqsy-qb-score-${option.id}-${outcome.id}`}
															type="number"
															min="-100"
															max="100"
															step="1"
															value={
																option.scores?.[
																	outcome.id
																] ?? ''
															}
															placeholder="0"
															onChange={(
																event
															) => {
																const scores = {
																	...(option.scores ||
																		{}),
																};
																const value =
																	parseInt(
																		event
																			.target
																			.value,
																		10
																	);
																if (
																	Number.isNaN(
																		value
																	) ||
																	value === 0
																) {
																	delete scores[
																		outcome
																			.id
																	];
																} else {
																	scores[
																		outcome.id
																	] =
																		Math.max(
																			-100,
																			Math.min(
																				100,
																				value
																			)
																		);
																}
																setOption(
																	optionIndex,
																	{ scores }
																);
															}}
														/>
													</label>
												)
											)}
										</div>
									)}
								</div>
							))}
							{question.options.length <
								(config.limits?.options || 20) && (
								<Button
									variant="secondary"
									size="compact"
									icon="plus"
									onClick={() =>
										set({
											options: [
												...question.options,
												newOption(),
											],
										})
									}
								>
									{__('Add option', 'maqsy-faq-ai-chat')}
								</Button>
							)}
						</fieldset>
					)}

					<fieldset className="maqsy-qb-section">
						<legend>{__('Branching', 'maqsy-faq-ai-chat')}</legend>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={__(
								'After this question',
								'maqsy-faq-ai-chat'
							)}
							help={
								isChoice
									? __(
											'Options with their own jump override this.',
											'maqsy-faq-ai-chat'
										)
									: undefined
							}
							value={question.next}
							options={targetOptions(
								quiz.questions,
								index,
								__(
									'Continue to the next question',
									'maqsy-faq-ai-chat'
								)
							)}
							onChange={(next) => set({ next })}
						/>
					</fieldset>

					<fieldset className="maqsy-qb-section">
						<legend>
							{__('Conditional display', 'maqsy-faq-ai-chat')}
						</legend>
						<ConditionsEditor
							conditions={question.conditions}
							questions={quiz.questions.slice(0, index)}
							emptyText={__(
								'Always asked. Add a rule to ask this question only when earlier answers match.',
								'maqsy-faq-ai-chat'
							)}
							onChange={(conditions) => set({ conditions })}
						/>
					</fieldset>

					<p className="maqsy-qb-token-hint">
						{__(
							'To repeat this answer in a personalized result message, use',
							'maqsy-faq-ai-chat'
						)}{' '}
						<code>{`{answer:${question.id}}`}</code>
					</p>
				</div>
			)}
		</div>
	);
}
