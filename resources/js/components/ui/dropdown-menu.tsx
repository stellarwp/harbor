/**
 * Radix UI DropdownMenu primitives styled to the project's design system.
 *
 * Intentionally NOT using DropdownMenu.Portal — portal content renders outside
 * .lw-harbor-ui and would be invisible to the PostCSS scope plugin (all Tailwind
 * utilities are scoped to .lw-harbor-ui). The Content renders in the DOM tree but
 * Radix positions it with position:fixed so it still floats above other elements.
 *
 * @package LiquidWeb\Harbor
 */
import * as React from 'react';
import { DropdownMenu as DropdownMenuPrimitive } from 'radix-ui';
import { cn } from '@/lib/utils';

const DropdownMenu = DropdownMenuPrimitive.Root;
const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;
const DropdownMenuGroup = DropdownMenuPrimitive.Group;

const DropdownMenuContent = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Content>
>( ( { className, sideOffset = 4, ...props }, ref ) => (
    <DropdownMenuPrimitive.Content
        ref={ ref }
        sideOffset={ sideOffset }
        className={ cn(
            'z-[100000] min-w-[8rem] overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md',
            'data-[side=bottom]:translate-y-1 data-[side=top]:-translate-y-1',
            className
        ) }
        { ...props }
    />
) );
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName;

const DropdownMenuItem = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Item>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Item>
>( ( { className, ...props }, ref ) => (
    <DropdownMenuPrimitive.Item
        ref={ ref }
        className={ cn(
            'relative flex w-full cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none',
            'focus:bg-accent focus:text-accent-foreground',
            'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
            'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
            className
        ) }
        { ...props }
    />
) );
DropdownMenuItem.displayName = DropdownMenuPrimitive.Item.displayName;

const DropdownMenuLabel = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Label>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Label>
>( ( { className, ...props }, ref ) => (
    <DropdownMenuPrimitive.Label
        ref={ ref }
        className={ cn( 'px-2 py-1.5 text-sm font-semibold', className ) }
        { ...props }
    />
) );
DropdownMenuLabel.displayName = DropdownMenuPrimitive.Label.displayName;

const DropdownMenuSeparator = React.forwardRef<
    React.ElementRef<typeof DropdownMenuPrimitive.Separator>,
    React.ComponentPropsWithoutRef<typeof DropdownMenuPrimitive.Separator>
>( ( { className, ...props }, ref ) => (
    <DropdownMenuPrimitive.Separator
        ref={ ref }
        className={ cn( '-mx-1 my-1 h-px bg-muted', className ) }
        { ...props }
    />
) );
DropdownMenuSeparator.displayName = DropdownMenuPrimitive.Separator.displayName;

export {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuGroup,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
};
