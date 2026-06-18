export function IFrame(props: any): import("preact").JSX.Element;
export namespace IFrame {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export function create(options?: {}): {
            label: string;
            security: {
                allowScripts: boolean;
            };
        };
    }
}
declare const type: "iframe";
export {};
