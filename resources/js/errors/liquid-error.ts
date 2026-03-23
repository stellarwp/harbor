/**
 * LiquidError -- typed wrapper around the WP REST API serialized WP_Error.
 *
 * @wordpress/api-fetch throws the parsed JSON body (a plain object) when
 * the server returns a non-2xx response. LiquidError normalizes that into
 * a proper Error subclass with structured access to code, data, and any
 * additional errors.
 *
 * The entire error chain is typed. `additionalErrors` contains LiquidError
 * instances (not plain WpRestError objects), so consumers get `.code`,
 * `.status`, and `.data` on every entry without casting.
 *
 * @package LiquidWeb\Harbor
 */

import type { WpRestError } from './types';
import { isWpRestError } from './utils';
import { ErrorCode } from './error-code';

export default class LiquidError extends Error {
	/**
	 * Machine-readable error code from the WP_Error.
	 */
	readonly code: string;

	/**
	 * Data payload (usually contains `{ status: number }`).
	 */
	readonly data: Record<string, unknown>;

	/**
	 * Secondary errors from a multi-code WP_Error response. This is a
	 * deserialization concern only. Use `cause` (via `LiquidError.wrap()`)
	 * to chain errors on the frontend.
	 */
	readonly additionalErrors: LiquidError[];

	/**
	 * Original cause, if this error wraps another.
	 */
	readonly cause?: Error;

	constructor(code: ErrorCode, message: string, options?: { cause?: Error });
	constructor(wpError: WpRestError, options?: { cause?: Error });
	constructor(
		codeOrError: ErrorCode | WpRestError,
		messageOrOptions?: string | { cause?: Error },
		options?: { cause?: Error }
	) {
		if (typeof codeOrError === 'string') {
			super(messageOrOptions as string);
			this.name = 'LiquidError';
			this.code = codeOrError;
			this.data = {};
			this.additionalErrors = [];
			this.cause = options?.cause;
		} else {
			super(codeOrError.message);
			this.name = 'LiquidError';
			this.code = codeOrError.code;
			this.data = codeOrError.data ?? {};
			this.additionalErrors = (codeOrError.additional_errors ?? []).map(
				(entry) => new LiquidError(entry)
			);
			this.cause = (messageOrOptions as { cause?: Error } | undefined)?.cause;
		}
	}

	/**
	 * HTTP status code, if present.
	 */
	get status(): number | undefined {
		return typeof this.data.status === 'number'
			? this.data.status
			: undefined;
	}

	/**
	 * Flatten the error tree into an array. Collects this error, then its
	 * additionalErrors (server-side siblings), then recurses into cause.
	 */
	toArray(): LiquidError[] {
		const result: LiquidError[] = [this];
		for (const additional of this.additionalErrors) {
			result.push(...additional.toArray());
		}
		if (this.cause instanceof LiquidError) {
			result.push(...this.cause.toArray());
		}
		return result;
	}

	/**
	 * Async conversion of an unknown value into an LiquidError.
	 *
	 * Handles everything `syncFrom` does, plus `Response` objects that
	 * apiFetch throws when it cannot parse JSON or when `parse: false`
	 * is used.
	 */
	static async from(
		error: unknown,
		code: ErrorCode,
		message: string
	): Promise<LiquidError> {
		if (error instanceof Response) {
			try {
				const body = await error.json();
				if (isWpRestError(body)) {
					return new LiquidError(body);
				}
			} catch {
				// Response body wasn't JSON, fall through.
			}

			return new LiquidError(code, message);
		}

		return LiquidError.syncFrom(error, code, message);
	}

	/**
	 * Synchronous conversion of an unknown value into an LiquidError.
	 *
	 * If the value is already an LiquidError, returns it as-is. If it is
	 * a WpRestError, hydrates it via the constructor. Anything else
	 * (plain Error, string, etc.) produces an LiquidError with the given
	 * fallback `code` and `message`, and the original is stored as `cause`.
	 */
	static syncFrom(
		error: unknown,
		code: ErrorCode,
		message: string
	): LiquidError {
		if (error instanceof LiquidError) {
			return error;
		}

		if (isWpRestError(error)) {
			return new LiquidError(error);
		}

		if (error instanceof Error) {
			return new LiquidError({ code, message }, { cause: error });
		}

		return new LiquidError({ code, message });
	}

	/**
	 * Async wrap of an unknown caught value into an LiquidError with context.
	 *
	 * The provided `code` and `message` describe what operation failed.
	 * The original value is preserved as `cause` so the full error chain
	 * is available for inspection. When the original is a WpRestError,
	 * its `data` and `additional_errors` are also carried forward.
	 *
	 * Handles `Response` objects that apiFetch throws when it cannot
	 * parse JSON or when `parse: false` is used.
	 */
	static async wrap(
		error: unknown,
		code: ErrorCode,
		message: string
	): Promise<LiquidError> {
		if (error instanceof Response) {
			try {
				const body = await error.json();
				if (isWpRestError(body)) {
					return new LiquidError(
						{
							code,
							message,
							data: body.data,
							additional_errors: body.additional_errors,
						},
						{ cause: new LiquidError(body) }
					);
				}
			} catch {
				// Response body wasn't JSON, fall through.
			}

			return new LiquidError({ code, message });
		}

		return LiquidError.wrapSync(error, code, message);
	}

	/**
	 * Synchronous wrap of an unknown caught value into an LiquidError
	 * with context.
	 *
	 * Same as `wrap` but cannot handle `Response` objects. Use this in
	 * synchronous code paths where `await` is not available.
	 */
	static wrapSync(error: unknown, code: ErrorCode, message: string): LiquidError {
		if (error instanceof LiquidError || error instanceof Error) {
			return new LiquidError({ code, message }, { cause: error });
		}

		if (isWpRestError(error)) {
			return new LiquidError(
				{
					code,
					message,
					data: error.data,
					additional_errors: error.additional_errors,
				},
				{ cause: new LiquidError(error) }
			);
		}

		return new LiquidError({ code, message });
	}
}
