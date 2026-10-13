import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	githubNotes,
	parseChangelog,
	releaseErrors,
	syncPluginPhp,
	syncReadme,
	syncRelease,
} from './sync-wordpress-release.mjs';
import { releaseVersion } from './mark-release.mjs';

const php = ` * Version:           1.5.3
 * @since             1.0.0
define( 'QS_CF7_API_VERSION', '1.5.3' );
`;

const readme = `Stable tag: 1.5.3

== Upgrade Notice ==

= 1.5.3 =
Keep the previous warning.

= 1.5.0 =
Original warning.

== Changelog ==

= 1.5.3 =

* Already published.

= 1.5.0 =

* Older entry.
`;

const changelog = `# cf7-to-api

## 1.5.4

### Patch Changes

- abc1234: Trim surrounding whitespace from the saved API URL.
  An encoded %20 stays put.

## 1.5.3

### Patch Changes

- old1234: Already published.
`;

describe('syncRelease', () => {
	it('bumps the header, constant, stable tag, and changelog', () => {
		const synced = syncRelease({ version: '1.5.4', changelog, php, readme });

		assert.match(synced.php, /Version:\s+1\.5\.4/);
		assert.match(synced.php, /QS_CF7_API_VERSION',\s*'1\.5\.4'/);
		assert.match(synced.php, /@since\s+1\.0\.0/);
		assert.doesNotMatch(synced.php, /QS_CF7_API_VERSION',\s*'1\.5\.3'/);

		assert.match(synced.readme, /^Stable tag: 1\.5\.4$/m);
		assert.match(synced.readme, /== Changelog ==\n\n= 1\.5\.4 =\n\n\* Trim surrounding whitespace from the saved API URL\. An encoded %20 stays put\.\n\n= 1\.5\.3 =/);
		assert.match(synced.readme, /\* Older entry\./);
		assert.match(synced.readme, /== Upgrade Notice ==\n\n= 1\.5\.4 =\nKeep the previous warning\.\n\n= 1\.5\.3 =/);
		assert.deepEqual(releaseErrors({ version: '1.5.4', php: synced.php, readme: synced.readme }), []);
	});

	it('uses an Upgrade notice line from the changelog', () => {
		const withNotice = changelog.replace(
			'  An encoded %20 stays put.\n',
			'  An encoded %20 stays put.\n  Upgrade notice: Saved URLs no longer keep a trailing space.\n',
		);
		const synced = syncRelease({ version: '1.5.4', changelog: withNotice, php, readme });

		assert.match(synced.readme, /= 1\.5\.4 =\nSaved URLs no longer keep a trailing space\.\n\n= 1\.5\.3 =/);
		assert.doesNotMatch(synced.readme, /Upgrade notice:/);
		assert.match(synced.readme, /\* Trim surrounding whitespace from the saved API URL\. An encoded %20 stays put\./);
	});

	it('does not insert the same version twice', () => {
		const once = syncRelease({ version: '1.5.4', changelog, php, readme });
		const twice = syncReadme(
			once.readme,
			'1.5.4',
			['Trim surrounding whitespace from the saved API URL. An encoded %20 stays put.'],
			null,
		);

		assert.equal(twice, once.readme);
		assert.equal(syncPluginPhp(once.php, '1.5.4'), once.php);
	});

	it('rejects a changelog without the new version', () => {
		assert.throws(() => parseChangelog(changelog, '9.9.9'), /CHANGELOG\.md has no 9\.9\.9 section/);
	});
});

describe('release notes and tags', () => {
	it('prints the new changelog bullets and the zip warning', () => {
		const synced = syncRelease({ version: '1.5.4', changelog, php, readme });
		const notes = githubNotes(synced.readme, '1.5.4');

		assert.match(notes, /^\* Trim surrounding whitespace/);
		assert.match(notes, /cf7-to-api-basic-auth/);
		assert.doesNotMatch(notes, /Already published/);
	});

	it('skips a version that already has a tag', () => {
		assert.equal(releaseVersion('1.5.3', new Set(['1.5.3'])), null);
		assert.equal(releaseVersion('1.5.3', new Set(['v1.5.3'])), null);
		assert.equal(releaseVersion('1.5.4', new Set(['1.5.3', 'v1.5.3'])), '1.5.4');
	});

	it('reports a readme that was not synced', () => {
		const errors = releaseErrors({ version: '1.5.4', php: syncPluginPhp(php, '1.5.4'), readme });
		assert.ok(errors.some((error) => error.includes('Stable tag')));
		assert.ok(errors.some((error) => error.includes('changelog')));
	});
});
