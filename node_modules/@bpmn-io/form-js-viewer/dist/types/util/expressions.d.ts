/**
 * Transform a LocalExpressionContext object into a usable FEEL context.
 *
 * @param {Object} context - The LocalExpressionContext object.
 * @returns {Object} The usable FEEL context.
 */
export function buildExpressionContext(context: any): any;
/**
 * If the value is a valid expression, it is evaluated and returned. Otherwise, it is returned as-is.
 *
 * @param {any} expressionLanguage - The expression language to use.
 * @param {any} value - The static value or expression to evaluate.
 * @param {Object} expressionContextInfo - The context information to use.
 * @returns {any} - Evaluated value or the original value if not an expression.
 */
export function runExpressionEvaluation(expressionLanguage: any, value: any, expressionContextInfo: any): any;
