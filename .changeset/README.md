# Changesets

A file here is a release that has not shipped yet. Add one with `npx changeset`. Pushing it to `master` opens a release pull request. Merging that pull request tags the plugin and publishes it.

`patch` is a fix, `minor` is a new feature, and `major` is a breaking change. The sentence in the file is the changelog line. Put `Upgrade notice:` on its own line when WordPress should show a new upgrade notice. Otherwise the previous notice is copied forward.

A change that should not release uses `npx changeset --empty`.
