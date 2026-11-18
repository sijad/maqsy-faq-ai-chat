import { useEffect, useRef, useState } from "react";

import { Popover, PopoverContent } from "./ui/popover";
import { Tooltip, TooltipContent, TooltipTrigger } from "./ui/tooltip";
import { Button } from "./ui/button";
import { Textarea } from "./ui/textarea";
import {
	MessageScroller,
	MessageScrollerButton,
	MessageScrollerContent,
	MessageScrollerItem,
	MessageScrollerProvider,
	MessageScrollerViewport,
} from "./ui/message-scroller";
import { useColorMode } from "./useColorMode";
import { useStoredMessages } from "./useStoredMessages";
import { useQuiz } from "./useQuiz";
import { renderSimpleMarkdown } from "./lib/markdown";
import { QuizComposer } from "./quiz/QuizComposer";
import { QuizResultCard, QuizStartCard } from "./quiz/QuizCards";
import type { ChatMessage, ChatSettings, QuizSource } from "./types";

/** A request (from a page link or the launcher API) to start a quiz; seq makes repeats distinct. */
export interface QuizRequest {
	id: number;
	source: QuizSource;
	seq: number;
}

interface ChatAppProps {
	settings: ChatSettings;
	open: boolean;
	onClose: () => void;
	anchor: HTMLElement;
	container: HTMLElement | ShadowRoot;
	quizRequest: QuizRequest | null;
}

/** Plain conversation turns — quiz transcript lines and result cards stay out of the AI's history. */
function isConversationMessage(message: ChatMessage): boolean {
	return !message.isError && !message.kind && !message.result && (message.content.trim() !== "" || !!message.suggestion);
}

/** A turn as the AI wrote it: the server strips the [[quiz:ID]] token into `suggestion`, so put it back for the history to show a quiz was already suggested. */
function toHistoryContent({ content, suggestion }: ChatMessage): string {
	const token = suggestion ? `[[quiz:${suggestion.id}]]` : "";
	return [content.slice(0, 16000 - token.length - 1).trim(), token].filter(Boolean).join("\n");
}

