#!/usr/bin/env node

const fs = require('fs');
const { join } = require('path');
const puppeteer = require('puppeteer');
const sharp = require('sharp');

const rootPath = join(__dirname, '..');
const staticPath = join('public', 'static');
const stylesPath = join(rootPath, staticPath, 'styles');
const stylesPreviewPath = join(rootPath, staticPath, 'stylespreview');

if (!fs.existsSync(stylesPath)) {
    console.error(`Could not find styles path: ${stylesPreviewPath}`);
}

if (!fs.existsSync(stylesPreviewPath)) {
    fs.mkdirSync(stylesPreviewPath);
}

const styles = [];
const styleCandidates = fs.readdirSync(join(staticPath, 'styles'), {withFileTypes: true});
styleCandidates.forEach((entry) => {
    if (!entry.isDirectory) {
        return;
    }
    if (!fs.existsSync(join(stylesPath, entry.name, 'images'))) {
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
    var preview = join(stylesPath, style, 'preview.html');
    var output = join(stylesPreviewPath, `full_${style}.png`);

    fs.copyFileSync(join(__dirname, 'preview_base.html'), preview);

    const context = await browser.createIncognitoBrowserContext();
    const page = await context.newPage();
    await page.setViewport({width: 1200, height: 1000});
    await page.goto('file://' + preview);
    await page.screenshot({path: output});
    await context.close();
    sharp(output).resize(480, 400).toFile(join(stylesPreviewPath, `thumb_${style}.png`));
    fs.unlinkSync(preview);
}

console.log('Building styles:\n');

puppeteer.launch({args: ['--no-sandbox']}).then((browser) => {
    Promise.all(styles.map((style) => buildPreview(browser, style))).then(() => {
        console.log('\nAll style previews built');
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

