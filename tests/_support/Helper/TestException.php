<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests;

use RuntimeException;

/**
 * Thrown by a test to stop execution just before code under test calls exit().
 *
 * Suppressing exit() itself (uopz_allow_exit) lets a failing test keep running
 * past the point it should have stopped, which can leave a failure unreported.
 * Instead, a test mocks the call immediately before the exit() — usually
 * wp_safe_redirect() — to throw this, and asserts inside the catch block.
 *
 * @since TBD
 */
final class TestException extends RuntimeException {

}
