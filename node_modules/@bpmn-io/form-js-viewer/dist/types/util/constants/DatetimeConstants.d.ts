export const MINUTES_IN_DAY: number;
export namespace DATETIME_SUBTYPES {
    let DATE: string;
    let TIME: string;
    let DATETIME: string;
}
export namespace TIME_SERIALISING_FORMATS {
    let UTC_OFFSET: string;
    let UTC_NORMALIZED: string;
    let NO_TIMEZONE: string;
}
export const DATETIME_SUBTYPES_LABELS: {
    [DATETIME_SUBTYPES.DATE]: string;
    [DATETIME_SUBTYPES.TIME]: string;
    [DATETIME_SUBTYPES.DATETIME]: string;
};
export const TIME_SERIALISINGFORMAT_LABELS: {
    [TIME_SERIALISING_FORMATS.UTC_OFFSET]: string;
    [TIME_SERIALISING_FORMATS.UTC_NORMALIZED]: string;
    [TIME_SERIALISING_FORMATS.NO_TIMEZONE]: string;
};
export const DATETIME_SUBTYPE_PATH: string[];
export const DATE_LABEL_PATH: string[];
export const DATE_DISALLOW_PAST_PATH: string[];
export const TIME_LABEL_PATH: string[];
export const TIME_USE24H_PATH: string[];
export const TIME_INTERVAL_PATH: string[];
export const TIME_SERIALISING_FORMAT_PATH: string[];
