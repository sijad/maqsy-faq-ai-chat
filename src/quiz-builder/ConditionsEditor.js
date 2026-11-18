import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config, questionLabel } from './utils';

const CHOICE_OPERATORS = [
	{ value: 'is', label: __('is', 'maqsy-faq-ai-chat') },
	{ value: 'is_not', label: __('is not', 'maqsy-faq-ai-chat') },
	{ value: 'answered', label: __('was answered', 'maqsy-faq-ai-chat') },
	{ value: 'skipped', label: __('was skipped', 'maqsy-faq-ai-chat') },
];

const TEXT_OPERATORS = [
	{ value: 'contains', label: __('contains', 'maqsy-faq-ai-chat') },
	{
		value: 'not_contains',
		label: __('does not contain', 'maqsy-faq-ai-chat'),
	},
	{ value: 'is', label: __('is exactly', 'maqsy-faq-ai-chat') },
	{ value: 'is_not', label: __('is not', 'maqsy-faq-ai-chat') },
	{ value: 'answered', label: __('was answered', 'maqsy-faq-ai-chat') },
	{ value: 'skipped', label: __('was skipped', 'maqsy-faq-ai-chat') },
];

/**
 * Edit a { match, rules } condition group over a set of earlier questions.
 *
 * @param {Object}   props
 * @param {Object}   props.conditions The condition group.
 * @param {Object[]} props.questions  Questions the rules may reference.
 * @param {Function} props.onChange   Receives the new group.
 * @param {string}   props.emptyText  Text shown when there are no rules.
 */
export default function ConditionsEditor({
	conditions,
	questions,
	onChange,
	emptyText,
}) {
	if (!questions.length) {
		return (
			<p className="description">
				{__(
					'Conditions can only refer to earlier questions. Add questions before this one to use them.',
					'maqsy-faq-ai-chat'
				)}
			</p>
		);
	}

	const setRule = (index, patch) =>
		onChange({
			...conditions,
			rules: conditions.rules.map((rule, i) =>
				i === index ? { ...rule, ...patch } : rule
			),
		});

	const addRule = () => {
		const question = questions[questions.length - 1];
		onChange({
			...conditions,
			rules: [
				...conditions.rules,
				{
					question: question.id,
					operator: question.type === 'text' ? 'contains' : 'is',
					value:
						question.type === 'text'
							? ''
							: question.options[0]?.id || '',
				},
			],
		});
	};

	return (
		<div className="maqsy-qb-conditions">
			{conditions.rules.length === 0 && (
				<p className="description">{emptyText}</p>
			)}

			{conditions.rules.length > 1 && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Match', 'maqsy-faq-ai-chat')}
					value={conditions.match}
					options={[
						{
							value: 'all',
							label: __(
								'All of these rules',
								'maqsy-faq-ai-chat'
							),
						},
						{
							value: 'any',
							label: __(
								'Any of these rules',
								'maqsy-faq-ai-chat'
							),
						},
					]}
					onChange={(match) => onChange({ ...conditions, match })}
				/>
			)}

			{conditions.rules.map((rule, index) => {
				const questionIndex = questions.findIndex(
					(q) => q.id === rule.question
				);
				const question = questions[questionIndex];
				const operators =
					question?.type === 'text'
						? TEXT_OPERATORS
						: CHOICE_OPERATORS;
				const needsValue = !['answered', 'skipped'].includes(
					rule.operator
				);

				return (
					<div className="maqsy-qb-rule" key={index}>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={__('Question', 'maqsy-faq-ai-chat')}
							hideLabelFromVision
							value={rule.question}
							options={questions.map((q) => ({
								value: q.id,
								label: questionLabel(q, questions.indexOf(q)),
							}))}
							onChange={(id) => {
								const next = questions.find((q) => q.id === id);
								setRule(index, {
									question: id,
									operator:
										next.type === 'text'
											? 'contains'
											: 'is',
									value:
										next.type === 'text'
											? ''
											: next.options[0]?.id || '',
								});
							}}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={__('Operator', 'maqsy-faq-ai-chat')}
							hideLabelFromVision
							value={rule.operator}
							options={operators}
							onChange={(operator) =>
								setRule(index, { operator })
							}
						/>
						{needsValue &&
							(question?.type === 'text' ? (
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={__('Value', 'maqsy-faq-ai-chat')}
									hideLabelFromVision
									value={rule.value}
									placeholder={__(
										'Text…',
										'maqsy-faq-ai-chat'
									)}
									onChange={(value) =>
										setRule(index, { value })
									}
								/>
							) : (
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={__('Answer', 'maqsy-faq-ai-chat')}
									hideLabelFromVision
									value={rule.value}
									options={(question?.options || []).map(
										(o) => ({
											value: o.id,
											label:
												o.label ||
												__(
													'(empty option)',
													'maqsy-faq-ai-chat'
												),
										})
									)}
									onChange={(value) =>
										setRule(index, { value })
									}
								/>
							))}
						<Button
							icon="no-alt"
							label={__('Remove rule', 'maqsy-faq-ai-chat')}
							onClick={() =>
								onChange({
									...conditions,
									rules: conditions.rules.filter(
										(_, i) => i !== index
									),
								})
							}
						/>
					</div>
				);
			})}

			{conditions.rules.length < (config.limits?.rules || 10) && (
				<Button
					variant="secondary"
					size="compact"
					icon="plus"
					onClick={addRule}
				>
					{__('Add rule', 'maqsy-faq-ai-chat')}
				</Button>
			)}
		</div>
	);
}
