import { useEffect, useRef, useState } from "react";
import type { ReactNode } from "react";

import { Button } from "../ui/button";
import { renderSimpleMarkdown } from "../lib/markdown";
import { isExternal, safeUrl, sprintf } from "../lib/quiz";
import type { QuizResultMessage, QuizStrings, QuizSummary } from "../types";
import type { QuizController } from "../useQuiz";

const REDIRECT_SECONDS = 5;

/** Only a result shown moments ago may auto-redirect — never one restored from history. */
const FRESH_RESULT_MS = 15000;

const primaryLink =
	"inline-flex w-full items-center justify-center rounded-md bg-[var(--maqsy-primary,#2563eb)] px-3 py-2 text-sm font-medium text-white no-underline outline-none hover:brightness-90 focus-visible:ring-2 focus-visible:ring-blue-500";
const secondaryLink =
	"inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-800 no-underline outline-none hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700";

interface TrackedLinkProps {
	href: string;
	className: string;
	onTrack: (url: string) => void;
	children: ReactNode;
}

/** A link that records the click before the browser navigates away. */
function TrackedLink({ href, className, onTrack, children }: TrackedLinkProps) {
	const url = safeUrl(href);
	if (!url) {
		return null;
	}
	const external = isExternal(url);
	return (
		<a
			href={url.href}
			className={className}
			target={external ? "_blank" : undefined}
			rel={external ? "noopener noreferrer" : undefined}
			onClick={() => onTrack(url.href)}
		>
			{children}
		</a>
	);
}

interface QuizResultCardProps {
	result: QuizResultMessage;
	quiz: QuizController;
	strings: QuizStrings;
}

export function QuizResultCard({ result, quiz, strings }: QuizResultCardProps) {
	const redirectUrl = result.redirect ? safeUrl(result.redirect.url) : null;
	const redirectHref = redirectUrl ? redirectUrl.href : "";
	const [countdown, setCountdown] = useState<number | null>(() =>
		redirectUrl && result.redirect?.auto && Date.now() - result.createdAt < FRESH_RESULT_MS ? REDIRECT_SECONDS : null,
	);

	// Through a ref so parent re-renders (e.g. typing) don't reset the timer.
	const trackRef = useRef(quiz.trackClick);
	trackRef.current = quiz.trackClick;

	useEffect(() => {
		if (countdown === null || !redirectHref) {
			return;
		}
		if (countdown <= 0) {
			trackRef.current(result.token, redirectHref, "redirect");
			window.location.assign(redirectHref);
			return;
		}
		const id = window.setTimeout(() => setCountdown(countdown - 1), 1000);
		return () => window.clearTimeout(id);
	}, [countdown, redirectHref, result.token]);

	const track = (kind: "cta" | "recommendation" | "redirect") => (url: string) => quiz.trackClick(result.token, url, kind);

	return (
		<div className="w-full overflow-hidden rounded-xl border border-gray-200 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
			<div className="border-b border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
				<p className="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
					{strings.resultLabel} · {result.quizTitle}
				</p>
				<h3 dir="auto" className="mt-0.5 text-base font-semibold text-gray-900 dark:text-gray-50">
					{result.title}
				</h3>
			</div>

			<div className="flex flex-col gap-3 p-3">
				{result.message && (
					<div
						dir="auto"
						className="whitespace-pre-wrap text-gray-800 dark:text-gray-200"
						dangerouslySetInnerHTML={{ __html: renderSimpleMarkdown(result.message) }}
					/>
				)}
				{result.offer && (
					<div dir="auto" className="rounded-lg bg-amber-50 px-3 py-2 font-medium text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
						<span aria-hidden="true">★ </span>
						{result.offer}
					</div>
				)}

				{result.recommendations.length > 0 && (
					<div>
						<p className="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{strings.recommended}</p>
						<ul className="flex flex-col gap-1.5">
							{result.recommendations.map((item, index) => {
								const body = (
									<>
										<span dir="auto" className="block font-medium text-gray-900 dark:text-gray-100">
											{item.title}
											{item.url ? <span aria-hidden="true"> →</span> : null}
										</span>
										{item.description && (
											<span dir="auto" className="block text-xs text-gray-600 dark:text-gray-400">
												{item.description}
											</span>
										)}
									</>
								);
								const boxClass =
									"block rounded-lg border border-gray-200 px-2.5 py-2 no-underline dark:border-gray-700";
								return (
									<li key={index}>
										{item.url && safeUrl(item.url) ? (
											<TrackedLink href={item.url} onTrack={track("recommendation")} className={`${boxClass} outline-none hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:bg-gray-700`}>
												{body}
											</TrackedLink>
										) : (
											<div className={boxClass}>{body}</div>
										)}
									</li>
								);
							})}
						</ul>
					</div>
				)}

				{(result.cta || (redirectUrl && !result.redirect?.auto)) && (
					<div className="flex flex-col gap-1.5">
						{result.cta && (
							<TrackedLink href={result.cta.url} onTrack={track("cta")} className={primaryLink}>
								{result.cta.label}
							</TrackedLink>
						)}
						{redirectUrl && !result.redirect?.auto && (
							<TrackedLink href={redirectUrl.href} onTrack={track("redirect")} className={result.cta ? secondaryLink : primaryLink}>
								{strings.continue}
							</TrackedLink>
						)}
					</div>
				)}

				{countdown !== null && redirectUrl && (
					<div role="status" className="flex items-center justify-between gap-2 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
						<span>{sprintf(strings.redirecting, Math.max(countdown, 0))}</span>
						<span className="flex gap-1">
							<Button type="button" className="size-auto h-7 bg-transparent px-2 text-xs text-gray-600 hover:bg-gray-200 hover:brightness-100 dark:text-gray-300 dark:hover:bg-gray-700" onClick={() => setCountdown(null)}>
								{strings.cancel}
							</Button>
							<Button type="button" className="size-auto h-7 px-2 text-xs" onClick={() => setCountdown(0)}>
								{strings.goNow}
							</Button>
						</span>
					</div>
				)}

				{result.allowRestart && (
					<Button
						type="button"
						disabled={quiz.busy}
						onClick={() => quiz.restart(result.token)}
						className="size-auto h-auto self-start bg-transparent p-0 text-xs font-medium text-gray-500 underline hover:bg-transparent hover:text-gray-800 hover:brightness-100 dark:text-gray-400 dark:hover:text-gray-200"
					>
						↻ {strings.restartAgain}
					</Button>
				)}
			</div>
		</div>
	);
}

interface QuizStartCardProps {
	quiz: QuizSummary;
	strings: QuizStrings;
	disabled: boolean;
	onStart: () => void;
}

/** A compact "take this quiz" card, used for AI suggestions and the quiz menu. */
export function QuizStartCard({ quiz, strings, disabled, onStart }: QuizStartCardProps) {
	return (
		<div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
			<div className="min-w-0 flex-1">
				<p dir="auto" className="font-medium text-gray-900 dark:text-gray-100">
					{quiz.title}
				</p>
				{quiz.description && (
					<p dir="auto" className="text-xs text-gray-600 dark:text-gray-400">
						{quiz.description}
					</p>
				)}
			</div>
			<Button type="button" disabled={disabled} onClick={onStart} className="size-auto h-8 shrink-0 px-3 text-xs">
				{quiz.startLabel || strings.start}
			</Button>
		</div>
	);
}
