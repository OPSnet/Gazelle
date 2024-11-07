import js from "@eslint/js";
import globals from 'globals';
import stylisticJs from '@stylistic/eslint-plugin-js'

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
            sourceType: "script",
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
            '@stylistic/js': stylisticJs,  // provides checks for whitespace
        },
        rules: {  // degrade these errors to warnings as a rudimentary baseline
            "no-unused-vars": "warn",
            "no-prototype-builtins": "warn",
        },
    }
];
