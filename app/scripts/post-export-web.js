// Runs after `expo export --platform web`: the UI is German, so is the document.
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', '..', 'api', 'public', 'app', 'index.html');
const html = fs.readFileSync(file, 'utf8');
fs.writeFileSync(file, html.replace(/<html lang="[^"]*"/, '<html lang="de"'));
console.log('index.html: lang="de"');
