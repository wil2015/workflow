/**
 * @typedef Props
 * @property {string} id
 * @property {string[]} errors
 *
 * @param {Props} props
 * @returns {import("preact").JSX.Element}
 */
export function Errors(props: Props): import("preact").JSX.Element;
export type Props = {
    id: string;
    errors: string[];
};
