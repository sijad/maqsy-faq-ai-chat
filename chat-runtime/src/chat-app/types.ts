export type ColorMode = "light" | "dark" | "auto";

export interface QuizStrings {
	menuTitle: string;
	menuButton: string;
	start: string;
	starting: string;
	progress: string;
	skip: string;
	pause: string;
	pauseHint: string;
	paused: string;
	resume: string;
	restart: string;
	restartAgain: string;
	exit: string;
	continue: string;
	selectHint: string;
	answerHint: string;
	skipped: string;
	finishing: string;
	resultLabel: string;
	recommended: string;
	redirecting: string;
	cancel: string;
	goNow: string;
	error: string;
	expired: string;
	ended: string;
}

export interface ChatSettings {
	enabled: boolean;
	restUrl: string;
	nonce: string;
	title: string;
	buttonLabel: string;
	welcomeMessage: string;
	errorMessage: string;
	historyLimit: number;
	loadingMessage: string;
	waitingMessage: string;
	colorMode: ColorMode;
	storageKey: string;
	primaryColor: string;
	quizRestUrl: string;
	quizzes: QuizSummary[];
	quizStrings: QuizStrings;
}

/** A quiz as listed in the chat's quiz menu or suggested by the AI. */
export interface QuizSummary {
	id: number;
	title: string;
	description: string;
	/** The quiz's own start-button text; empty means the default. */
	startLabel?: string;
}

export type QuizSource = "manual" | "suggested" | "link" | "keyword";

export interface QuizQuestion {
	id: string;
	type: "single" | "multiple" | "text";
	text: string;
	help: string;
	required: boolean;
	input: "text" | "email" | "tel";
	placeholder: string;
	options: { id: string; label: string }[];
}

export interface QuizResult {
	id: string;
	title: string;
	message: string;
	offer: string;
	recommendations: { title: string; url: string; description: string }[];
	cta: { label: string; url: string } | null;
	redirect: { url: string; auto: boolean } | null;
}

/** The state every quiz REST route responds with. */
export interface QuizState {
	token: string;
	quiz: { id: number; title: string; allowRestart: boolean };
	status: "active" | "paused" | "completed";
	question: QuizQuestion | null;
	progress: { answered: number; total: number };
	result: QuizResult | null;
	intro?: string;
	answer_label?: string;
}

/** A result card in the transcript; keeps the token for CTA tracking and retakes. */
export interface QuizResultMessage extends QuizResult {
	token: string;
	quizTitle: string;
	allowRestart: boolean;
	createdAt: number;
}

export interface ChatMessage {
	role: "user" | "assistant";
	content: string;
	isError?: boolean;
	/** Quiz transcript lines (questions, answers, notices) — never sent to the AI as history. */
	kind?: "quiz";
	/** A quiz the AI suggested alongside this reply. */
	suggestion?: QuizSummary;
	/** A quiz result card. */
	result?: QuizResultMessage;
}

export interface ChatAppApi {
	open: () => void;
	close: () => void;
	toggle: () => void;
	isOpen: () => boolean;
	startQuiz: (quizId: number, source: QuizSource) => void;
}
