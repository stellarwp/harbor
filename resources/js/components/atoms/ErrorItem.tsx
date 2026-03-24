/**
 * Displays a HarborError as a bullet item, recursively nesting the cause
 * chain beneath the top-level message.
 *
 * @package LiquidWeb\Harbor
 */
import HarborError from '@/errors/harbor-error';

interface Props {
    error:   HarborError;
    nested?: boolean;
}

/**
 * @since 1.0.0
 */
export function ErrorItem( { error, nested = false }: Props ) {
    const cause = error.cause instanceof HarborError ? error.cause : null;

    return (
        <li className="flex flex-col gap-1">
            <span className={ `flex items-start gap-2 text-sm ${ nested ? 'text-muted-foreground' : 'font-medium text-foreground' }` }>
                <span className="mt-1.5 shrink-0 w-1.5 h-1.5 rounded-full bg-destructive" aria-hidden="true" />
                { error.message }
            </span>
            { cause && (
                <ul className="ml-3 space-y-1 border-l border-border pl-3">
                    <ErrorItem error={ cause } nested />
                </ul>
            ) }
        </li>
    );
}
