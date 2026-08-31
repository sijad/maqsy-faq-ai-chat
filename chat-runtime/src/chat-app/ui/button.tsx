import { Button as ButtonPrimitive } from "@base-ui/react/button";

import { cn } from "@/lib/utils";

function Button({ className, ...props }: ButtonPrimitive.Props) {
	return (
		<ButtonPrimitive
			data-slot="button"
			className={cn(
				"inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--maqsy-primary,#2563eb)] text-sm font-medium text-white outline-none select-none hover:brightness-90 focus-visible:ring-2 focus-visible:ring-blue-500 disabled:pointer-events-none disabled:opacity-50",
				className,
			)}
			{...props}
		/>
	);
}

export { Button };
