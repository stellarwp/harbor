import type { LiquidData } from './liquid-data';

declare global {
    interface Window {
        uplinkData?: LiquidData;
    }
}
