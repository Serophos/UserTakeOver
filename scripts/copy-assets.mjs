import {copyFile} from 'node:fs/promises';
await copyFile('node_modules/@varvet/tiny-autocomplete/src/tiny-autocomplete.js', 'js/dist/tiny-autocomplete.js');
