/**
 * If the value is a valid expression, we evaluate it. Otherwise, we continue with the value as-is.
 * If the resulting value isn't a boolean, we return 'false'
 * The function is memoized to minimize re-renders.
 *
 * @param {boolean | string} value - A static boolean or expression to evaluate.
 * @returns {boolean} - Evaluated boolean result.
 */
export function useBooleanExpressionEvaluation(value: boolean | string): boolean;
