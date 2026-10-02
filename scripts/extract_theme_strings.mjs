import fs from 'fs';
import path from 'path';

const themeDir = '/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/helmetsan-theme';
const files = [];

function walk(dir) {
  for (const f of fs.readdirSync(dir)) {
    const full = path.join(dir, f);
    if (fs.statSync(full).isDirectory()) {
      if (f !== 'node_modules' && f !== 'vendor' && f !== 'assets' && f !== 'data') {
        walk(full);
      }
    } else if (f.endsWith('.php')) {
      files.push(full);
    }
  }
}
walk(themeDir);

const regex = /(?:hs_e|hs_attr_e|hs_t|__|_e|_x|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(\s*(['"])((?:(?!\1)[^\\]|\\.)*)\1/g;
const foundStrings = new Set();

for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');
  let match;
  while ((match = regex.exec(content)) !== null) {
    const str = match[2].replace(/\\([\\'"nrt])/g, '$1').trim();
    if (str && str !== 'helmetsan-theme' && str.length > 1) {
      foundStrings.add(str);
    }
  }
}

console.log('Total unique gettext strings extracted from theme:', foundStrings.size);

const existingPath = path.join(themeDir, 'languages/theme_strings.json');
const existing = new Set(JSON.parse(fs.readFileSync(existingPath, 'utf8')));
const missing = [...foundStrings].filter(s => !existing.has(s));

console.log('Existing in theme_strings.json:', existing.size);
console.log('Missing strings from theme_strings.json:', missing.length);

fs.writeFileSync('/tmp/all_theme_strings.json', JSON.stringify([...foundStrings].sort(), null, 2));
fs.writeFileSync('/tmp/missing_theme_strings.json', JSON.stringify(missing.sort(), null, 2));
console.log('Sample missing strings (up to 30):');
console.log(missing.slice(0, 30));
