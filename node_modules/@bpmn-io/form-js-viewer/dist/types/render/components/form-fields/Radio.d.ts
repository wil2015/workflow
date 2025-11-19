export function Radio(props: any): import("preact").JSX.Element;
export namespace Radio {
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
declare const type: "radio";
import { sanitizeSingleSelectValue } from '../util/sanitizerUtil';
export {};
