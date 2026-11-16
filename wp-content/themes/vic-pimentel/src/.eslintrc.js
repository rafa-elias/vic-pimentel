module.exports = {
    env: {
        browser: true,
        commonjs: true,
        es6: true,
        node: true,
        jquery: true
    },
    rules: {
        'arrow-parens': [2, 'as-needed'],
        'quote-props': [2, 'consistent'],
        'semi': [2, 'always'],
        'quotes': [2, 'single'],
        'comma-dangle': [2, 'only-multiline'],
        'array-bracket-spacing': [2, 'never'],
        'no-var': 2,
        'no-multiple-empty-lines': [2, {max: 1, maxEOF: 1}]
    },
    parserOptions: {
        sourceType: 'module',
        ecmaVersion: 9
    }
};
