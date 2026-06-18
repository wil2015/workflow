/**
 * If the value is a valid expression, it is evaluated and returned. Otherwise, it is returned as-is.
 * The function is memoized to minimize re-renders.
 *
 * @param {any} value - A static value or expression to evaluate.
 * @returns {any} - Evaluated value or the original value if not an expression.
 */
export function useExpressionEvaluation(value: any): any;
