import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import * as sass from 'sass';

const isProduction = process.env.NODE_ENV === 'production';

const style     = isProduction ? 'compressed' : 'expanded';
const sourceMap = isProduction;
const __dirname = import.meta.dirname;
const rootDir   = path.join(__dirname, '..');
const sassDir   = path.join(rootDir, 'sass');
const stylesDir = path.join(rootDir, 'public', 'static', 'styles');

if (process.argv.length < 3) {
  console.error('config filename not specified');
  process.exit(1);
}

const loadJSON = (path) => JSON.parse(fs.readFileSync(new URL(path, import.meta.url)));
const config   = loadJSON(process.argv[2]);

const skipItems = [
  'opendyslexic',
];

const sassOptions = {
  functions: {
    'config($key)': (args) => {
      const key = args[0].assertString('key').toString().replace(/^['"]+|['"]+$/g, '');
      if (!config[key]) {
        throw new Error(`Unknown config key: ${key}`);
      }
      return new sass.SassString(config[key]);
    },
  },
  sourceMap,
  style,
}

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
  const result = sass.compile(sourceFile, sassOptions);
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
