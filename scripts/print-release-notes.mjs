import { readFileSync } from 'node:fs';
import { githubNotes } from './sync-wordpress-release.mjs';

const version = JSON.parse(readFileSync('package.json', 'utf8')).version;
process.stdout.write(githubNotes(readFileSync('readme.txt', 'utf8'), version));
