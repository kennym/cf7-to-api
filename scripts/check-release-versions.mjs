import { readFileSync } from 'node:fs';
import { releaseErrors } from './sync-wordpress-release.mjs';

const version = JSON.parse(readFileSync('package.json', 'utf8')).version;
const errors = releaseErrors({
	version,
	php: readFileSync('contact-form-7-api.php', 'utf8'),
	readme: readFileSync('readme.txt', 'utf8'),
});

if (errors.length > 0) {
	console.error(errors.join('\n'));
	process.exit(1);
}
