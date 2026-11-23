# Releases are built only by XenForo's release builder

Release zips are built with `xf xf-addon:build-release TaylorJ/Blogs`, never by zipping the add-on folder. The add-on directory holds dev-only material (vendor/, tests/, phpunit config, agent docs, ADRs), and the `exec` steps in `build.json` remove it from XenForo's temporary `_build` copy. Every release is checked with the `zipinfo` grep in `README.md`. Version numbers change only through `xf xf-addon:bump-version`, so `addon.json`, the `_output` metadata, and upgrade steps stay consistent.

## Consequences

- Any new dev-only file or directory at the add-on root must be added to `build.json` and to the README zip check.
