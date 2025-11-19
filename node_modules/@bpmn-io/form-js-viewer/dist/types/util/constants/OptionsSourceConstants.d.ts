export function getOptionsSource(field: any): string;
export namespace OPTIONS_SOURCES {
    let STATIC: string;
    let INPUT: string;
    let EXPRESSION: string;
}
export const OPTIONS_SOURCE_DEFAULT: string;
export const OPTIONS_SOURCES_LABELS: {
    [OPTIONS_SOURCES.STATIC]: string;
    [OPTIONS_SOURCES.INPUT]: string;
    [OPTIONS_SOURCES.EXPRESSION]: string;
};
export const OPTIONS_SOURCES_PATHS: {
    [OPTIONS_SOURCES.STATIC]: string[];
    [OPTIONS_SOURCES.INPUT]: string[];
    [OPTIONS_SOURCES.EXPRESSION]: string[];
};
export const OPTIONS_SOURCES_DEFAULTS: {
    [OPTIONS_SOURCES.STATIC]: {
        label: string;
        value: string;
    }[];
    [OPTIONS_SOURCES.INPUT]: string;
    [OPTIONS_SOURCES.EXPRESSION]: string;
};
