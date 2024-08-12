import js from "@eslint/js";
import globals from 'globals';
import stylisticJs from '@stylistic/eslint-plugin-js'

export default [
    {
        ignores: [
            "public/static/**/*.min.js",
            "public/static/functions/jquery.js",
            "public/static/functions/jquery.*.js",
            "public/static/functions/jquery-migrate.js",
            "public/static/functions/jquery-ui.js",
            "public/static/functions/highcharts.js",
            "public/static/functions/highcharts-accessibility.js",
            "public/static/functions/highmaps.js",
            "public/static/functions/tooltipster.js",
            "public/static/functions/tagcanvas.js",
            "public/static/functions/noty/**/*.js",
            "public/static/assets/**"
        ]
    },
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "script",
            globals: {
                ...globals.browser,
                "$": "readonly",
                "jQuery": "readonly",
                "katex": "readonly",
                "authkey": "readonly",
                "userid": "readonly",
            }
        },
        plugins: {
            '@stylistic/js': stylisticJs,  // provides checks for whitespace
        },
        rules: {  // degrade these errors to warnings as a rudimentary baseline
            "no-undef": "warn",
            "no-unused-vars": "warn",
            "no-prototype-builtins": "warn",
        },
    }
];
