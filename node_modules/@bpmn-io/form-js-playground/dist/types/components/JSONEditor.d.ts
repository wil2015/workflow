/**
 * @param {object} options
 * @param {boolean} [options.readonly]
 * @param {object} [options.contentAttributes]
 * @param {string | HTMLElement} [options.placeholder]
 */
export function JSONEditor(options?: {
    readonly?: boolean;
    contentAttributes?: object;
    placeholder?: string | HTMLElement;
}): void;
export class JSONEditor {
    /**
     * @param {object} options
     * @param {boolean} [options.readonly]
     * @param {object} [options.contentAttributes]
     * @param {string | HTMLElement} [options.placeholder]
     */
    constructor(options?: {
        readonly?: boolean;
        contentAttributes?: object;
        placeholder?: string | HTMLElement;
    });
    setValue: (newValue: any) => void;
    getValue: () => string;
    setVariables: (variables: any) => void;
    getView: () => EditorView;
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
    attachTo: (_container: any) => void;
    destroy: () => void;
}
import { EditorView } from '@codemirror/view';
