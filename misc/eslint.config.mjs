import js from "@eslint/js";
import globals from 'globals';
import stylistic from '@stylistic/eslint-plugin'

export default [
    {
        ignores: [
            "public/static/assets/**",
            "public/static/vendor/**",
        ]
    },
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            globals: {
                ...globals.browser,
                "$":       "readonly",
                "jQuery":  "readonly",
                "katex":   "readonly",
                "authkey": "readonly",
                "userid":  "readonly",
            }
        },
        plugins: {
            '@stylistic': stylistic,  // provides checks for whitespace
        },
        rules: {  // degrade these errors to warnings as a rudimentary baseline
            "no-unused-vars": "warn",
            "no-prototype-builtins": "warn",
        },
    },
    {
        files: ["**/*.js"],
        languageOptions: {
            sourceType: "script",
        },
    },
    {
        files: ["**/*.mjs"],
        languageOptions: {
            sourceType: "module",
            globals: {
                ...globals.browser,
                "$":       "readonly",
                "gazURL":  "readonly",
            }
        },
    }
];
