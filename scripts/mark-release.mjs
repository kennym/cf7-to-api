import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

export function releaseVersion(version, tags) {
	if (tags.has(version) || tags.has(`v${version}`)) {
		return null;
	}
	return version;
}

function isMain() {
	const entry = process.argv[1];
	return entry !== undefined && import.meta.url === pathToFileURL(entry).href;
}

if (isMain()) {
	const version = JSON.parse(readFileSync('package.json', 'utf8')).version;
	const listed = execFileSync('git', ['tag', '--list'], { encoding: 'utf8' });
	const tags = new Set(listed.split('\n').filter((tag) => tag !== ''));
	const releasing = releaseVersion(version, tags);
	if (releasing !== null) {
		writeFileSync('release-version.txt', `${releasing}\n`);
	}
}
