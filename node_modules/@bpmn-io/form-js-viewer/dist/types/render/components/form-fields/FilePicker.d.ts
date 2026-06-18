/**
 * @typedef Props
 * @property {(props: { value: string }) => void} onChange
 * @property {string} domId
 * @property {string[]} errors
 * @property {boolean} disabled
 * @property {boolean} readonly
 * @property {boolean} required
 * @property {Object} field
 * @property {string} field.id
 * @property {string} [field.label]
 * @property {string} [field.accept]
 * @property {string|boolean} [field.multiple]
 * @property {Object} [field.validate]
 * @property {boolean} [field.validate.required]
 * @property {string} [value]
 *
 * @param {Props} props
 * @returns {import("preact").JSX.Element}
 */
export function FilePicker(props: Props): import("preact").JSX.Element;
export namespace FilePicker {
    namespace config {
        let type: string;
        let keyed: boolean;
        let label: string;
        let group: string;
        let emptyValue: any;
        function create(options?: {}): {};
    }
}
export type Props = {
    onChange: (props: {
        value: string;
    }) => void;
    domId: string;
    errors: string[];
    disabled: boolean;
    readonly: boolean;
    required: boolean;
    field: {
        id: string;
        label?: string;
        accept?: string;
        multiple?: string | boolean;
        validate?: {
            required?: boolean;
        };
    };
    value?: string;
};
