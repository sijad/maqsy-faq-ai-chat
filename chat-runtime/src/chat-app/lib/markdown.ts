/**
 * Minimal, safe markdown-ish rendering for AI replies: bold, italic, inline
 * code, and links. Deliberately not a full markdown parser — the model's
 * output is untrusted, so the input is HTML-escaped first and only our own
 * fixed set of tags is ever introduced afterwards; nothing from the model's
 * text can end up as a real tag or attribute.
 */

function escapeHtml( text: string ): string {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
}

export function renderSimpleMarkdown( text: string ): string {
	let html = escapeHtml( text );

	// Inline code: `code`
	html = html.replace(
		/`([^`]+)`/g,
		'<code class="rounded bg-black/10 px-1 py-0.5 font-mono text-[0.85em] dark:bg-white/20">$1</code>'
	);

	// Bold: **text** or __text__ (before italic, so single */_ aren't matched first).
	html = html.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
	html = html.replace( /__([^_]+)__/g, '<strong>$1</strong>' );

	// Italic: *text* or _text_
	html = html.replace( /\*([^*]+)\*/g, '<em>$1</em>' );
	html = html.replace( /_([^_]+)_/g, '<em>$1</em>' );

	// Links: [text](https://…) — http(s) only.
	html = html.replace(
		/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g,
		'<a href="$2" target="_blank" rel="noopener noreferrer" class="underline">$1</a>'
	);

	return html;
}
