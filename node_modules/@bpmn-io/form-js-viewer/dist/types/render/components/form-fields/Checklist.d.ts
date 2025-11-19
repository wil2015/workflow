export function Checklist(props: any): import("preact").JSX.Element;
export namespace Checklist {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export let emptyValue: any[];
        export { sanitizeMultiSelectValue as sanitizeValue };
        export function create(options?: {}): any;
    }
}
declare const type: "checklist";
import { sanitizeMultiSelectValue } from '../util/sanitizerUtil';
export {};
