## Summary

Say what changed.

## Release

Add a file in `.changeset/` with `npx changeset`, or commit the markdown yourself.

- `patch` fixes a bug.
- `minor` adds a feature.
- `major` is a breaking change.

The sentence in that file is the changelog line. To show a new WordPress upgrade notice, put `Upgrade notice:` on its own line.

If this pull request should not be released, add an empty changeset with `npx changeset --empty`.

The Release check fails when the plugin files change and this pull request has no changeset.
