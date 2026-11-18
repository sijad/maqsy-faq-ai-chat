import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Modal,
	Notice,
	RangeControl,
	Spinner,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { config } from './utils';

/**
 * Collect the admin's goals and business context, then ask the AI for a draft quiz.
 *
 * @param {Object}   props
 * @param {boolean}  props.hasContent Whether applying will replace existing questions/outcomes.
 * @param {Function} props.onApply    Receives ({ quiz, title }).
 * @param {Function} props.onClose    Closes the modal.
 */
export default function GenerateModal({ hasContent, onApply, onClose }) {
	const [brief, setBrief] = useState({
		goal: '',
		audience: '',
		context: config.businessContext || '',
		questions: 6,
		capture_lead: true,
		use_faqs: true,
	});
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');
	const set = (patch) => setBrief((current) => ({ ...current, ...patch }));

	const generate = () => {
		setBusy(true);
		setError('');
		apiFetch({
			path: '/maqsy/v1/quiz/generate',
			method: 'POST',
			data: brief,
		})
			.then((result) => onApply(result))
			.catch((failure) =>
				setError(
					failure.message ||
						__(
							'Generation failed. Please try again.',
							'maqsy-faq-ai-chat'
						)
				)
			)
			.finally(() => setBusy(false));
	};

	return (
		<Modal
			title={__('Generate a quiz with AI', 'maqsy-faq-ai-chat')}
			onRequestClose={busy ? () => {} : onClose}
			size="medium"
		>
			{!config.aiConfigured ? (
				<Notice status="warning" isDismissible={false}>
					{__(
						'Add an API key first to generate quizzes with AI.',
						'maqsy-faq-ai-chat'
					)}{' '}
					<ExternalLink href={config.settingsUrl}>
						{__('Open AI Chat Settings', 'maqsy-faq-ai-chat')}
					</ExternalLink>
				</Notice>
			) : (
				<div className="maqsy-qb-generate">
					<TextareaControl
						__nextHasNoMarginBottom
						label={__(
							'What should the quiz achieve?',
							'maqsy-faq-ai-chat'
						)}
						placeholder={__(
							'e.g. Help visitors pick the right subscription plan and capture leads for our sales team.',
							'maqsy-faq-ai-chat'
						)}
						rows={2}
						value={brief.goal}
						onChange={(goal) => set({ goal })}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={__('Who will take it?', 'maqsy-faq-ai-chat')}
						placeholder={__(
							'e.g. Small business owners comparing accounting tools.',
							'maqsy-faq-ai-chat'
						)}
						rows={2}
						value={brief.audience}
						onChange={(audience) => set({ audience })}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={__('Business context', 'maqsy-faq-ai-chat')}
						help={__(
							'Your products, services, plans, prices, and links. The AI only uses URLs and offers you mention here.',
							'maqsy-faq-ai-chat'
						)}
						rows={5}
						value={brief.context}
						onChange={(context) => set({ context })}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Number of questions', 'maqsy-faq-ai-chat')}
						min={3}
						max={12}
						value={brief.questions}
						onChange={(questions) =>
							set({ questions: questions || 6 })
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__(
							'Ask for name and email at the end',
							'maqsy-faq-ai-chat'
						)}
						checked={brief.capture_lead}
						onChange={(value) => set({ capture_lead: value })}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__(
							'Use my published FAQs as extra context',
							'maqsy-faq-ai-chat'
						)}
						checked={brief.use_faqs}
						onChange={(value) => set({ use_faqs: value })}
					/>

					{hasContent && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'The generated quiz will replace the current questions and outcomes. Nothing is saved until you click Save or Publish.',
								'maqsy-faq-ai-chat'
							)}
						</Notice>
					)}
					{error && (
						<Notice status="error" isDismissible={false}>
							{error}
						</Notice>
					)}

					<div className="maqsy-qb-generate-actions">
						{busy && (
							<span className="maqsy-qb-generate-status">
								<Spinner />{' '}
								{__(
									'Designing your quiz… this can take up to a minute.',
									'maqsy-faq-ai-chat'
								)}
							</span>
						)}
						<Button
							variant="tertiary"
							onClick={onClose}
							disabled={busy}
						>
							{__('Cancel', 'maqsy-faq-ai-chat')}
						</Button>
						<Button
							variant="primary"
							onClick={generate}
							isBusy={busy}
							disabled={
								busy ||
								(!brief.goal.trim() && !brief.context.trim())
							}
						>
							{__('Generate quiz', 'maqsy-faq-ai-chat')}
						</Button>
					</div>
				</div>
			)}
		</Modal>
	);
}
