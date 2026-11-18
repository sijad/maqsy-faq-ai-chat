import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { BarList, LineChart, formatNumber, formatPercent } from './charts';

const config = window.maqsyQuizAnalytics || {};

// Reference categorical slots 1–2 (blue, orange): validated CVD-safe pair.
const STARTS_COLOR = '#2a78d6';
const COMPLETIONS_COLOR = '#eb6834';

const RANGES = [
	{ value: '7', label: __('Last 7 days', 'maqsy-faq-ai-chat') },
	{ value: '30', label: __('Last 30 days', 'maqsy-faq-ai-chat') },
	{ value: '90', label: __('Last 90 days', 'maqsy-faq-ai-chat') },
	{ value: '365', label: __('Last 12 months', 'maqsy-faq-ai-chat') },
	{ value: '0', label: __('All time', 'maqsy-faq-ai-chat') },
];

const STATUS_LABELS = {
	completed: __('Completed', 'maqsy-faq-ai-chat'),
	in_progress: __('In progress', 'maqsy-faq-ai-chat'),
	dropped: __('Dropped off', 'maqsy-faq-ai-chat'),
	restarted: __('Restarted', 'maqsy-faq-ai-chat'),
};

const SOURCE_LABELS = {
	manual: __('Chat menu', 'maqsy-faq-ai-chat'),
	suggested: __('Suggested by AI', 'maqsy-faq-ai-chat'),
	link: __('Link or button', 'maqsy-faq-ai-chat'),
	keyword: __('Chat keyword', 'maqsy-faq-ai-chat'),
};

function StatTile({ label, value, detail }) {
	return (
		<div className="maqsy-qa-tile">
			<span className="maqsy-qa-tile-label">{label}</span>
			<span className="maqsy-qa-tile-value">{value}</span>
			{detail && <span className="maqsy-qa-tile-detail">{detail}</span>}
		</div>
	);
}

function Section({ title, description, actions, children }) {
	return (
		<section className="maqsy-qa-section">
			<header>
				<div>
					<h2>{title}</h2>
					{description && (
						<p className="description">{description}</p>
					)}
				</div>
				{actions}
			</header>
			{children}
		</section>
	);
}

function Funnel({ funnel, starts }) {
	const [open, setOpen] = useState(null);
	if (!funnel.length) {
		return (
			<p className="description">
				{__('This quiz has no questions.', 'maqsy-faq-ai-chat')}
			</p>
		);
	}
	const max = Math.max(1, starts, ...funnel.map((q) => q.reached));

	return (
		<table className="widefat maqsy-qa-funnel">
			<thead>
				<tr>
					<th>{__('Question', 'maqsy-faq-ai-chat')}</th>
					<th className="maqsy-qa-funnel-barcol">
						{__('Reached', 'maqsy-faq-ai-chat')}
					</th>
					<th className="num">
						{__('Answered', 'maqsy-faq-ai-chat')}
					</th>
					<th className="num">
						{__('Skipped', 'maqsy-faq-ai-chat')}
					</th>
					<th className="num">
						{__('Dropped here', 'maqsy-faq-ai-chat')}
					</th>
				</tr>
			</thead>
			<tbody>
				{funnel.map((question, index) => {
					const dropRate = question.reached
						? question.dropped / question.reached
						: 0;
					const isOpen = open === question.id;
					const hasDetail =
						question.options.length > 0 ||
						question.recent.length > 0;
					return [
						<tr
							key={question.id}
							className={
								dropRate >= 0.25 && question.dropped >= 3
									? 'is-hotspot'
									: ''
							}
						>
							<td>
								{hasDetail ? (
									<button
										type="button"
										className="button-link maqsy-qa-funnel-toggle"
										aria-expanded={isOpen}
										onClick={() =>
											setOpen(isOpen ? null : question.id)
										}
									>
										<span
											className={`dashicons dashicons-arrow-${isOpen ? 'down' : 'right'}-alt2`}
											aria-hidden="true"
										/>
										{index + 1}. {question.text}
									</button>
								) : (
									<span className="maqsy-qa-funnel-text">
										{index + 1}. {question.text}
									</span>
								)}
							</td>
							<td className="maqsy-qa-funnel-barcol">
								<span
									className="maqsy-qa-funnel-bar"
									title={formatNumber(question.reached)}
								>
									<span
										style={{
											width: `${(question.reached / max) * 100}%`,
											background: STARTS_COLOR,
										}}
									/>
								</span>
								<span className="maqsy-qa-funnel-num">
									{formatNumber(question.reached)}
								</span>
							</td>
							<td className="num">
								{formatNumber(question.answered)}
							</td>
							<td className="num">
								{formatNumber(question.skipped)}
							</td>
							<td className="num">
								{formatNumber(question.dropped)}
								{question.dropped > 0 && (
									<small> ({formatPercent(dropRate)})</small>
								)}
							</td>
						</tr>,
						isOpen && (
							<tr
								key={question.id + '-detail'}
								className="maqsy-qa-funnel-detail"
							>
								<td colSpan={5}>
									{question.options.length > 0 && (
										<BarList
											items={question.options.map(
												(o) => ({
													key: o.id,
													label: o.label,
													value: o.count,
												})
											)}
											total={question.answered}
										/>
									)}
									{question.recent.length > 0 && (
										<>
											<p className="description">
												{__(
													'Latest answers',
													'maqsy-faq-ai-chat'
												)}
											</p>
											<ul className="maqsy-qa-recent">
												{question.recent.map(
													(text, i) => (
														<li key={i}>{text}</li>
													)
												)}
											</ul>
										</>
									)}
								</td>
							</tr>
						),
					];
				})}
			</tbody>
		</table>
	);
}

