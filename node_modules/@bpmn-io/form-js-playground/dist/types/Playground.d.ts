export type FormProperties = import("@bpmn-io/form-js-viewer/dist/types/types").FormProperties;
export type FormEditorProperties = import("@bpmn-io/form-js-editor/dist/types/types").FormEditorProperties;
export type FormPlaygroundOptions = {
    actions?: {
        display: boolean;
    };
    additionalModules?: Array<any>;
    container?: Element;
    data: any;
    editor?: {
        inlinePropertiesPanel: boolean;
    };
    editorAdditionalModules?: Array<any>;
    editorProperties?: FormEditorProperties;
    exporter?: {
        name: string;
        version: string;
    };
    propertiesPanel?: {
        parent: Element;
        feelPopupContainer: Element;
    };
    schema: any;
    viewerAdditionalModules?: Array<any>;
    viewerProperties?: FormProperties;
};
/**
 * @typedef { import('@bpmn-io/form-js-viewer/dist/types/types').FormProperties } FormProperties
 * @typedef { import('@bpmn-io/form-js-editor/dist/types/types').FormEditorProperties } FormEditorProperties
 *
 * @typedef { {
 *  actions?: { display: Boolean }
 *  additionalModules?: Array<any>
 *  container?: Element
 *  data: any
 *  editor?: { inlinePropertiesPanel: Boolean }
 *  editorAdditionalModules?: Array<any>
 *  editorProperties?: FormEditorProperties
 *  exporter?: { name: String, version: String }
 *  propertiesPanel?: { parent: Element, feelPopupContainer: Element }
 *  schema: any
 *  viewerAdditionalModules?: Array<any>
 *  viewerProperties?: FormProperties
 * } } FormPlaygroundOptions
 */
/**
 * @param {FormPlaygroundOptions} options
 */
export function Playground(options: FormPlaygroundOptions): void;
export class Playground {
    /**
     * @typedef { import('@bpmn-io/form-js-viewer/dist/types/types').FormProperties } FormProperties
     * @typedef { import('@bpmn-io/form-js-editor/dist/types/types').FormEditorProperties } FormEditorProperties
     *
     * @typedef { {
     *  actions?: { display: Boolean }
     *  additionalModules?: Array<any>
     *  container?: Element
     *  data: any
     *  editor?: { inlinePropertiesPanel: Boolean }
     *  editorAdditionalModules?: Array<any>
     *  editorProperties?: FormEditorProperties
     *  exporter?: { name: String, version: String }
     *  propertiesPanel?: { parent: Element, feelPopupContainer: Element }
     *  schema: any
     *  viewerAdditionalModules?: Array<any>
     *  viewerProperties?: FormProperties
     * } } FormPlaygroundOptions
     */
    /**
     * @param {FormPlaygroundOptions} options
     */
    constructor(options: FormPlaygroundOptions);
    on: {
        <Key extends import("mitt").EventType>(type: Key, handler: import("mitt").Handler<Record<import("mitt").EventType, unknown>[Key]>): void;
        (type: "*", handler: import("mitt").WildcardHandler<Record<import("mitt").EventType, unknown>>): void;
    };
    off: {
        <Key extends import("mitt").EventType>(type: Key, handler?: import("mitt").Handler<Record<import("mitt").EventType, unknown>[Key]>): void;
        (type: "*", handler: import("mitt").WildcardHandler<Record<import("mitt").EventType, unknown>>): void;
    };
    emit: {
        <Key extends import("mitt").EventType>(type: Key, event: Record<import("mitt").EventType, unknown>[Key]): void;
        <Key extends import("mitt").EventType>(type: undefined extends Record<import("mitt").EventType, unknown>[Key] ? Key : never): void;
    };
    destroy: () => void;
    getState: (...args: any[]) => any;
    getSchema: (...args: any[]) => any;
    setSchema: (...args: any[]) => any;
    saveSchema: (...args: any[]) => any;
    get: (...args: any[]) => any;
    getDataEditor: (...args: any[]) => any;
    getEditor: (...args: any[]) => any;
    getForm: (...args: any[]) => any;
    getResultView: (...args: any[]) => any;
    attachEditorContainer: (...args: any[]) => any;
    attachPreviewContainer: (...args: any[]) => any;
    attachDataContainer: (...args: any[]) => any;
    attachResultContainer: (...args: any[]) => any;
    attachPaletteContainer: (...args: any[]) => any;
    attachPropertiesPanelContainer: (...args: any[]) => any;
}
