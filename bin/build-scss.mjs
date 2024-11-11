import fs from 'fs';
import path from 'path';
import * as sass from 'sass';

const isProduction = process.env.NODE_ENV === 'production';
const style = isProduction ? 'compressed' : 'expanded';
const sourceMap = isProduction;

const __dirname = import.meta.dirname;

const sassDir = path.join(__dirname, '..', 'sass');
const stylesDir = path.join(__dirname, '..', 'public', 'static', 'styles');

const skipItems = [
  'opendyslexic',
];

for (const item of fs.readdirSync(sassDir, { withFileTypes: true })) {
  let sourceFile;
  let outputFile;
  let outputSourcemap;
  if (skipItems.includes(item.name)) {
    continue;
  }
  if (item.isDirectory()) {
    const outputDir = path.join(stylesDir, item.name);
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir);
    }
    sourceFile = path.join(sassDir, item.name, 'style.scss');
    outputFile = path.join(outputDir, 'style.css');
    outputSourcemap = path.join(outputDir, 'style.css.map');
  } else {
    sourceFile = path.join(sassDir, item.name);
    outputFile = path.join(stylesDir, item.name.replace('.scss', '.css'));
    outputSourcemap = path.join(stylesDir, item.name.replace('.scss', '.css.map'));
  }
  const result = sass.compile(sourceFile, { sourceMap, style });
  fs.writeFileSync(outputFile, result.css);
  if (result.sourceMap) {
    fs.writeFileSync(
      outputSourcemap,
      JSON.stringify({
        ...result.sourceMap,
        sources: result.sourceMap.sources.map((source) => source.replace(/^(.+)\/sass/, './sass')),
      }),
    );
  }
}
