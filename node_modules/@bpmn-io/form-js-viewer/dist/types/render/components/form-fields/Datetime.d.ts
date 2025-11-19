export function Datetime(props: any): import("preact").JSX.Element;
export namespace Datetime {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export let emptyValue: any;
        export { sanitizeDateTimePickerValue as sanitizeValue };
        export function create(options: {}, isNewField: any): {};
        export function getSubheading(field: any): any;
    }
}
declare const type: "datetime";
import { sanitizeDateTimePickerValue } from '../util/sanitizerUtil';
export {};
