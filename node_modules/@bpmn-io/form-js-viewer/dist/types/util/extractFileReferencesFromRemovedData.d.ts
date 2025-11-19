export type RemovedData = Record<PropertyKey, unknown>;
/**
 * @typedef {Record<PropertyKey, unknown>} RemovedData
 * @param {RemovedData} removedData
 * @returns {string[]}
 */
export function extractFileReferencesFromRemovedData(removedData: RemovedData): string[];