function SessionsTable({ quizId, days, showQuiz }) {
	const [mode, setMode] = useState('leads');
	const [page, setPage] = useState(1);
	const [rows, setRows] = useState(null);
	const [totalPages, setTotalPages] = useState(1);
	const [total, setTotal] = useState(0);
	const [open, setOpen] = useState(null);
	const [error, setError] = useState('');

	useEffect(() => setPage(1), [quizId, days, mode]);

	useEffect(() => {
		let cancelled = false;
		setRows(null);
		setError('');
		apiFetch({
			path: addQueryArgs('/maqsy/v1/quiz/sessions', {
				quiz: quizId,
				days,
				leads: mode === 'leads',
				page,
			}),
			parse: false,
		})
			.then((response) => {
				const pages =
					Number(response.headers.get('X-WP-TotalPages')) || 1;
				const count = Number(response.headers.get('X-WP-Total')) || 0;
				return response
					.json()
					.then(
						(data) =>
							!cancelled &&
							(setRows(data),
							setTotalPages(pages),
							setTotal(count))
					);
			})
			.catch(
				(failure) =>
					!cancelled &&
					setError(
						failure.message ||
							__('Could not load responses.', 'maqsy-faq-ai-chat')
					)
			);
		return () => {
			cancelled = true;
		};
	}, [quizId, days, mode, page]);

	const exportUrl = (leads) =>
		addQueryArgs(config.exportUrl, {
			quiz: quizId,
			days,
			leads: leads ? 1 : 0,
		});

	return (
		<Section
			title={__('Leads & responses', 'maqsy-faq-ai-chat')}
			actions={
				<div className="maqsy-qa-actions">
					<Button
						variant="secondary"
						size="compact"
						icon="download"
						href={exportUrl(mode === 'leads')}
					>
						{mode === 'leads'
							? __('Export leads (CSV)', 'maqsy-faq-ai-chat')
							: __('Export responses (CSV)', 'maqsy-faq-ai-chat')}
					</Button>
				</div>
			}
		>
			<TabPanel
				className="maqsy-qa-subtabs"
				initialTabName="leads"
				onSelect={setMode}
				tabs={[
					{ name: 'leads', title: __('Leads', 'maqsy-faq-ai-chat') },
					{
						name: 'all',
						title: __('All responses', 'maqsy-faq-ai-chat'),
					},
				]}
			>
				{() => (
					<>
						{error && (
							<Notice status="error" isDismissible={false}>
								{error}
							</Notice>
						)}
						{!rows && !error && <Spinner />}
						{rows && rows.length === 0 && (
							<p className="description">
								{mode === 'leads'
									? __(
											'No leads in this period. Add an open-ended question saved as an email, name, or phone lead detail to collect them.',
											'maqsy-faq-ai-chat'
										)
									: __(
											'No responses in this period.',
											'maqsy-faq-ai-chat'
										)}
							</p>
						)}
						{rows && rows.length > 0 && (
							<>
								<table className="widefat striped maqsy-qa-sessions">
									<thead>
										<tr>
											<th>
												{__(
													'Date',
													'maqsy-faq-ai-chat'
												)}
											</th>
											{showQuiz && (
												<th>
													{__(
														'Quiz',
														'maqsy-faq-ai-chat'
													)}
												</th>
											)}
											<th>
												{__(
													'Contact',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th>
												{__(
													'Status',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th>
												{__(
													'Outcome',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th>
												{__('CTA', 'maqsy-faq-ai-chat')}
											</th>
											<th>
												{__(
													'Source',
													'maqsy-faq-ai-chat'
												)}
											</th>
										</tr>
									</thead>
									<tbody>
										{rows.map((row) => [
											<tr key={row.id}>
												<td>
													<button
														type="button"
														className="button-link"
														aria-expanded={
															open === row.id
														}
														onClick={() =>
															setOpen(
																open === row.id
																	? null
																	: row.id
															)
														}
													>
														{new Date(
															row.started_at
														).toLocaleString(
															undefined,
															{
																dateStyle:
																	'medium',
																timeStyle:
																	'short',
															}
														)}
													</button>
												</td>
												{showQuiz && (
													<td>{row.quiz_title}</td>
												)}
												<td>
													{row.name && (
														<div>{row.name}</div>
													)}
													{row.email && (
														<div>
															<a
																href={`mailto:${row.email}`}
															>
																{row.email}
															</a>
														</div>
													)}
													{row.phone && (
														<div>{row.phone}</div>
													)}
													{!row.name &&
														!row.email &&
														!row.phone && (
															<span className="maqsy-qa-muted">
																—
															</span>
														)}
												</td>
												<td>
													<span
														className={`maqsy-qa-status is-${row.status.replace('_', '-')}`}
													>
														{STATUS_LABELS[
															row.status
														] || row.status}
													</span>
												</td>
												<td>
													{row.outcome || (
														<span className="maqsy-qa-muted">
															—
														</span>
													)}
												</td>
												<td>
													{row.cta_clicked ? (
														__(
															'Clicked',
															'maqsy-faq-ai-chat'
														)
													) : (
														<span className="maqsy-qa-muted">
															—
														</span>
													)}
												</td>
												<td>
													{SOURCE_LABELS[
														row.source
													] || row.source}
												</td>
											</tr>,
											open === row.id && (
												<tr key={row.id + '-detail'}>
													<td
														colSpan={
															showQuiz ? 7 : 6
														}
													>
														<dl className="maqsy-qa-answers">
															{row.responses.map(
																(
																	response,
																	i
																) => (
																	<div
																		key={i}
																	>
																		<dt>
																			{
																				response.question
																			}
																		</dt>
																		<dd>
																			{response.skipped ? (
																				<em>
																					{__(
																						'Skipped',
																						'maqsy-faq-ai-chat'
																					)}
																				</em>
																			) : (
																				response.answer
																			)}
																		</dd>
																	</div>
																)
															)}
														</dl>
														{row.page_url && (
															<p className="description">
																{__(
																	'Started on',
																	'maqsy-faq-ai-chat'
																)}
																:{' '}
																<a
																	href={
																		row.page_url
																	}
																>
																	{
																		row.page_url
																	}
																</a>
															</p>
														)}
													</td>
												</tr>
											),
										])}
									</tbody>
								</table>
								<div className="maqsy-qa-pager">
									<span>
										{sprintf(
											/* translators: %s: number of sessions. */
											__('%s total', 'maqsy-faq-ai-chat'),
											formatNumber(total)
										)}
									</span>
									<Button
										size="compact"
										variant="secondary"
										disabled={page <= 1}
										onClick={() => setPage(page - 1)}
									>
										{__('Previous', 'maqsy-faq-ai-chat')}
									</Button>
									<span>
										{sprintf(
											/* translators: 1: current page number, 2: total number of pages. */
											__(
												'Page %1$d of %2$d',
												'maqsy-faq-ai-chat'
											),
											page,
											totalPages
										)}
									</span>
									<Button
										size="compact"
										variant="secondary"
										disabled={page >= totalPages}
										onClick={() => setPage(page + 1)}
									>
										{__('Next', 'maqsy-faq-ai-chat')}
									</Button>
								</div>
							</>
						)}
					</>
				)}
			</TabPanel>
		</Section>
	);
}

export default function Dashboard() {
	const [quizId, setQuizId] = useState(String(config.quiz || 0));
	const [days, setDays] = useState('30');
	const [report, setReport] = useState(null);
	const [error, setError] = useState('');

	useEffect(() => {
		let cancelled = false;
		setError('');
		apiFetch({
			path: addQueryArgs('/maqsy/v1/quiz/analytics', {
				quiz: quizId,
				days,
			}),
		})
			.then((data) => !cancelled && setReport(data))
			.catch(
				(failure) =>
					!cancelled &&
					setError(
						failure.message ||
							__('Could not load analytics.', 'maqsy-faq-ai-chat')
					)
			);

		// Keep the selected quiz in the URL so the view can be bookmarked/shared.
		const url = new URL(window.location.href);
		if (Number(quizId)) {
			url.searchParams.set('quiz', quizId);
		} else {
			url.searchParams.delete('quiz');
		}
		window.history.replaceState(null, '', url);

		return () => {
			cancelled = true;
		};
	}, [quizId, days]);

	const quizzes = config.quizzes || [];
	if (!quizzes.length) {
		return (
			<div className="maqsy-qa-empty">
				<h2>{__('No quizzes yet', 'maqsy-faq-ai-chat')}</h2>
				<p>
					{__(
						'Create a quiz to start engaging visitors in the chat. Starts, completions, drop-offs, leads, and clicks will appear here.',
						'maqsy-faq-ai-chat'
					)}
				</p>
				<Button variant="primary" href={config.newUrl}>
					{__('Create a quiz', 'maqsy-faq-ai-chat')}
				</Button>
			</div>
		);
	}

	const totals = report?.totals;
	const single = Number(quizId) > 0;

	return (
		<div className="maqsy-qa">
			<div className="maqsy-qa-filters">
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Quiz', 'maqsy-faq-ai-chat')}
					value={quizId}
					options={[
						{
							value: '0',
							label: __('All quizzes', 'maqsy-faq-ai-chat'),
						},
						...quizzes.map((q) => ({
							value: String(q.id),
							label:
								q.status === 'publish'
									? q.title
									: `${q.title} (${q.status})`,
						})),
					]}
					onChange={setQuizId}
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={__('Period', 'maqsy-faq-ai-chat')}
					value={days}
					options={RANGES}
					onChange={setDays}
				/>
				{single && (
					<Button
						variant="tertiary"
						icon="edit"
						href={config.editUrl + quizId}
					>
						{__('Edit quiz', 'maqsy-faq-ai-chat')}
					</Button>
				)}
			</div>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}
			{!report && !error && <Spinner />}

			{report && totals && (
				<div className="maqsy-qa-body">
					<div className="maqsy-qa-tiles">
						<StatTile
							label={__('Starts', 'maqsy-faq-ai-chat')}
							value={formatNumber(totals.starts)}
							detail={
								totals.restarts
									? sprintf(
											/* translators: %s: number of restarts. */
											__(
												'+%s restarts',
												'maqsy-faq-ai-chat'
											),
											formatNumber(totals.restarts)
										)
									: null
							}
						/>
						<StatTile
							label={__('Completions', 'maqsy-faq-ai-chat')}
							value={formatNumber(totals.completions)}
							detail={sprintf(
								/* translators: %s: percentage. */
								__('%s completion rate', 'maqsy-faq-ai-chat'),
								formatPercent(totals.completion_rate)
							)}
						/>
						<StatTile
							label={__('Drop-offs', 'maqsy-faq-ai-chat')}
							value={formatNumber(totals.dropoffs)}
							detail={
								totals.in_progress
									? sprintf(
											/* translators: %s: number of sessions. */
											__(
												'%s in progress now',
												'maqsy-faq-ai-chat'
											),
											formatNumber(totals.in_progress)
										)
									: null
							}
						/>
						<StatTile
							label={__('Leads', 'maqsy-faq-ai-chat')}
							value={formatNumber(totals.leads)}
							detail={sprintf(
								/* translators: %s: percentage. */
								__('%s of starts', 'maqsy-faq-ai-chat'),
								formatPercent(totals.lead_rate)
							)}
						/>
						<StatTile
							label={__('CTA clicks', 'maqsy-faq-ai-chat')}
							value={formatNumber(totals.cta_clicks)}
							detail={sprintf(
								/* translators: %s: percentage. */
								__(
									'%s of completions clicked',
									'maqsy-faq-ai-chat'
								),
								formatPercent(totals.cta_rate)
							)}
						/>
					</div>

					<Section
						title={__(
							'Starts and completions per day',
							'maqsy-faq-ai-chat'
						)}
						description={__(
							'Days are in UTC.',
							'maqsy-faq-ai-chat'
						)}
					>
						{totals.starts === 0 ? (
							<p className="description">
								{__(
									'No quiz activity in this period yet.',
									'maqsy-faq-ai-chat'
								)}
							</p>
						) : (
							<LineChart
								data={report.daily}
								label={__(
									'Daily quiz starts and completions',
									'maqsy-faq-ai-chat'
								)}
								series={[
									{
										key: 'starts',
										label: __(
											'Starts',
											'maqsy-faq-ai-chat'
										),
										color: STARTS_COLOR,
									},
									{
										key: 'completions',
										label: __(
											'Completions',
											'maqsy-faq-ai-chat'
										),
										color: COMPLETIONS_COLOR,
									},
								]}
							/>
						)}
					</Section>

					<div className="maqsy-qa-columns">
						{single ? (
							<Section
								title={__('Outcomes', 'maqsy-faq-ai-chat')}
								description={__(
									'Which results visitors got, with CTA clicks.',
									'maqsy-faq-ai-chat'
								)}
							>
								<BarList
									items={report.outcomes.map((o) => ({
										key: o.id || 'none',
										label: o.title,
										value: o.count,
										note: sprintf(
											/* translators: %s: number of clicks. */
											__(
												'%s CTA clicks',
												'maqsy-faq-ai-chat'
											),
											formatNumber(o.cta_clicks)
										),
									}))}
									total={totals.completions}
								/>
							</Section>
						) : (
							<Section title={__('Quizzes', 'maqsy-faq-ai-chat')}>
								<table className="widefat striped">
									<thead>
										<tr>
											<th>
												{__(
													'Quiz',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th className="num">
												{__(
													'Starts',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th className="num">
												{__(
													'Completion',
													'maqsy-faq-ai-chat'
												)}
											</th>
											<th className="num">
												{__(
													'Leads',
													'maqsy-faq-ai-chat'
												)}
											</th>
										</tr>
									</thead>
									<tbody>
										{report.quizzes.map((q) => (
											<tr key={q.id}>
												<td>
													<button
														type="button"
														className="button-link"
														onClick={() =>
															setQuizId(
																String(q.id)
															)
														}
													>
														{q.title ||
															__(
																'(no title)',
																'maqsy-faq-ai-chat'
															)}
													</button>
													{q.status !== 'publish' && (
														<span className="maqsy-qa-muted">
															{' '}
															— {q.status}
														</span>
													)}
												</td>
												<td className="num">
													{formatNumber(q.starts)}
												</td>
												<td className="num">
													{q.starts
														? formatPercent(
																q.completions /
																	q.starts
															)
														: '—'}
												</td>
												<td className="num">
													{formatNumber(q.leads)}
												</td>
											</tr>
										))}
									</tbody>
								</table>
							</Section>
						)}
						<Section
							title={__(
								'How visitors started',
								'maqsy-faq-ai-chat'
							)}
						>
							<BarList
								items={Object.entries(report.sources).map(
									([key, value]) => ({
										key,
										label: SOURCE_LABELS[key] || key,
										value,
									})
								)}
								total={totals.starts}
							/>
						</Section>
					</div>

					{single && (
						<Section
							title={__('Question funnel', 'maqsy-faq-ai-chat')}
							description={__(
								'How many visitors reached each question and where they left. Visitors idle for 30 minutes count as dropped off. Expand a question to see its answers.',
								'maqsy-faq-ai-chat'
							)}
						>
							<Funnel
								funnel={report.funnel}
								starts={totals.starts}
							/>
						</Section>
					)}

					<SessionsTable
						quizId={quizId}
						days={days}
						showQuiz={!single}
					/>
				</div>
			)}
		</div>
	);
}
