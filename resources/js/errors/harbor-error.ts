/**
 * HarborError -- typed wrapper around the WP REST API serialized WP_Error.
 *
 * @wordpress/api-fetch throws the parsed JSON body (a plain object) when
 * the server returns a non-2xx response. HarborError normalizes that into
 * a proper Error subclass with structured access to code, data, and any
 * additional errors.
 *
 * The entire error chain is typed. `additionalErrors` contains HarborError
 * instances (not plain WpRestError objects), so consumers get `.code`,
 * `.status`, and `.data` on every entry without casting.
 *
 * @package LiquidWeb\Harbor
 */

import type { WpRestError } from './types';
import { isWpRestError } from './utils';
import { ErrorCode } from './error-code';

export default class HarborError extends Error {
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
	 * deserialization concern only. Use `cause` (via `HarborError.wrap()`)
	 * to chain errors on the frontend.
	 */
	readonly additionalErrors: HarborError[];

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
			this.name = 'HarborError';
			this.code = codeOrError;
			this.data = {};
			this.additionalErrors = [];
			this.cause = options?.cause;
		} else {
			super(codeOrError.message);
			this.name = 'HarborError';
			this.code = codeOrError.code;
			this.data = codeOrError.data ?? {};
			this.additionalErrors = (codeOrError.additional_errors ?? []).map(
				(entry) => new HarborError(entry)
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
	toArray(): HarborError[] {
		const result: HarborError[] = [this];
		for (const additional of this.additionalErrors) {
			result.push(...additional.toArray());
		}
		if (this.cause instanceof HarborError) {
			result.push(...this.cause.toArray());
		}
		return result;
	}

	/**
	 * Async conversion of an unknown value into an HarborError.
	 *
	 * Handles everything `syncFrom` does, plus `Response` objects that
	 * apiFetch throws when it cannot parse JSON or when `parse: false`
	 * is used.
	 */
	static async from(
		error: unknown,
		code: ErrorCode,
		message: string
	): Promise<HarborError> {
		if (error instanceof Response) {
			try {
				const body = await error.json();
				if (isWpRestError(body)) {
					return new HarborError(body);
				}
			} catch {
				// Response body wasn't JSON, fall through.
			}

			return new HarborError(code, message);
		}

		return HarborError.syncFrom(error, code, message);
	}

	/**
	 * Synchronous conversion of an unknown value into an HarborError.
	 *
	 * If the value is already an HarborError, returns it as-is. If it is
	 * a WpRestError, hydrates it via the constructor. Anything else
	 * (plain Error, string, etc.) produces an HarborError with the given
	 * fallback `code` and `message`, and the original is stored as `cause`.
	 */
	static syncFrom(
		error: unknown,
		code: ErrorCode,
		message: string
	): HarborError {
		if (error instanceof HarborError) {
			return error;
		}

		if (isWpRestError(error)) {
			return new HarborError(error);
		}

		if (error instanceof Error) {
			return new HarborError({ code, message }, { cause: error });
		}

		return new HarborError({ code, message });
	}

	/**
	 * Async wrap of an unknown caught value into an HarborError with context.
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
	): Promise<HarborError> {
		if (error instanceof Response) {
			try {
				const body = await error.json();
				if (isWpRestError(body)) {
					return new HarborError(
						{
							code,
							message,
							data: body.data,
							additional_errors: body.additional_errors,
						},
						{ cause: new HarborError(body) }
					);
				}
			} catch {
				// Response body wasn't JSON, fall through.
			}

			return new HarborError({ code, message });
		}

		return HarborError.wrapSync(error, code, message);
	}

	/**
	 * Synchronous wrap of an unknown caught value into an HarborError
	 * with context.
	 *
	 * Same as `wrap` but cannot handle `Response` objects. Use this in
	 * synchronous code paths where `await` is not available.
	 */
	static wrapSync(error: unknown, code: ErrorCode, message: string): HarborError {
		if (error instanceof HarborError || error instanceof Error) {
			return new HarborError({ code, message }, { cause: error });
		}

		if (isWpRestError(error)) {
			return new HarborError(
				{
					code,
					message,
					data: error.data,
					additional_errors: error.additional_errors,
				},
				{ cause: new HarborError(error) }
			);
		}

		return new HarborError({ code, message });
	}
}
