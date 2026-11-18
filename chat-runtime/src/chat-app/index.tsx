import { createRoot, type Root } from "react-dom/client";

import tailwindCss from "./tailwind.built.txt";
import { ChatApp, type QuizRequest } from "./ChatApp";
import type { ChatAppApi, ChatSettings } from "./types";

export function mountChatApp(
	shadowRoot: ShadowRoot,
	anchorElement: HTMLElement,
	settings: ChatSettings,
): ChatAppApi {
	const style = document.createElement("style");
	style.textContent = tailwindCss;
	shadowRoot.appendChild(style);

	const container = document.createElement("div");
	shadowRoot.appendChild(container);

	const root: Root = createRoot(container);

	let isOpen = true;
	let quizRequest: QuizRequest | null = null;
	anchorElement.setAttribute("aria-expanded", "true");

	function renderApp() {
		root.render(
			<ChatApp
				settings={settings}
				open={isOpen}
				onClose={() => api.close()}
				anchor={anchorElement}
				container={container}
				quizRequest={quizRequest}
			/>,
		);
	}

	const api: ChatAppApi = {
		open() {
			isOpen = true;
			anchorElement.setAttribute("aria-expanded", "true");
			renderApp();
		},
		close() {
			isOpen = false;
			anchorElement.setAttribute("aria-expanded", "false");
			renderApp();
		},
		toggle() {
			if (isOpen) {
				api.close();
			} else {
				api.open();
			}
		},
		isOpen() {
			return isOpen;
		},
		startQuiz(quizId, source) {
			quizRequest = { id: quizId, source, seq: (quizRequest?.seq ?? 0) + 1 };
			api.open();
		},
	};

	renderApp();

	return api;
}
