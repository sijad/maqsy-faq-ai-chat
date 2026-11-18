import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Run the (unsaved) quiz exactly as visitors would, via the stateless
 * preview endpoint — same engine, no analytics recorded.
 *
 * @param {Object} props
 * @param {Object} props.quiz  The quiz definition being edited.
 * @param {string} props.title The quiz title.
 */
export default function PreviewTab({ quiz, title }) {
	// Each entry: { question (public shape), entry: { question, value, skipped }, label }.
	const [log, setLog] = useState([]);
	const [step, setStep] = useState(null);
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');
	const [selected, setSelected] = useState([]);
	const [text, setText] = useState('');
	const quizRef = useRef(quiz);
	quizRef.current = quiz;

	const run = useCallback(
		(nextLog) => {
			setBusy(true);
			setError('');
			apiFetch({
				path: '/maqsy/v1/quiz/preview',
				method: 'POST',
				data: {
					quiz: quizRef.current,
					title,
					answers: nextLog.map((item) => item.entry),
				},
			})
				.then((response) => {
					// The quiz may have been edited since these answers were given;
					// keep only the ones the engine still accepts on its path.
					setLog(nextLog.slice(0, response.accepted));
					setStep(response);
					setSelected([]);
					setText('');
				})
				.catch((failure) =>
					setError(
						failure.message ||
							__('Preview failed.', 'maqsy-faq-ai-chat')
					)
				)
				.finally(() => setBusy(false));
		},
		[title]
	);

	// Re-run the current path whenever the tab is opened.
	useEffect(() => {
		run(log);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const submit = (entry, label) =>
		run([...log, { question: step.question, entry, label }]);

	const question = step?.question;
	const outcomeTitles = Object.fromEntries(
		quiz.outcomes.map((o, i) => [
			o.id,
			/* translators: %d: outcome number. */
			o.title || sprintf(__('Outcome %d', 'maqsy-faq-ai-chat'), i + 1),
		])
	);

	return (
		<div className="maqsy-qb-preview">
			<div className="maqsy-qb-preview-chat" aria-live="polite">
				{quiz.intro && (
					<div className="maqsy-qb-bubble">{quiz.intro}</div>
				)}
				{log.map((item, i) => (
					<div key={i}>
						<div className="maqsy-qb-bubble">
							{item.question.text}
						</div>
						<div className="maqsy-qb-bubble is-user">
							{item.entry.skipped
								? __('Skipped', 'maqsy-faq-ai-chat')
								: item.label}
						</div>
					</div>
				))}

				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{question && (
					<div className="maqsy-qb-preview-question">
						<div className="maqsy-qb-bubble">
							{question.text || (
								<em>
									{__(
										'(untitled question)',
										'maqsy-faq-ai-chat'
									)}
								</em>
							)}
							{question.help && <small>{question.help}</small>}
						</div>
						<div className="maqsy-qb-preview-controls">
							{question.type === 'single' &&
								question.options.map((option) => (
									<Button
										key={option.id}
										variant="secondary"
										disabled={busy}
										onClick={() =>
											submit(
												{
													question: question.id,
													value: option.id,
													skipped: false,
												},
												option.label
											)
										}
									>
										{option.label}
									</Button>
								))}
							{question.type === 'multiple' && (
								<>
									{question.options.map((option) => (
										<Button
											key={option.id}
											variant={
												selected.includes(option.id)
													? 'primary'
													: 'secondary'
											}
											isPressed={selected.includes(
												option.id
											)}
											disabled={busy}
											onClick={() =>
												setSelected((current) =>
													current.includes(option.id)
														? current.filter(
																(id) =>
																	id !==
																	option.id
															)
														: [
																...current,
																option.id,
															]
												)
											}
										>
											{option.label}
										</Button>
									))}
									<Button
										variant="primary"
										disabled={busy || !selected.length}
										onClick={() =>
											submit(
												{
													question: question.id,
													value: selected,
													skipped: false,
												},
												question.options
													.filter((o) =>
														selected.includes(o.id)
													)
													.map((o) => o.label)
													.join(', ')
											)
										}
									>
										{__('Continue', 'maqsy-faq-ai-chat')}
									</Button>
								</>
							)}
							{question.type === 'text' && (
								<form
									className="maqsy-qb-preview-text"
									onSubmit={(event) => {
										event.preventDefault();
										if (text.trim()) {
											submit(
												{
													question: question.id,
													value: text.trim(),
													skipped: false,
												},
												text.trim()
											);
										}
									}}
								>
									<TextareaControl
										__nextHasNoMarginBottom
										label={__(
											'Answer',
											'maqsy-faq-ai-chat'
										)}
										hideLabelFromVision
										rows={2}
										placeholder={question.placeholder}
										value={text}
										onChange={setText}
									/>
									<Button
										variant="primary"
										type="submit"
										disabled={busy || !text.trim()}
									>
										{__('Send', 'maqsy-faq-ai-chat')}
									</Button>
								</form>
							)}
							{!question.required && (
								<Button
									variant="tertiary"
									disabled={busy}
									onClick={() =>
										submit(
											{
												question: question.id,
												value: null,
												skipped: true,
											},
											''
										)
									}
								>
									{__('Skip', 'maqsy-faq-ai-chat')}
								</Button>
							)}
						</div>
					</div>
				)}

				{step?.result && (
					<div className="maqsy-qb-preview-result">
						<p className="maqsy-qb-preview-eyebrow">
							{__('Result', 'maqsy-faq-ai-chat')}
						</p>
						<h3>{step.result.title}</h3>
						{step.result.message && (
							<p className="maqsy-qb-prewrap">
								{step.result.message}
							</p>
						)}
						{step.result.offer && (
							<p className="maqsy-qb-preview-offer">
								★ {step.result.offer}
							</p>
						)}
						{step.result.recommendations.length > 0 && (
							<ul>
								{step.result.recommendations.map((item, i) => (
									<li key={i}>
										<strong>{item.title}</strong>
										{item.description &&
											` — ${item.description}`}
										{item.url && <code>{item.url}</code>}
									</li>
								))}
							</ul>
						)}
						{step.result.cta && (
							<p>
								<span className="button button-primary">
									{step.result.cta.label}
								</span>{' '}
								<code>{step.result.cta.url}</code>
							</p>
						)}
						{step.result.redirect && (
							<p className="description">
								{step.result.redirect.auto
									? sprintf(
											/* translators: %s: URL. */
											__(
												'Redirects to %s after a countdown.',
												'maqsy-faq-ai-chat'
											),
											step.result.redirect.url
										)
									: sprintf(
											/* translators: %s: URL. */
											__(
												'“Continue” button to %s.',
												'maqsy-faq-ai-chat'
											),
											step.result.redirect.url
										)}
							</p>
						)}
					</div>
				)}

				{busy && <Spinner />}
			</div>

			<aside className="maqsy-qb-preview-side">
				<Button
					variant="secondary"
					icon="image-rotate"
					disabled={busy}
					onClick={() => run([])}
				>
					{__('Restart preview', 'maqsy-faq-ai-chat')}
				</Button>
				{step && (
					<p className="description">
						{sprintf(
							/* translators: 1: answered count, 2: estimated total. */
							__(
								'Answered %1$d of about %2$d questions.',
								'maqsy-faq-ai-chat'
							),
							step.progress.answered,
							step.progress.total
						)}
					</p>
				)}
				<h4>{__('Outcome points so far', 'maqsy-faq-ai-chat')}</h4>
				{quiz.outcomes.length ? (
					<table className="widefat striped maqsy-qb-scoretable">
						<tbody>
							{quiz.outcomes.map((outcome) => (
								<tr
									key={outcome.id}
									className={
										step?.debug?.outcome === outcome.id
											? 'is-winner'
											: ''
									}
								>
									<td>
										{outcomeTitles[outcome.id]}
										{outcome.conditions.rules.length >
											0 && (
											<span className="maqsy-qb-badge is-info">
												{__(
													'Rule-based',
													'maqsy-faq-ai-chat'
												)}
											</span>
										)}
									</td>
									<td className="num">
										{step?.debug?.scores?.[outcome.id] ?? 0}
									</td>
								</tr>
							))}
						</tbody>
					</table>
				) : (
					<p className="description">
						{__('No outcomes yet.', 'maqsy-faq-ai-chat')}
					</p>
				)}
				<p className="description">
					{__(
						'Preview runs are not recorded in analytics.',
						'maqsy-faq-ai-chat'
					)}
				</p>
			</aside>
		</div>
	);
}
