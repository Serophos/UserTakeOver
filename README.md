# UserTakeOver (STWSH)

UserTakeOver (STWSH) is the STWSH fork of UserTakeOver. It lets authorized ILIAS users take over another account to investigate permissions and other problems, then return to their original account. Administrators can configure eligible global roles, group membership restrictions, allowed target roles, and whether administrator accounts may be taken over.

The current fork release is **7.0.2**. The ILIAS 11 migration bumped the original version from 6.1.2 to 7.0.0; 7.0.1 added the STWSH branding and fixed metabar template loading under the public web root. Version 7.0.2 applies the fork name to the ILIAS plugin overview and records Studentenwerk SH as the responsible maintainer.

The display name is **UserTakeOver (STWSH)**. The plugin overview and configuration use this display name. ILIAS's plugin administration actions and CLI still use the technical name `UserTakeOver`, and the plugin ID remains `usrto`. These identifiers also determine PHP class names and installation paths, so they are retained to preserve registration and data.

## Requirements

- ILIAS 11.x; release 7.0.0 has been verified against ILIAS 11.4.
- PHP 8.3 or 8.4 and the extensions required by ILIAS.
- Composer 2 for the plugin autoloader. There are no third-party PHP runtime dependencies.
- For development: Node `^22.18 || ^24.0`, npm `^10.9.3 || ^11.3`.

ILIAS 9 and 10 require an earlier plugin release.

## Installation and update

Install this repository at the following path relative to the ILIAS installation root:

```text
public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/UserTakeOver
```

Back up the plugin, its tables (`ui_uihk_usrto_grp`, `ui_uihk_usrto_member`, `usrto_config` and their sequences), and its `il_plugin` registration before updating. Keep existing groups and settings; do not uninstall the previous version.

In the plugin directory:

```sh
composer install --no-dev --no-interaction --no-plugins --no-scripts
```

The committed `js/dist` directory contains all plugin browser assets. Production does not need `node_modules`. To rebuild those assets:

```sh
npm ci --ignore-scripts
npm run build
```

From the ILIAS root, as the deployment account that can write ILIAS's generated files and client directories:

```sh
composer dump-autoload --no-plugins --no-scripts --optimize
php cli/setup.php build --yes
```

For an existing installation, update and activate this plugin only:

```sh
php cli/setup.php update --legacy-plugin UserTakeOver --yes
```

For an unregistered plugin, use **Administration → Plugins → UserTakeOver → Install/Update → Activate** after rebuilding the artifacts. The setup `install --legacy-plugin UserTakeOver` command also supports installation, but requires the installation's setup configuration. Do not run an unrestricted core update merely to update this plugin.

ILIAS setup requires filesystem permissions even when the plugin itself only needs database changes. Do not ignore failed setup preconditions. Use the normal administrative plugin lifecycle or run setup as the deployment account.

## Tests

Point the tests at an installed ILIAS 11 checkout; they use its real interfaces and bundled PHPUnit:

```sh
export ILIAS_ROOT=/path/to/ilias
composer install --no-interaction --no-plugins --no-scripts
npm ci --ignore-scripts
npm run build
npm test
composer test
```

Database integration tests are opt-in:

```sh
USRTO_TEST_CLIENT_INI="$ILIAS_ROOT/public/data/CLIENT/client.ini.php" composer test
```

They exercise the real ILIAS database driver on uniquely named disposable tables and remove those tables afterwards. They do not modify plugin or user records. Prefer a test instance; the database account needs permission to create and drop tables. Without this variable, database tests are reported as skipped.

The GitLab configuration expects a shell runner tagged `ilias11` with `ILIAS_ROOT` configured. It replaces the old private ILIAS 9 review-deployment template.

## Browser acceptance checklist

Use designated test accounts. Check:

- Administrator search by login/name, including keyboard selection and names with special characters.
- Takeover from search and from a configured group; the target's actual repository permissions apply.
- The takeover indicator and return action restore the original administrator, even when the target cannot use the plugin.
- An unauthorized user cannot search or take over an account; member and role restrictions still apply.
- Administrator targets are denied/enabled according to the corresponding setting.
- Create, edit, filter and delete a group; member autocomplete shows names and preserves selected IDs after saving.
- Missing/deleted targets fail without switching accounts; another takeover cannot replace the stored original identity.
- Course member view, LTI, learning sequences and WOPI keep their own mode controls. The takeover return action reappears after leaving those modes.
- Logout terminates the session; a subsequent login does not restore an old takeover.

## Migration notes

The ILIAS 11 migration updates legacy UI content types, metabar decoration, asynchronous member autocomplete, controller registration and page construction. Session data now uses PHP session encoding instead of a debug dump. Existing table names and database step numbers remain unchanged; historical demo-group migration inserts are repaired.

Runtime dependencies remain supplied by ILIAS. Rollup and frontend test tools are development dependencies; tiny-autocomplete 1.1.1 is copied into the browser distribution at build time.

References: [ILIAS UI plugin manipulation](https://github.com/ILIAS-eLearning/ILIAS/blob/release_11/docs/development/ui-plugin-manipulations.md), [ILIAS installation](https://github.com/ILIAS-eLearning/ILIAS/blob/release_11/docs/configuration/install.md), [Rollup migration](https://rollupjs.org/migration/).

See [MIGRATION.md](MIGRATION.md) for the compatibility audit, server deployment results, backup locations and remaining acceptance checks.

## Contact

Original maintainer: sr solutions ag — support@sr.solutions — https://sr.solutions/plugins
