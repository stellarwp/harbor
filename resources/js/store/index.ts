/**
 * Registers the lw @wordpress/data store.
 *
 * Call registerHarborStore() once before createRoot() in index.tsx.
 * Consumers import the store descriptor and use useSelect / useDispatch.
 *
 * @package LiquidWeb\Harbor
 */
import { createReduxStore, register } from '@wordpress/data';
import { reducer } from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';
import { STORE_NAME } from './constants';

export const store = createReduxStore(STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
});

export function registerHarborStore(): void {
	register(store);
}

export { STORE_NAME };
