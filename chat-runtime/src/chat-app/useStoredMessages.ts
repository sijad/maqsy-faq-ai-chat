import { useEffect, useState } from 'react';
import type { ChatMessage } from './types';

const MAX_STORED_MESSAGES = 50;

function readStoredMessages( storageKey: string ): ChatMessage[] {
	try {
		const raw = window.localStorage.getItem( storageKey );
		if ( ! raw ) {
			return [];
		}
		const parsed = JSON.parse( raw );
		return Array.isArray( parsed ) ? parsed : [];
	} catch ( error ) {
		return [];
	}
}

/**
 * Persist the chat transcript to localStorage so it survives page reloads,
 * capped to the most recent messages to avoid unbounded storage growth.
 */
export function useStoredMessages(
	storageKey: string
): [ ChatMessage[], ( messages: ChatMessage[] ) => void ] {
	const [ messages, setMessages ] = useState< ChatMessage[] >( () =>
		readStoredMessages( storageKey )
	);

	useEffect( () => {
		try {
			window.localStorage.setItem(
				storageKey,
				JSON.stringify( messages.slice( -MAX_STORED_MESSAGES ) )
			);
		} catch ( error ) {
			// localStorage may be unavailable (private browsing, quota exceeded) — fail silently.
		}
	}, [ storageKey, messages ] );

	return [ messages, setMessages ];
}
