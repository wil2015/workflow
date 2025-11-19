export class FormFieldInstanceRegistry {
    constructor(eventBus: any, formFieldRegistry: any, formFields: any);
    _eventBus: any;
    _formFieldRegistry: any;
    _formFields: any;
    _formFieldInstances: {};
    syncInstance(instanceId: any, formFieldInfo: any): any;
    cleanupInstance(instanceId: any): void;
    get(instanceId: any): any;
    getAll(): any[];
    getAllKeyed(): any[];
    clear(): void;
}
export namespace FormFieldInstanceRegistry {
    let $inject: string[];
}
