import * as React from 'react';

import { cn } from '@/lib/utils';

const Textarea = React.forwardRef<
	HTMLTextAreaElement,
	React.ComponentProps< 'textarea' >
>( ( { className, ...props }, ref ) => {
	return (
		<textarea
			ref={ ref }
			data-slot="textarea"
			className={ cn(
				'flex min-h-16 w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus-visible:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500',
				className
			) }
			{ ...props }
		/>
	);
} );
Textarea.displayName = 'Textarea';

export { Textarea };
