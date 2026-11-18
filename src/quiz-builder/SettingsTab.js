import {
	ExternalLink,
	Notice,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { config } from './utils';

export default function SettingsTab({ quiz, onChange }) {
	const set = (patch) => onChange({ ...quiz, ...patch });
	const isSaved = config.postId && config.status !== 'auto-draft';
	// The raw text, so typing a trailing comma or space isn't swallowed.
	const [keywordText, setKeywordText] = useState(() =>
		(quiz.keywords || []).join(', ')
	);

	return (
		<div className="maqsy-qb-settings">
			<div className="maqsy-qb-panel">
				<h3>{__('Introduction', 'maqsy-faq-ai-chat')}</h3>
				<TextareaControl
					__nextHasNoMarginBottom
					label={__('Short description', 'maqsy-faq-ai-chat')}
					help={__(
						'Shown in the chat’s recommendation menu and on suggestion cards. The AI also uses it to decide when to suggest this quiz, so describe who it helps and with what.',
						'maqsy-faq-ai-chat'
					)}
					rows={2}
					value={quiz.description}
					onChange={(description) => set({ description })}
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={__('Opening message', 'maqsy-faq-ai-chat')}
					help={__(
						'What the assistant says before the first question.',
						'maqsy-faq-ai-chat'
					)}
					rows={2}
					value={quiz.intro}
					onChange={(intro) => set({ intro })}
				/>
			</div>

			<div className="maqsy-qb-panel">
				<h3>{__('In the chat', 'maqsy-faq-ai-chat')}</h3>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Start button label', 'maqsy-faq-ai-chat')}
					help={__(
						'The button visitors click to begin, e.g. “Find my plan”. Leave empty for “Let’s start”.',
						'maqsy-faq-ai-chat'
					)}
					maxLength={40}
					value={quiz.start_label}
					onChange={(value) => set({ start_label: value })}
				/>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Chat keywords', 'maqsy-faq-ai-chat')}
					help={__(
						'Comma-separated. When a visitor sends one of these as their whole message (e.g. ACCESS), this quiz starts right away instead of the AI replying. Not case-sensitive, and works even when the quiz isn’t listed in the menu.',
						'maqsy-faq-ai-chat'
					)}
					placeholder={__(
						'e.g. ACCESS, pricing quiz',
						'maqsy-faq-ai-chat'
					)}
					value={keywordText}
					onChange={(value) => {
						setKeywordText(value);
						set({
							keywords: value
								.split(',')
								.map((keyword) => keyword.trim())
								.filter(Boolean),
						});
					}}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={__(
						'List in the chat’s recommendation menu',
						'maqsy-faq-ai-chat'
					)}
					help={__(
						'Visitors can start it themselves from the “Help me choose” button and the welcome screen.',
						'maqsy-faq-ai-chat'
					)}
					checked={quiz.show_in_menu}
					onChange={(value) => set({ show_in_menu: value })}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={__(
						'Let the AI suggest this quiz',
						'maqsy-faq-ai-chat'
					)}
					help={__(
						'When a visitor’s question shows they need a personalized recommendation, the assistant offers a button that starts this quiz.',
						'maqsy-faq-ai-chat'
					)}
					checked={quiz.suggest}
					onChange={(suggest) => set({ suggest })}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={__('Allow restarting', 'maqsy-faq-ai-chat')}
					checked={quiz.allow_restart}
					onChange={(value) => set({ allow_restart: value })}
				/>
			</div>

			<div className="maqsy-qb-panel">
				<h3>{__('Leads', 'maqsy-faq-ai-chat')}</h3>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="email"
					label={__('Email new leads to', 'maqsy-faq-ai-chat')}
					help={__(
						'When a visitor who left contact details finishes the quiz, their answers and result are emailed here. Leave empty to only collect leads in Quiz Analytics.',
						'maqsy-faq-ai-chat'
					)}
					value={quiz.notify_email}
					onChange={(value) => set({ notify_email: value })}
				/>
				<p className="description">
					{__(
						'Add an open-ended question saved as a “Name”, “Email”, or “Phone” lead detail to collect contact information.',
						'maqsy-faq-ai-chat'
					)}
				</p>
			</div>

			<div className="maqsy-qb-panel">
				<h3>{__('Launching the quiz', 'maqsy-faq-ai-chat')}</h3>
				{!config.chatEnabled && (
					<Notice status="warning" isDismissible={false}>
						{__(
							'The chat widget is turned off, so visitors can’t take quizzes yet.',
							'maqsy-faq-ai-chat'
						)}{' '}
						<ExternalLink href={config.settingsUrl}>
							{__('Open AI Chat Settings', 'maqsy-faq-ai-chat')}
						</ExternalLink>
					</Notice>
				)}
				{isSaved ? (
					<>
						<p>
							{__(
								'Add a button anywhere that opens the chat and starts this quiz:',
								'maqsy-faq-ai-chat'
							)}
						</p>
						<p>
							<code className="maqsy-qb-copy">{`[maqsy_quiz id="${config.postId}" label="${quiz.start_label || __('Find what’s right for me', 'maqsy-faq-ai-chat')}"]`}</code>
						</p>
						<p>
							{__(
								'Or link to it from any menu, button, or URL:',
								'maqsy-faq-ai-chat'
							)}
						</p>
						<p>
							<code className="maqsy-qb-copy">{`#maqsy-quiz-${config.postId}`}</code>
						</p>
					</>
				) : (
					<p className="description">
						{__(
							'Save the quiz to get its shortcode and link.',
							'maqsy-faq-ai-chat'
						)}
					</p>
				)}
			</div>
		</div>
	);
}
