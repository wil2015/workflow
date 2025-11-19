export function Taglist(props: any): import("preact").JSX.Element;
export namespace Taglist {
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
declare const type: "taglist";
import { sanitizeMultiSelectValue } from '../util/sanitizerUtil';
export {};
