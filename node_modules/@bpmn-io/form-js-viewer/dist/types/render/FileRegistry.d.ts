export class FileRegistry {
    /**
     * @param {import('../core/EventBus').EventBus} eventBus
     * @param {import('../core/FormFieldRegistry').FormFieldRegistry} formFieldRegistry
     * @param {import('../core/FormFieldInstanceRegistry').FormFieldInstanceRegistry} formFieldInstanceRegistry
     */
    constructor(eventBus: import("../core/EventBus").EventBus, formFieldRegistry: import("../core/FormFieldRegistry").FormFieldRegistry, formFieldInstanceRegistry: import("../core/FormFieldInstanceRegistry").FormFieldInstanceRegistry);
    /**
     * @param {string} id
     * @param {File[]} files
     */
    setFiles(id: string, files: File[]): void;
    /**
     * @param {string} id
     * @returns {File[]}
     */
    getFiles(id: string): File[];
    /**
     * @returns {string[]}
     */
    getKeys(): string[];
    /**
     * @param {string} id
     * @returns {boolean}
     */
    hasKey(id: string): boolean;
    /**
     * @param {string} id
     */
    deleteFiles(id: string): void;
    /**
     * @returns {Map<string, File[]>}
     */
    getAllFiles(): Map<string, File[]>;
    clear(): void;
    /** @type {Map<string, File[]>} */
    [fileRegistry]: Map<string, File[]>;
    /** @type {import('../core/EventBus').EventBus} */
    [eventBusSymbol]: import("../core/EventBus").EventBus;
    /** @type {import('../core/FormFieldRegistry').FormFieldRegistry} */
    [formFieldRegistrySymbol]: import("../core/FormFieldRegistry").FormFieldRegistry;
    /** @type {import('../core/FormFieldInstanceRegistry').FormFieldInstanceRegistry} */
    [formFieldInstanceRegistrySymbol]: import("../core/FormFieldInstanceRegistry").FormFieldInstanceRegistry;
}
export namespace FileRegistry {
    let $inject: string[];
}
declare const fileRegistry: unique symbol;
declare const eventBusSymbol: unique symbol;
declare const formFieldRegistrySymbol: unique symbol;
declare const formFieldInstanceRegistrySymbol: unique symbol;
export {};
