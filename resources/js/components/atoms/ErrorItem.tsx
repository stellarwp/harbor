/**
 * Displays a single error entry — human-readable message and machine-readable
 * error code.
 *
 * @package LiquidWeb\Harbor
 */
import type HarborError from '@/errors/harbor-error';

interface Props {
    error: HarborError;
}

/**
 * @since 1.0.0
 */
export function ErrorItem( { error }: Props ) {
    return (
        <li className="flex flex-col gap-0.5">
            <span className="text-sm font-medium text-destructive">{ error.message }</span>
            <span className="text-xs text-muted-foreground font-mono">{ error.code }</span>
        </li>
    );
}
