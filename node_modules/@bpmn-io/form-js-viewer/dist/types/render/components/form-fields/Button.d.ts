export function Button(props: any): import("preact").JSX.Element;
export namespace Button {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let name: string;
        export let group: string;
        export function create(options?: {}): {
            label: string;
            action: string;
        };
    }
}
declare const type: "button";
export {};
