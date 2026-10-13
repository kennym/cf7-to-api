import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const UPGRADE_HEADING = '== Upgrade Notice ==';
const CHANGELOG_HEADING = '== Changelog ==';

function escapeRegExp(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function sectionBody(readme, heading) {
	const bounds = sectionBounds(readme, heading);
	return readme.slice(bounds.bodyStart, bounds.bodyEnd);
}

function sectionBounds(readme, heading) {
	const start = readme.indexOf(heading);
	if (start === -1) {
		throw new Error(`readme.txt is missing ${heading}`);
	}
	const bodyStart = start + heading.length;
	const next = readme.indexOf('\n== ', bodyStart);
	return {
		bodyStart,
		bodyEnd: next === -1 ? readme.length : next,
	};
}

function hasVersionHeading(body, version) {
	return new RegExp(`^= ${escapeRegExp(version)} =$`, 'm').test(body);
}

function firstNoticeBody(body) {
	const match = body.match(/^= [^\n]+ =\n([\s\S]*?)(?=\n= |\s*$)/m);
	if (!match) {
		throw new Error('readme.txt Upgrade Notice has no version block');
	}
	return match[1].trim();
}

function insertBlock(readme, heading, block) {
	const { bodyStart } = sectionBounds(readme, heading);
	const rest = readme.slice(bodyStart).replace(/^\n+/, '\n\n');
	return `${readme.slice(0, bodyStart)}\n\n${block.trim()}${rest}`;
}

export function versionSection(changelog, version) {
	const match = new RegExp(`^## ${escapeRegExp(version)}\\s*$`, 'm').exec(changelog);
	if (!match) {
		throw new Error(`CHANGELOG.md has no ${version} section`);
	}
	const rest = changelog.slice(match.index + match[0].length);
	const next = rest.search(/^## /m);
	return next === -1 ? rest : rest.slice(0, next);
}

function cleanBullet(lines) {
	const notices = [];
	const text = [];
	lines.forEach((line, index) => {
		const trimmed = line.trim();
		if (trimmed === '') {
			return;
		}
		const source = index === 0 ? trimmed.replace(/^[0-9a-f]{7,40}:\s+/i, '') : trimmed;
		if (source.startsWith('Upgrade notice:')) {
			notices.push(source.slice('Upgrade notice:'.length).trim());
			return;
		}
		text.push(source);
	});
	return {
		text: text.join(' ').trim(),
		notice: notices.join('\n').trim(),
	};
}

export function parseChangelog(changelog, version) {
	const lines = versionSection(changelog, version).split('\n');
	const groups = [];
	let current = null;
	for (const line of lines) {
		if (line.startsWith('- ')) {
			if (current) {
				groups.push(current);
			}
			current = [line.slice(2)];
			continue;
		}
		if (current && (line.startsWith('  ') || line.trim() === '')) {
			current.push(line);
		}
	}
	if (current) {
		groups.push(current);
	}
	const bullets = [];
	const notices = [];
	for (const group of groups) {
		const parsed = cleanBullet(group);
		if (parsed.text !== '' && !parsed.text.startsWith('Updated dependencies')) {
			bullets.push(parsed.text);
		}
		if (parsed.notice !== '') {
			notices.push(parsed.notice);
		}
	}
	if (bullets.length === 0) {
		throw new Error(`CHANGELOG.md ${version} has no release notes`);
	}
	return {
		bullets,
		notice: notices.length === 0 ? null : notices.join('\n'),
	};
}

export function syncPluginPhp(php, version) {
	const headerPattern = /^(\s*\*\s*Version:\s+)\S+/m;
	if (!headerPattern.test(php)) {
		throw new Error('plugin header Version line was not found');
	}
	const constantPattern = /(define\(\s*'QS_CF7_API_VERSION',\s*')[^']+(')/;
	if (!constantPattern.test(php)) {
		throw new Error('QS_CF7_API_VERSION was not found');
	}
	return php.replace(headerPattern, `$1${version}`).replace(constantPattern, `$1${version}$2`);
}

export function syncReadme(readme, version, bullets, notice) {
	const stablePattern = /^Stable tag:\s*\S+/m;
	if (!stablePattern.test(readme)) {
		throw new Error('Stable tag was not found');
	}
	let next = readme.replace(stablePattern, `Stable tag: ${version}`);
	const changelogBody = sectionBody(next, CHANGELOG_HEADING);
	if (!hasVersionHeading(changelogBody, version)) {
		const lines = bullets.map((bullet) => `* ${bullet}`);
		next = insertBlock(next, CHANGELOG_HEADING, `= ${version} =\n\n${lines.join('\n')}`);
	}
	const upgradeBody = sectionBody(next, UPGRADE_HEADING);
	if (!hasVersionHeading(upgradeBody, version)) {
		const copied = notice ?? firstNoticeBody(upgradeBody);
		next = insertBlock(next, UPGRADE_HEADING, `= ${version} =\n${copied}`);
	}
	return next;
}

export function syncRelease({ version, changelog, php, readme }) {
	const parsed = parseChangelog(changelog, version);
	return {
		php: syncPluginPhp(php, version),
		readme: syncReadme(readme, version, parsed.bullets, parsed.notice),
	};
}

export function releaseErrors({ version, php, readme }) {
	const errors = [];
	const header = php.match(/^\s*\*\s*Version:\s+(\S+)/m);
	if (!header || header[1] !== version) {
		errors.push(`plugin header is ${header ? header[1] : 'missing'}, expected ${version}`);
	}
	const constant = php.match(/define\(\s*'QS_CF7_API_VERSION',\s*'([^']+)'/);
	if (!constant || constant[1] !== version) {
		errors.push(`QS_CF7_API_VERSION is ${constant ? constant[1] : 'missing'}, expected ${version}`);
	}
	const stable = readme.match(/^Stable tag:\s*(\S+)/m);
	if (!stable || stable[1] !== version) {
		errors.push(`Stable tag is ${stable ? stable[1] : 'missing'}, expected ${version}`);
	}
	if (!hasVersionHeading(sectionBody(readme, CHANGELOG_HEADING), version)) {
		errors.push(`readme changelog has no ${version} section`);
	}
	return errors;
}

export function githubNotes(readme, version) {
	const body = sectionBody(readme, CHANGELOG_HEADING);
	const match = body.match(new RegExp(`^= ${escapeRegExp(version)} =\\n\\n([\\s\\S]*?)(?=\\n= |\\s*$)`, 'm'));
	if (!match) {
		throw new Error(`readme changelog has no ${version} section`);
	}
	return [
		match[1].trim(),
		'',
		'The attached zip is the installable package. It unpacks as `cf7-to-api-basic-auth`. The source archive unpacks as `cf7-to-api`.',
		'',
	].join('\n');
}

function isMain() {
	const entry = process.argv[1];
	return entry !== undefined && import.meta.url === pathToFileURL(entry).href;
}

if (isMain()) {
	const version = JSON.parse(readFileSync('package.json', 'utf8')).version;
	const synced = syncRelease({
		version,
		changelog: readFileSync('CHANGELOG.md', 'utf8'),
		php: readFileSync('contact-form-7-api.php', 'utf8'),
		readme: readFileSync('readme.txt', 'utf8'),
	});
	writeFileSync('contact-form-7-api.php', synced.php);
	writeFileSync('readme.txt', synced.readme);
}
