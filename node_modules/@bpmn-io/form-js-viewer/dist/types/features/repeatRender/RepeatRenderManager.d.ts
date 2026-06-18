export class RepeatRenderManager {
    constructor(form: any, formFields: any, formFieldRegistry: any, pathRegistry: any, eventBus: any);
    _form: any;
    /** @type {import('../../render/FormFields').FormFields} */
    _formFields: import("../../render/FormFields").FormFields;
    /** @type {import('../../core/FormFieldRegistry').FormFieldRegistry} */
    _formFieldRegistry: import("../../core/FormFieldRegistry").FormFieldRegistry;
    /** @type {import('../../core/PathRegistry').PathRegistry} */
    _pathRegistry: import("../../core/PathRegistry").PathRegistry;
    /** @type {import('../../core/EventBus').EventBus} */
    _eventBus: import("../../core/EventBus").EventBus;
    Repeater(props: any): import("preact").JSX.Element;
    RepeatFooter(props: any): import("preact").JSX.Element;
    /**
     * Checks whether a field is currently repeating its children.
     *
     * @param {string} id - The id of the field to check
     * @returns {boolean} - True if repeatable, false otherwise
     */
    isFieldRepeating(id: string): boolean;
    _getNonCollapsedItems(field: any): any;
}
export namespace RepeatRenderManager {
    let $inject: string[];
}
