import { useCallback, useEffect, useRef, useState } from "react";
import type { Dispatch, SetStateAction } from "react";

import type { ChatMessage, ChatSettings, QuizSource, QuizState } from "./types";

/** The part of a quiz session the widget keeps across page loads. */
export interface ActiveQuiz {
	token: string;
	quiz: QuizState["quiz"];
	status: "active" | "paused";
	question: QuizState["question"];
	progress: QuizState["progress"];
}

export interface QuizController {
	active: ActiveQuiz | null;
	busy: boolean;
	busyLabel: string;
	error: string;
	start: (quizId: number, source: QuizSource) => void;
	answer: (value: string | string[]) => void;
	skip: () => void;
	pause: () => void;
	resume: () => void;
	restart: (token?: string) => void;
	exit: () => void;
	trackClick: (token: string, url: string, kind: "cta" | "recommendation" | "redirect") => void;
}

class QuizRequestError extends Error {
	constructor(
		message: string,
		public status: number,
		public code: string,
		public state?: QuizState,
	) {
		super(message);
	}
}

function readActive(key: string): ActiveQuiz | null {
	try {
		const raw = window.localStorage.getItem(key);
		const parsed = raw ? JSON.parse(raw) : null;
		return parsed && typeof parsed.token === "string" ? parsed : null;
	} catch (error) {
		return null;
	}
}

function toActive(state: QuizState): ActiveQuiz {
	return {
		token: state.token,
		quiz: state.quiz,
		status: state.status === "paused" ? "paused" : "active",
		question: state.question,
		progress: state.progress,
	};
}

function questionMessage(state: QuizState): ChatMessage {
	const question = state.question!;
	return {
		role: "assistant",
		kind: "quiz",
		content: question.help ? `${question.text}\n_${question.help}_` : question.text,
	};
}

/**
 * Drives a conversational quiz inside the chat transcript.
 *
 * The server owns quiz progress (see Maqsy_Quiz_Rest_Controller); this hook
 * just renders each state it returns as chat messages and remembers the
 * session token so a quiz survives reloads and can be paused/resumed.
 */
