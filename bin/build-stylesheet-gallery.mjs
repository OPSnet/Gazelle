#!/usr/bin/env node

import puppeteer from 'puppeteer';
import sharp from 'sharp';
import { copyFileSync, existsSync, mkdirSync, readdirSync, unlinkSync } from 'fs';

import path from 'path';
import { fileURLToPath } from 'url';

// CI environments do not have SIMD vector support for their CPUs, so disable
// this to avoid a warning and any potential issues.
if (process.env.CI) {
    sharp.simd(false);
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const staticPath  = path.join(__dirname, '../public/static');
const stylesPath  = path.join(staticPath, 'styles');
const previewPath = path.join(staticPath, 'stylespreview');

if (!existsSync(stylesPath)) {
    console.error(`Could not find styles path: ${stylesPath}`);
}

if (!existsSync(previewPath)) {
    mkdirSync(previewPath);
}

const styles = [];
const styleCandidates = readdirSync(stylesPath, {withFileTypes: true});
styleCandidates.forEach((entry) => {
    if (!entry.isDirectory) {
        return;
    }
    if (!existsSync(path.join(stylesPath, entry.name, 'style.css'))) {
        return;
    }
    if (entry.name === 'public') {
        return;
    }
    styles.push(entry.name);
});

process.setMaxListeners(styles.length);

async function buildPreview(browser, style) {
    console.log(` -> ${style}`);

    const preview = path.join(stylesPath, style, 'preview.html');
    copyFileSync(path.join(__dirname, '../misc/stylesheet-gallery-base.html'), preview);

    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({width: 1200, height: 1000});
    await page.goto('file://' + preview);

    const output = path.join(previewPath, `full_${style}.png`);
    await page.screenshot({path: output});
    await context.close();
    sharp(output).resize(480, 400).toFile(path.join(previewPath, `thumb_${style}.png`));
    unlinkSync(preview);
}

puppeteer.launch({args: ['--no-sandbox']})
    .then((browser) => {
        Promise.all(styles.map((style) => buildPreview(browser, style))).then(() => {
    }).catch((err) => {
        console.error(err);
        process.exitCode = 1;
    }).finally(() => {
        browser.close();
    });
}).catch((err) => {
    console.error(err);
    process.exitCode = 1;
});

