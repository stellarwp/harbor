import type { HarborData } from './harbor-data';

declare global {
    interface Window {
        harborData?: HarborData;
    }
}
