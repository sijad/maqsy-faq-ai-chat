export type ColorMode = "light" | "dark" | "auto";

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
}

export interface ChatMessage {
	role: "user" | "assistant";
	content: string;
	isError?: boolean;
}

export interface ChatAppApi {
	open: () => void;
	close: () => void;
	toggle: () => void;
	isOpen: () => boolean;
}