export function useQuiz(
	settings: ChatSettings,
	setMessages: Dispatch<SetStateAction<ChatMessage[]>>,
): QuizController {
	const storageKey = `${settings.storageKey}:quiz`;
	const strings = settings.quizStrings;

	const [active, setActiveState] = useState<ActiveQuiz | null>(() => readActive(storageKey));
	const [busy, setBusy] = useState(false);
	const [busyLabel, setBusyLabel] = useState("");
	const [error, setError] = useState("");
	const busyRef = useRef(false);
	const activeRef = useRef(active);

	const setActive = useCallback(
		(next: ActiveQuiz | null) => {
			activeRef.current = next;
			setActiveState(next);
			try {
				if (next) {
					window.localStorage.setItem(storageKey, JSON.stringify(next));
				} else {
					window.localStorage.removeItem(storageKey);
				}
			} catch (storageError) {
				// localStorage may be unavailable — the quiz still works for this page view.
			}
		},
		[storageKey],
	);

	const append = useCallback(
		(...items: ChatMessage[]) => setMessages((previous) => [...previous, ...items]),
		[setMessages],
	);

	const request = useCallback(
		async (path: string, body: Record<string, unknown>, keepalive = false): Promise<QuizState> => {
			const headers: Record<string, string> = { "Content-Type": "application/json" };
			if (settings.nonce) {
				headers["X-WP-Nonce"] = settings.nonce;
			}
			const res = await fetch(`${settings.quizRestUrl}/${path}`, {
				method: "POST",
				headers,
				body: JSON.stringify(body),
				keepalive,
			});
			const data = await res.json().catch(() => ({}));
			if (!res.ok) {
				throw new QuizRequestError(data.message || strings.error, res.status, data.code || "", data.data?.state);
			}
			return data as QuizState;
		},
		[settings.nonce, settings.quizRestUrl, strings.error],
	);

	/** Render a server state: the next question, or the result card. */
	const apply = useCallback(
		(state: QuizState, intro = false) => {
			const items: ChatMessage[] = [];
			if (intro && state.intro) {
				items.push({ role: "assistant", kind: "quiz", content: state.intro });
			}
			if (state.status === "completed" && state.result) {
				items.push({
					role: "assistant",
					content: state.result.title,
					result: {
						...state.result,
						token: state.token,
						quizTitle: state.quiz.title,
						allowRestart: state.quiz.allowRestart,
						createdAt: Date.now(),
					},
				});
				setActive(null);
			} else if (state.question) {
				items.push(questionMessage(state));
				setActive(toActive(state));
			}
			append(...items);
		},
		[append, setActive],
	);

	/** Run one request at a time, translating failures into chat messages. */
	const run = useCallback(
		async (label: string, task: () => Promise<void>) => {
			if (busyRef.current) {
				return;
			}
			busyRef.current = true;
			setBusy(true);
			setBusyLabel(label);
			setError("");
			try {
				await task();
			} catch (caught) {
				const failure = caught instanceof QuizRequestError ? caught : null;
				if (failure?.code === "maqsy_quiz_out_of_sync" && failure.state) {
					// Another tab (or a double click) moved the quiz on; follow the server.
					// If it is still on the question already shown, don't show it twice.
					const { state } = failure;
					if (state.status !== "completed" && state.question && state.question.id === activeRef.current?.question?.id) {
						setActive(toActive(state));
					} else {
						apply(state);
					}
				} else if (failure && failure.status === 400) {
					// Validation messages stay next to the input rather than in the transcript.
					setError(failure.message);
				} else if (failure && failure.status === 404) {
					setActive(null);
					append({ role: "assistant", kind: "quiz", content: failure.code === "maqsy_quiz_session_expired" ? strings.expired : failure.message, isError: true });
				} else {
					append({ role: "assistant", kind: "quiz", content: failure?.message || strings.error, isError: true });
				}
			} finally {
				busyRef.current = false;
				setBusy(false);
				setBusyLabel("");
			}
		},
		[append, apply, setActive, strings.error, strings.expired],
	);

	// Resync a session restored from storage — the quiz may have been edited,
	// unpublished, or finished in another tab since.
	const resynced = useRef(false);
	useEffect(() => {
		if (resynced.current || !active) {
			return;
		}
		resynced.current = true;
		request("state", { token: active.token }).then(
			(state) => {
				if (state.question && state.status !== "completed") {
					setActive(toActive(state));
				} else {
					setActive(null);
				}
			},
			(caught) => {
				if (caught instanceof QuizRequestError && caught.status === 404) {
					setActive(null);
				}
			},
		);
		// Only ever on mount.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const start = useCallback(
		(quizId: number, source: QuizSource) => {
			if (active && active.quiz.id === quizId) {
				if (active.status === "paused") {
					void run("", async () => {
						apply(await request("resume", { token: active.token }));
					});
				}
				return;
			}
			void run(strings.starting, async () => {
				if (active) {
					// Starting a different quiz abandons the current one.
					void request("pause", { token: active.token }).catch(() => undefined);
				}
				apply(await request("start", { quiz_id: quizId, source, page_url: window.location.href }), true);
			});
		},
		[active, apply, request, run, strings.starting],
	);

	const submit = useCallback(
		(body: Record<string, unknown>, label: string) => {
			if (!active?.question) {
				return;
			}
			const token = active.token;
			const questionId = active.question.id;
			const isLast = active.progress.answered + 1 >= active.progress.total;
			void run(isLast ? strings.finishing : "", async () => {
				const state = await request("answer", { token, question_id: questionId, ...body });
				append({
					role: "user",
					kind: "quiz",
					content: body.skip ? strings.skipped : state.answer_label || label,
				});
				apply(state);
			});
		},
		[active, append, apply, request, run, strings.finishing, strings.skipped],
	);

	const answer = useCallback(
		(value: string | string[]) => submit({ value }, Array.isArray(value) ? value.join(", ") : value),
		[submit],
	);

	const skip = useCallback(() => submit({ skip: true }, strings.skipped), [submit, strings.skipped]);

	const pause = useCallback(() => {
		if (!active || active.status === "paused") {
			return;
		}
		const current = active;
		void run("", async () => {
			await request("pause", { token: current.token });
			setActive({ ...current, status: "paused" });
			append({ role: "assistant", kind: "quiz", content: strings.paused });
		});
	}, [active, append, request, run, setActive, strings.paused]);

	const resume = useCallback(() => {
		if (!active) {
			return;
		}
		const token = active.token;
		void run("", async () => {
			apply(await request("resume", { token }));
		});
	}, [active, apply, request, run]);

	const restart = useCallback(
		(token?: string) => {
			const target = token || active?.token;
			if (!target) {
				return;
			}
			void run(strings.starting, async () => {
				apply(await request("restart", { token: target }), true);
			});
		},
		[active, apply, request, run, strings.starting],
	);

	const exit = useCallback(() => {
		if (!active) {
			return;
		}
		// Left unfinished on the server, so it counts as a drop-off.
		void request("pause", { token: active.token }).catch(() => undefined);
		setActive(null);
		setError("");
		append({ role: "assistant", kind: "quiz", content: strings.ended });
	}, [active, append, request, setActive, strings.ended]);

	const trackClick = useCallback(
		(token: string, url: string, kind: "cta" | "recommendation" | "redirect") => {
			// keepalive lets the beacon outlive the navigation it precedes.
			void request("cta", { token, url, kind }, true).catch(() => undefined);
		},
		[request],
	);

	return { active, busy, busyLabel, error, start, answer, skip, pause, resume, restart, exit, trackClick };
}
