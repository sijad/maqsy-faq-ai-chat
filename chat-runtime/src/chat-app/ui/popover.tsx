import { Popover as PopoverPrimitive } from "@base-ui/react/popover";

import { cn } from "@/lib/utils";

function Popover({ ...props }: PopoverPrimitive.Root.Props) {
	return <PopoverPrimitive.Root data-slot="popover" {...props} />;
}

interface PopoverContentProps
	extends
		PopoverPrimitive.Popup.Props,
		Pick<
			PopoverPrimitive.Positioner.Props,
			"align" | "alignOffset" | "side" | "sideOffset" | "anchor"
		> {
	container?: PopoverPrimitive.Portal.Props["container"];
}

function PopoverContent({
	className,
	align = "center",
	alignOffset = 0,
	side = "top",
	sideOffset = 4,
	anchor,
	container,
	...props
}: PopoverContentProps) {
	return (
		<PopoverPrimitive.Portal container={container}>
			<PopoverPrimitive.Positioner
				anchor={anchor}
				// The launcher button is `position: fixed`, so the positioner must use
				// the same viewport-relative coordinate space — otherwise the popup's
				// offset drifts depending on document scroll/layout.
				positionMethod="fixed"
				align={align}
				alignOffset={alignOffset}
				side={side}
				sideOffset={sideOffset}
				className="isolate z-[999999]"
			>
				<PopoverPrimitive.Popup
					data-slot="popover-content"
					className={cn(
						"flex w-80 max-w-[calc(100vw-2rem)] origin-[var(--transform-origin)] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-sm text-gray-900 shadow-2xl outline-none duration-100",
						"data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2",
						"data-[open]:animate-in data-[open]:fade-in-0 data-[open]:zoom-in-95 data-[closed]:animate-out data-[closed]:fade-out-0 data-[closed]:zoom-out-95",
						"dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100",
						className,
					)}
					{...props}
				/>
			</PopoverPrimitive.Positioner>
		</PopoverPrimitive.Portal>
	);
}

export { Popover, PopoverContent };
