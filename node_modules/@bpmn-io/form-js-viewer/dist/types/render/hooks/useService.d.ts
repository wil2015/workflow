/**
 * @template T
 * @param {string} type
 * @param {boolean} [strict=true]
 * @returns {T | null}
 */
export function useService<T>(type: string, strict?: boolean): T | null;
