import { useEffect, useState } from 'react';
import type { ColorMode } from './types';

const DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * Resolve the configured color mode setting to an actual light/dark boolean,
 * tracking the OS-level preference live when the setting is "auto".
 */
export function useColorMode( colorMode: ColorMode ): boolean {
	const [ prefersDark, setPrefersDark ] = useState< boolean >( () =>
		typeof window !== 'undefined' && window.matchMedia
			? window.matchMedia( DARK_QUERY ).matches
			: false
	);

	useEffect( () => {
		if ( colorMode !== 'auto' || typeof window === 'undefined' || ! window.matchMedia ) {
			return;
		}

		const mediaQuery = window.matchMedia( DARK_QUERY );
		const handleChange = ( event: MediaQueryListEvent ) => setPrefersDark( event.matches );

		mediaQuery.addEventListener( 'change', handleChange );
		return () => mediaQuery.removeEventListener( 'change', handleChange );
	}, [ colorMode ] );

	if ( colorMode === 'dark' ) {
		return true;
	}
	if ( colorMode === 'light' ) {
		return false;
	}
	return prefersDark;
}
