/**
 * Displays a single error message as a bullet list item.
 *
 * @package LiquidWeb\Harbor
 */

interface Props {
    message: string;
}

/**
 * @since 1.0.0
 */
export function ErrorItem( { message }: Props ) {
    return (
        <li className="flex items-start gap-2 text-sm text-foreground">
            <span className="mt-1.5 shrink-0 w-1.5 h-1.5 rounded-full bg-destructive" aria-hidden="true" />
            { message }
        </li>
    );
}
