/**
 * @typedef DocumentEndpointBuilder
 * @property {(document: DocumentMetadata) => string} buildUrl
 */
/**
 * @typedef DocumentMetadata
 * @property {string} documentId
 * @property {string} endpoint
 * @property {Object} metadata
 * @property {string|undefined} [metadata.contentType]
 * @property {string} metadata.fileName
 *
 * @typedef Field
 * @property {string} id
 * @property {string} [title]
 * @property {string} [dataSource]
 * @property {number} [maxHeight]
 * @property {string} [label]
 *
 * @typedef Props
 * @property {Field} field
 * @property {string} domId
 *
 * @param {Props} props
 * @returns {import("preact").JSX.Element}
 */
export function DocumentPreview(props: Props): import("preact").JSX.Element;
export namespace DocumentPreview {
    namespace config {
        export { type };
        export let keyed: boolean;
        export let group: string;
        export let name: string;
        export function create(options?: {}): {
            label: string;
        };
    }
}
export type DocumentEndpointBuilder = {
    buildUrl: (document: DocumentMetadata) => string;
};
export type DocumentMetadata = {
    documentId: string;
    endpoint: string;
    metadata: {
        contentType?: string | undefined;
        fileName: string;
    };
};
export type Field = {
    id: string;
    title?: string;
    dataSource?: string;
    maxHeight?: number;
    label?: string;
};
export type Props = {
    field: Field;
    domId: string;
};
export type GetErrorOptions = {
    dataSource: string | undefined;
};
declare const type: "documentPreview";
export {};
