import { spawnSync } from 'node:child_process';

function run(args) {
	const result = spawnSync('node', args, { stdio: 'inherit' });
	if (result.status !== 0) {
		process.exit(result.status === null ? 1 : result.status);
	}
}

run(['node_modules/@changesets/cli/bin.js', 'version']);
run(['scripts/sync-wordpress-release.mjs']);