export function ChatApp({ settings, open, onClose, anchor, container, quizRequest }: ChatAppProps) {
	const [messages, setMessages] = useStoredMessages(settings.storageKey);
	const quiz = useQuiz(settings, setMessages);
	const strings = settings.quizStrings;
	const quizzes = settings.quizzes || [];
	const [showQuizMenu, setShowQuizMenu] = useState(false);

	useEffect(() => {
		if (quizRequest) {
			setShowQuizMenu(false);
			quiz.start(quizRequest.id, quizRequest.source);
		}
		// Only when a new request arrives (seq changes), not when quiz state does.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [quizRequest?.seq]);
	const [input, setInput] = useState("");
	const [isSending, setIsSending] = useState(false);
	const [isLongWait, setIsLongWait] = useState(false);

	useEffect(() => {
		if (!isSending) {
			setIsLongWait(false);
			return;
		}
		const timer = window.setTimeout(() => setIsLongWait(true), 10000);
		return () => window.clearTimeout(timer);
	}, [isSending]);
	const isDark = useColorMode(settings.colorMode);
	const textareaRef = useRef<HTMLTextAreaElement>(null);

	useEffect(() => {
		if ("classList" in container) {
			container.classList.toggle("dark", isDark);
		}
	}, [container, isDark]);

	useEffect(() => {
		if (!open) {
			return;
		}
		const id = window.setTimeout(() => textareaRef.current?.focus(), 0);
		return () => window.clearTimeout(id);
	}, [open]);

	async function sendMessage() {
		const text = input.trim();
		if (!text || isSending) {
			return;
		}

		const userMessage: ChatMessage = { role: "user", content: text };
		setMessages((previous) => [...previous, userMessage]);
		setInput("");
		setIsSending(true);

		try {
			const headers: Record<string, string> = {
				"Content-Type": "application/json",
			};
			if (settings.nonce) {
				headers["X-WP-Nonce"] = settings.nonce;
			}

			const res = await fetch(settings.restUrl, {
				method: "POST",
				headers,
				body: JSON.stringify({
					message: text,
					history: settings.historyLimit > 0
						? messages.filter(isConversationMessage)
							.slice(-Math.min(20, settings.historyLimit))
							.map((message) => ({ role: message.role, content: toHistoryContent(message) }))
						: [],
				}),
			});
			const data = await res.json();

			if (!res.ok) {
				throw new Error(data.message || "Request failed");
			}

			if (data.start && data.quiz) {
				// The message was a quiz keyword: keep it in the transcript but out of the AI's history.
				setMessages((previous) => previous.map((message) => (message === userMessage ? { ...message, kind: "quiz" } : message)));
				setShowQuizMenu(false);
				quiz.start(data.quiz.id, "keyword");
				return;
			}

			setMessages((previous) => [...previous, { role: "assistant", content: data.reply || "", suggestion: data.quiz || undefined }]);
		} catch (error) {
			setMessages((previous) => [...previous, { role: "assistant", content: settings.errorMessage, isError: true }]);
		} finally {
			setIsSending(false);
		}
	}

	return (
		<Popover
			open={open}
			onOpenChange={(isOpen) => {
				if (!isOpen) {
					onClose();
				}
			}}
		>
			<PopoverContent
				anchor={anchor}
				container={container}
				side="top"
				align="end"
				sideOffset={12}
				className="flex h-[32rem]"
			>
				<div className="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
					<div className="flex items-center gap-1.5">
						<span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
							{settings.title}
						</span>
						<Tooltip>
							<TooltipTrigger
								aria-label="About this chat"
								closeOnClick={false}
								delay={0}
								className="inline-flex size-4 shrink-0 items-center justify-center rounded-full text-gray-400 outline-none hover:text-gray-600 focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-500 dark:hover:text-gray-300"
							>
								<svg
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 24 24"
									fill="currentColor"
									className="size-6"
								>
									<path
										fillRule="evenodd"
										d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
										clipRule="evenodd"
									/>
								</svg>
							</TooltipTrigger>
							<TooltipContent container={container} className="max-w-[16rem]" side="top">
								This is an AI-powered chatbot and can make mistakes. Check important info.
							</TooltipContent>
						</Tooltip>
					</div>
					<div className="flex items-center gap-1">
						{quizzes.length > 0 && !quiz.active && (
							<Button
								className="size-auto h-7 bg-transparent px-2 text-xs font-medium text-gray-600 hover:bg-gray-200 hover:brightness-100 aria-expanded:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700 dark:aria-expanded:bg-gray-700"
								onClick={() => setShowQuizMenu((value) => !value)}
								aria-expanded={showQuizMenu}
								aria-controls="maqsy-quiz-menu"
							>
								{strings.menuButton}
							</Button>
						)}
						<Button
							className="bg-transparent text-gray-500 hover:bg-gray-200 hover:brightness-100 dark:text-gray-400 dark:hover:bg-gray-700"
							onClick={onClose}
							aria-label="Close chat"
						>
							✕
						</Button>
					</div>
				</div>

				<MessageScrollerProvider autoScroll defaultScrollPosition="end">
					<MessageScroller className="flex-1">
						<MessageScrollerViewport className="p-4" aria-label="Chat messages" role="log">
							<MessageScrollerContent>
								{messages.length === 0 && (
									<div className="flex flex-col gap-3">
										<p className="text-sm text-gray-500 dark:text-gray-400">
											{settings.welcomeMessage}
										</p>
										{quizzes.length > 0 && !quiz.active && (
											<div className="flex flex-col gap-1.5">
												<p className="text-xs font-medium text-gray-500 dark:text-gray-400">{strings.menuTitle}</p>
												{quizzes.map((item) => (
													<QuizStartCard key={item.id} quiz={item} strings={strings} disabled={quiz.busy} onStart={() => quiz.start(item.id, "manual")} />
												))}
											</div>
										)}
									</div>
								)}
								{messages.map((message, index) => (
									<MessageScrollerItem key={index} messageId={String(index)}>
										{message.result ? (
											<QuizResultCard result={message.result} quiz={quiz} strings={strings} />
										) : (
										<div className="flex flex-col gap-2">
										{message.content !== "" && (
										<div
											className={`flex ${
												message.role === "user" ? "justify-end" : "justify-start"
											}`}
										>
											{(() => {
												const bubbleClassName = `max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm ${
													message.role === "user"
														? "bg-[var(--maqsy-primary,#2563eb)] text-white"
														: "bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100"
												}`;

												// Only the AI's own replies are markdown-rendered — the
												// text is HTML-escaped first, then only our own fixed
												// set of tags is introduced (see lib/markdown.ts), so
												// nothing from the model's output can inject real markup.
												return message.role === "assistant" ? (
													<div
														dir="auto"
														className={bubbleClassName}
														dangerouslySetInnerHTML={{
															__html: renderSimpleMarkdown(message.content),
														}}
													/>
												) : (
													<div dir="auto" className={bubbleClassName}>
														{message.content}
													</div>
												);
											})()}
										</div>
										)}
										{message.suggestion && (
											<QuizStartCard
												quiz={message.suggestion}
												strings={strings}
												disabled={quiz.busy || quiz.active?.quiz.id === message.suggestion.id}
												onStart={() => quiz.start(message.suggestion!.id, "suggested")}
											/>
										)}
										</div>
										)}
									</MessageScrollerItem>
								))}
								{(isSending || quiz.busy) && (
									<MessageScrollerItem messageId="pending" scrollAnchor={false}>
										<div role="status" className="flex w-fit max-w-[85%] items-center gap-3 rounded-lg bg-gray-100 px-3 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
											<span aria-hidden="true" className="flex shrink-0 gap-1">
												{[0, 1, 2].map((dot) => (
													<span key={dot} className="maqsy-typing-dot size-1.5 rounded-full bg-current" style={{ animationDelay: `${dot * 160}ms` }} />
												))}
											</span>
											{isSending ? (
												<span>{isLongWait ? settings.waitingMessage : settings.loadingMessage}</span>
											) : quiz.busyLabel ? (
												<span>{quiz.busyLabel}</span>
											) : (
												<span className="sr-only">{settings.loadingMessage}</span>
											)}
										</div>
									</MessageScrollerItem>
								)}
							</MessageScrollerContent>
						</MessageScrollerViewport>
						<MessageScrollerButton direction="end" />
					</MessageScroller>
				</MessageScrollerProvider>

				{quiz.active?.status === "active" && quiz.active.question ? (
					<QuizComposer quiz={quiz} active={quiz.active} strings={strings} />
				) : (
				<>
				{quiz.active?.status === "paused" && (
					<div className="flex items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-800">
						<span className="min-w-0 truncate font-medium text-gray-700 dark:text-gray-200">{quiz.active.quiz.title}</span>
						<span className="flex shrink-0 gap-1">
							<Button className="size-auto h-7 bg-transparent px-2 text-xs text-gray-600 hover:bg-gray-200 hover:brightness-100 dark:text-gray-300 dark:hover:bg-gray-700" disabled={quiz.busy} onClick={quiz.exit}>
								{strings.exit}
							</Button>
							<Button className="size-auto h-7 px-2 text-xs" disabled={quiz.busy} onClick={quiz.resume}>
								{strings.resume}
							</Button>
						</span>
					</div>
				)}
				{showQuizMenu && !quiz.active && quizzes.length > 0 && (
					<div id="maqsy-quiz-menu" className="flex max-h-48 flex-col gap-1.5 overflow-y-auto border-t border-gray-200 p-3 dark:border-gray-700">
						<p className="text-xs font-medium text-gray-500 dark:text-gray-400">{strings.menuTitle}</p>
						{quizzes.map((item) => (
							<QuizStartCard
								key={item.id}
								quiz={item}
								strings={strings}
								disabled={quiz.busy}
								onStart={() => {
									setShowQuizMenu(false);
									quiz.start(item.id, "manual");
								}}
							/>
						))}
					</div>
				)}
				<form
					className="relative flex items-end gap-2 border-t border-gray-200 p-3 dark:border-gray-700"
					onSubmit={(event) => {
						event.preventDefault();
						void sendMessage();
					}}
				>
					<label className="sr-only" htmlFor="maqsy-chat-input">
						Message
					</label>
					<Textarea
						id="maqsy-chat-input"
						autoComplete="off"
						ref={textareaRef}
						className="min-h-9 flex-1 py-1.5 pr-11 resize-none"
						value={input}
						maxLength={1000}
						onChange={(event) => setInput(event.target.value)}
						onKeyDown={(event) => {
							// <textarea> never submits on Enter natively, so route the
							// bare-Enter case through the form (Shift+Enter still inserts
							// a newline).
							if (event.key === "Enter" && !event.shiftKey) {
								event.preventDefault();
								event.currentTarget.form?.requestSubmit();
							}
						}}
						placeholder="Type your question…"
						disabled={isSending}
						rows={3}
					/>
					<Button
						type="submit"
						disabled={isSending || !input.trim()}
						aria-label="Send"
						className="absolute right-4 bottom-4"
					>
						↑
					</Button>
				</form>
				</>
				)}
			</PopoverContent>
		</Popover>
	);
}
