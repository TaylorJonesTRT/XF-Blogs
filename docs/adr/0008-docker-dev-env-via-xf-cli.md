# Development runs in the xf-managed Docker environment

The add-on is developed in `/Users/taylorjones/Development/XenForo/addondev`, a Docker environment managed by the `xf` CLI. All XenForo commands are run as `xf <command>` from the add-on directory, and tests run inside the container with `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'`. This replaces the old `2.3.9/www` setup, which used `../scripts/run_in_container.sh` and ran tests on the host with `XENFORO_HOST=1` against a database port exposed on `127.0.0.1:3307`.

## Consequences

- MySQL isn't exposed to the host, so tests can't run on the host.
- `xf exec` starts in the XenForo root (`/var/www/html`), and the test bootstrap resolves the root as `../../../..` from the current directory. So phpunit has to start from the add-on directory.
- The new environment has no php-cs-fixer, so formatting is manual until one is added.
