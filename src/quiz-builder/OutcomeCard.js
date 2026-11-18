import {
	Button,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import ConditionsEditor from './ConditionsEditor';
import { config, questionLabel } from './utils';

export default function OutcomeCard({
	outcome,
	index,
	quiz,
	isDefault,
	expanded,
	onToggle,
	onChange,
	onSetDefault,
	onMove,
	onDuplicate,
	onDelete,
}) {
	const set = (patch) => onChange({ ...outcome, ...patch });
	const setRecommendation = (i, patch) =>
		set({
			recommendations: outcome.recommendations.map((r, j) =>
				j === i ? { ...r, ...patch } : r
			),
		});
	const panelId = `maqsy-qb-outcome-${outcome.id}`;
	const messageRef = useRef();
	// Where the cursor was when the message field lost focus; null until it has had focus.
	const selection = useRef(null);

	// Insert a placeholder at the cursor (or at the end), then put the cursor after it.
	const insertToken = (token) => {
		const message = outcome.message || '';
		const [start, end] = selection.current || [
			message.length,
			message.length,
		];
		const before = message.slice(0, start);
		const pad = before && !/\s$/.test(before) ? ' ' : '';
		const cursor = (before + pad + token).length;
		set({ message: before + pad + token + message.slice(end) });
		selection.current = [cursor, cursor];
		window.requestAnimationFrame(() => {
			messageRef.current?.focus();
			messageRef.current?.setSelectionRange(cursor, cursor);
		});
	};

	const tokens = [
		...(quiz.questions.some((q) => q.lead_field === 'name')
			? [
					{
						value: '{name}',
						label: __('Visitor’s name', 'maqsy-faq-ai-chat'),
					},
				]
			: []),
		...quiz.questions.map((q, i) => ({
			value: `{answer:${q.id}}`,
			label: questionLabel(q, i),
		})),
	];

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
					<span className="maqsy-qb-card-number is-outcome">
						{index + 1}
					</span>
					<span className="maqsy-qb-card-title">
						{outcome.title || (
							<em>
								{__('Untitled outcome', 'maqsy-faq-ai-chat')}
							</em>
						)}
					</span>
					<span className="maqsy-qb-badges">
						{isDefault && (
							<span className="maqsy-qb-badge is-success">
								{__('Fallback', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{outcome.conditions.rules.length > 0 && (
							<span className="maqsy-qb-badge is-info">
								{__('Rule-based', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{outcome.cta_url && (
							<span className="maqsy-qb-badge">
								{__('CTA', 'maqsy-faq-ai-chat')}
							</span>
						)}
						{outcome.redirect_url && (
							<span className="maqsy-qb-badge">
								{__('Redirect', 'maqsy-faq-ai-chat')}
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
						disabled={index === quiz.outcomes.length - 1}
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
						label={__('Delete outcome', 'maqsy-faq-ai-chat')}
						onClick={onDelete}
					/>
				</div>
			</div>

			{expanded && (
				<div className="maqsy-qb-card-body" id={panelId}>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Result title', 'maqsy-faq-ai-chat')}
						help={__(
							'e.g. the recommended product, plan, or service.',
							'maqsy-faq-ai-chat'
						)}
						value={outcome.title}
						maxLength={200}
						onChange={(title) => set({ title })}
					/>

					<TextareaControl
						__nextHasNoMarginBottom
						ref={messageRef}
						onBlur={(event) => {
							selection.current = [
								event.target.selectionStart,
								event.target.selectionEnd,
							];
						}}
						label={__('Personalized message', 'maqsy-faq-ai-chat')}
						help={__(
							'Supports **bold**, *italic* and [links](https://…).',
							'maqsy-faq-ai-chat'
						)}
						rows={4}
						value={outcome.message}
						onChange={(message) => set({ message })}
					/>
					{tokens.length > 0 && (
						<div
							className="maqsy-qb-personalize"
							role="group"
							aria-labelledby={`${panelId}-personalize`}
						>
							<p id={`${panelId}-personalize`}>
								<strong>
									{__(
										'Personalize with the visitor’s answers',
										'maqsy-faq-ai-chat'
									)}
								</strong>
								<br />
								<span className="description">
									{__(
										'Click to add one to the message at the cursor. Visitors see what they answered, e.g. “Thanks, Grace!”',
										'maqsy-faq-ai-chat'
									)}
								</span>
							</p>
							<div className="maqsy-qb-personalize-chips">
								{tokens.map((token) => (
									<Button
										key={token.value}
										variant="secondary"
										size="small"
										icon="plus-alt2"
										label={sprintf(
											/* translators: %s: the answer, e.g. "Visitor’s name". */
											__(
												'Insert %s',
												'maqsy-faq-ai-chat'
											),
											token.label
										)}
										showTooltip={false}
										onClick={() => insertToken(token.value)}
									>
										{token.label.length > 40
											? token.label.slice(0, 39) + '…'
											: token.label}
									</Button>
								))}
							</div>
						</div>
					)}

					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Offer (optional)', 'maqsy-faq-ai-chat')}
						help={__(
							'Highlighted, e.g. “20% off your first month with code WELCOME”.',
							'maqsy-faq-ai-chat'
						)}
						value={outcome.offer}
						onChange={(offer) => set({ offer })}
					/>

					<fieldset className="maqsy-qb-section">
						<legend>
							{__('Recommendations', 'maqsy-faq-ai-chat')}
						</legend>
						{outcome.recommendations.map((item, i) => (
							<div className="maqsy-qb-recommendation" key={i}>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={__('Title', 'maqsy-faq-ai-chat')}
									value={item.title}
									onChange={(title) =>
										setRecommendation(i, { title })
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									type="url"
									label={__('Link', 'maqsy-faq-ai-chat')}
									placeholder="https://… or /page"
									value={item.url}
									onChange={(url) =>
										setRecommendation(i, { url })
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={__(
										'Short description',
										'maqsy-faq-ai-chat'
									)}
									value={item.description}
									onChange={(description) =>
										setRecommendation(i, { description })
									}
								/>
								<Button
									icon="no-alt"
									label={__(
										'Remove recommendation',
										'maqsy-faq-ai-chat'
									)}
									onClick={() =>
										set({
											recommendations:
												outcome.recommendations.filter(
													(_, j) => j !== i
												),
										})
									}
								/>
							</div>
						))}
						{outcome.recommendations.length <
							(config.limits?.recommendations || 10) && (
							<Button
								variant="secondary"
								size="compact"
								icon="plus"
								onClick={() =>
									set({
										recommendations: [
											...outcome.recommendations,
											{
												title: '',
												url: '',
												description: '',
											},
										],
									})
								}
							>
								{__('Add recommendation', 'maqsy-faq-ai-chat')}
							</Button>
						)}
					</fieldset>

					<fieldset className="maqsy-qb-section">
						<legend>
							{__('Call to action', 'maqsy-faq-ai-chat')}
						</legend>
						<div className="maqsy-qb-grid">
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={__('Button label', 'maqsy-faq-ai-chat')}
								placeholder={__(
									'Learn more',
									'maqsy-faq-ai-chat'
								)}
								value={outcome.cta_label}
								onChange={(value) => set({ cta_label: value })}
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								type="url"
								label={__('Button link', 'maqsy-faq-ai-chat')}
								placeholder="https://… or /page"
								value={outcome.cta_url}
								onChange={(value) => set({ cta_url: value })}
							/>
						</div>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="url"
							label={__(
								'Landing page (optional)',
								'maqsy-faq-ai-chat'
							)}
							help={__(
								'Shown as a “Continue” button, or opened automatically after a short countdown.',
								'maqsy-faq-ai-chat'
							)}
							placeholder="https://… or /page"
							value={outcome.redirect_url}
							onChange={(value) => set({ redirect_url: value })}
						/>
						{outcome.redirect_url && (
							<ToggleControl
								__nextHasNoMarginBottom
								label={__(
									'Redirect automatically',
									'maqsy-faq-ai-chat'
								)}
								help={__(
									'Visitors see a 5-second countdown they can cancel.',
									'maqsy-faq-ai-chat'
								)}
								checked={outcome.auto_redirect}
								onChange={(value) =>
									set({ auto_redirect: value })
								}
							/>
						)}
					</fieldset>

					<fieldset className="maqsy-qb-section">
						<legend>
							{__(
								'When to show this outcome',
								'maqsy-faq-ai-chat'
							)}
						</legend>
						<ConditionsEditor
							conditions={outcome.conditions}
							questions={quiz.questions}
							emptyText={__(
								'Chosen by points. Add rules to show this outcome whenever specific answers match — rule-based outcomes are checked first, in the order listed.',
								'maqsy-faq-ai-chat'
							)}
							onChange={(conditions) => set({ conditions })}
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__(
								'Use as the fallback outcome',
								'maqsy-faq-ai-chat'
							)}
							help={__(
								'Shown when no rule matches and no outcome earned any points.',
								'maqsy-faq-ai-chat'
							)}
							checked={isDefault}
							onChange={(checked) => onSetDefault(checked)}
						/>
					</fieldset>

					{/* translators: %s: outcome ID. */}
					<p className="maqsy-qb-token-hint">
						{sprintf(
							/* translators: %s: outcome ID. */
							__('Outcome ID: %s', 'maqsy-faq-ai-chat'),
							outcome.id
						)}
					</p>
				</div>
			)}
		</div>
	);
}
