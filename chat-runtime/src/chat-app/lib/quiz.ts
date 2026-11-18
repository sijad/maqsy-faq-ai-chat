/**
 * Fill printf-style placeholders (%d, %s, %1$d, …) in a translated string
 * coming from PHP's __(), so translators keep control of word order.
 */
export function sprintf(format: string, ...args: (string | number)[]): string {
	let next = 0;
	return format.replace(/%(?:(\d+)\$)?[ds]/g, (_match, position?: string) => {
		const value = position ? args[Number(position) - 1] : args[next++];
		return value === undefined ? "" : String(value);
	});
}

/**
 * Resolve a result/recommendation URL, allowing only http(s). URLs are
 * already sanitized server-side; this is defense in depth before navigating.
 */
export function safeUrl(url: string): URL | null {
	try {
		const parsed = new URL(url, window.location.href);
		return parsed.protocol === "http:" || parsed.protocol === "https:" ? parsed : null;
	} catch (error) {
		return null;
	}
}

/** Same-site links navigate in place (the chat persists); others open a new tab. */
export function isExternal(url: URL): boolean {
	return url.origin !== window.location.origin;
}
