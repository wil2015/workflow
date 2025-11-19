export function Select(props: any): import("preact").JSX.Element;
export namespace Select {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export let emptyValue: any;
        export { sanitizeSingleSelectValue as sanitizeValue };
        export function create(options?: {}): any;
    }
}
declare const type: "select";
import { sanitizeSingleSelectValue } from '../util/sanitizerUtil';
export {};
