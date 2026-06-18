export function Textfield(props: any): import("preact").JSX.Element;
export namespace Textfield {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export let emptyValue: string;
        export function sanitizeValue({ value }: {
            value: any;
        }): string;
        export function create(options?: {}): {
            label: string;
        };
    }
}
declare const type: "textfield";
export {};
