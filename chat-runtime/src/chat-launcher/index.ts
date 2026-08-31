/**
 * Always-loaded bootstrap for the floating AI chat button.
 *
 * Deliberately framework-free and tiny: React, Tailwind, and the chat UI
 * itself only load (via a dynamic import, in a separate chunk) once a
 * visitor actually clicks the button. Everything renders inside a shared
 * shadow root so neither the active theme nor Tailwind's reset can bleed
 * across the boundary in either direction.
 */

import type { ChatAppApi, ChatSettings } from "../chat-app/types";

function getLauncherStyles(primaryColor: string): string {
	return `
		:host {
			all: initial;
			--maqsy-primary: ${primaryColor};
		}
		.maqsy-launcher-button {
			position: fixed;
			bottom: 1rem;
			right: 1rem;
			z-index: 999999;
			display: flex;
			align-items: center;
			justify-content: center;
			width: 3.5rem;
			height: 3.5rem;
			border-radius: 9999px;
			border: none;
			background: var(--maqsy-primary);
			color: #fff;
			cursor: pointer;
			box-shadow: 0 4px 14px rgba( 0, 0, 0, 0.25 );
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}
		.maqsy-launcher-button:hover {
			filter: brightness(0.9);
		}
		.maqsy-launcher-button:focus-visible {
			outline: 2px solid var(--maqsy-primary);
			outline-offset: 2px;
		}
		.maqsy-launcher-button:disabled {
			opacity: 0.6;
			cursor: default;
		}
		.maqsy-launcher-button svg {
			width: 1.5rem;
			height: 1.5rem;
			pointer-events: none;
		}
	`;
}

const CHAT_ICON =
	'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>';

function readSettings(): ChatSettings | null {
	const el = document.getElementById("maqsy-chat-settings");
	if (!el || !el.textContent) {
		return null;
	}
	try {
		return JSON.parse(el.textContent) as ChatSettings;
	} catch (error) {
		return null;
	}
}

document.addEventListener("DOMContentLoaded", function () {
	const settings = readSettings();

	if (!settings || !settings.enabled) {
		return;
	}

	const host = document.createElement("div");
	host.id = "maqsy-chat-host";
	document.body.appendChild(host);

	const shadowRoot = host.attachShadow({ mode: "open" });

	const baseStyle = document.createElement("style");
	baseStyle.textContent = getLauncherStyles(settings.primaryColor || "#2563eb");
	shadowRoot.appendChild(baseStyle);

	const button = document.createElement("button");
	button.type = "button";
	button.className = "maqsy-launcher-button";
	button.setAttribute("aria-label", settings.buttonLabel);
	button.setAttribute("aria-expanded", "false");
	button.innerHTML = CHAT_ICON;
	shadowRoot.appendChild(button);

	let chatApi: ChatAppApi | null = null;

	button.addEventListener("click", function () {
		if (chatApi) {
			if (!chatApi.isOpen()) {
				chatApi.open();
			}
			return;
		}

		button.disabled = true;

		import("../chat-app/index.tsx")
			.then(function (chatAppModule) {
				// mountChatApp() renders already-open on its first (and only) initial
				// render — don't also call open() here, or we're back to two renders
				// in a row right at mount.
				chatApi = chatAppModule.mountChatApp(shadowRoot, button, settings);
			})
			.finally(function () {
				button.disabled = false;
			});
	});
});
