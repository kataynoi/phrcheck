# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

phrCheck — a CodeIgniter 4 + Bootstrap 5 + MySQL web app (Docker) for Mahasarakham province health offices to review PHR **encounter mask** complaints: units upload the `.xlsx` exports straight from the PHR system, mark each record with a cause (สาเหตุ), and a dashboard rolls the results up daily and per service unit. It mirrors the "รายงานผลการดำเนินงานข้อร้องเรียน PHR รายหน่วยบริการ" spreadsheet.

## Commands

```powershell
docker compose up -d --build          # build php image + start nginx/php/db
docker compose logs -f php            # PHP-FPM + CI4 errors
docker compose exec php sh            # alpine — sh, not bash
docker compose exec php php spark routes
docker compose exec php php spark phr:import /path/file.xlsx [--user <id>] [--hoscode <code>]
docker compose down                   # add -v to drop the DB volume and re-run initdb
```

**After changing `src/composer.json`, rebuilding is not enough:**

```powershell
docker compose build php
docker compose rm -fsv php            # -v drops the anonymous vendor volume
docker compose up -d php
```

Compose carries an anonymous volume over to the recreated container, so a plain `up -d` keeps serving the *old* `vendor/` and the new package appears missing (`Class "..." not found`) even though the build log shows it installed.

App at http://localhost:8087, MySQL on host port 3311.

There is no test suite — the image is built with `composer install --no-dev`, so PHPUnit is absent. Verify changes by driving the app over HTTP or via `spark phr:import`.

## Architecture

Three services on `phpcheckbook-net`. Both nginx and php bind-mount `./src` at `/var/www/html`; nginx serves `public/` and proxies `.php` to `php:9000`.

**Dependencies live in the image, not the host.** `docker/php/Dockerfile` copies only `src/composer.json` and runs `composer install`, and an anonymous volume at `/var/www/html/vendor` keeps the `./src` bind mount from shadowing it. Consequences:
- Changing `src/composer.json` requires `docker compose build php` plus the volume drop above, not `composer install` on the host.
- The host has no `vendor/`, so IDE PHP language servers report every CI4 class as undefined. Those diagnostics are false positives.

`phpoffice/phpspreadsheet` is pinned to `^5.9`: every 4.x release is blocked by Composer security advisories, and this library parses files uploaded by users, so do not work around that with `policy.advisories`. It needs `ext-gd` and `ext-zip`, which is why the Dockerfile builds gd with freetype/jpeg.

**Config comes from environment variables, not `src/.env`.** Secrets live in the project-root `.env`, are passed to the `php` service via `docker-compose.yml`, and are read with `getenv()` in `app/Config/Database.php`, `app/Config/App.php`, and `app/Libraries/LineLogin.php`. `src/.env` only sets `CI_ENVIRONMENT`.

`APP_BASE_URL` and `APP_INDEX_PAGE` drive `Config\App`. Local uses clean URLs (`APP_INDEX_PAGE=` empty, nginx rewrites); the production host serves the app under `/phrcheck/` with `index.php` in the path, so it needs `APP_INDEX_PAGE=index.php` or every generated link 404s. `.env` carries both sets, with the production block commented out.

Two settings that must not drift:
- `docker/php/fpm-env.conf` sets `clear_env = no`. PHP-FPM defaults to wiping the environment, which makes every `getenv()` above return `false` even though Compose passed the values in.
- `App::$appTimezone` is `Asia/Bangkok`, matching MySQL's `--default-time-zone=+07:00`. If these diverge, PHP-stamped `checked_at` and MySQL's `CURDATE()` land on different days and the dashboard's daily buckets go wrong.

## Database

Built once, on first start, from two scripts in `/docker-entrypoint-initdb.d` (they only run while the volume is empty — `docker compose down -v` to re-seed):

1. `data_sample/data.sql` — 8 MB Navicat dump from `smiv_db`, supplying `chospital` and `campur`.
2. `docker/mysql/02-schema.sql` — app tables, plus `hospitals` populated by `SELECT ... FROM chospital WHERE provcode = '44'` joined to `campur` for `ampurname`. 420 rows.

App tables: `users`, `hospitals`, `check_statuses`, `encounter_masks`, `upload_batches`.

`check_statuses` is seeded with the six report causes; id 1 (`อยู่ระหว่างการวิเคราะห์ข้อมูล`) is the default on import and means "not yet reviewed" — `checked_at IS NULL` is the authoritative "unreviewed" test.

## Domain rules worth knowing before editing

- **`code`** is derived, never uploaded: the first 5 characters of `encounter_ref_code` (`11055:690615062028` → `11055`). It is the join key to `hospitals` and the basis of all access control.
- **Deduplication** is enforced by `UNIQUE (cid, encounter_ref_code)` and `INSERT IGNORE`; `EncounterMaskModel::insertIgnoreBatch()` sums `affectedRows()` per chunk to report what actually landed. The importer also de-dupes within a single file before hitting the DB, and reports the two counts separately.
- **Data scoping**: `BaseController::scopeHoscode()` returns `null` for admins and the user's `hoscode` otherwise; `null` means unrestricted. Every query path funnels through this — `EncounterMaskModel::scoped()`, `Dashboard::scopeSql()`, `EncounterImporter::import()`. Bulk updates re-apply the scope in the `WHERE` clause rather than trusting posted ids.
- **Auth** is LINE Login v2.1 → register (name + service unit) → admin approval. `AuthFilter` requires session `registered` *and* `status === 'approved'`. `ADMIN_LINE_USER_ID` in `.env` bootstraps the first admin as auto-approved, since otherwise nobody could approve anyone.

  The registration dropdown uses `HospitalModel::selectableOptions()`, which drops `NON_SELECTABLE_HOSTYPES` (`16` = private clinics, 206 of the 420 rows) — `Register::save()` re-checks the same list because a hidden `<option>` stops nothing. `options()` stays unfiltered so the admin encounter filter can still reach any unit that has data.

  Two failures here are LINE-side, not code: a channel left in **Developing** status rejects everyone without a console role (`400 Bad Request — This channel is now developing status`), and the `redirect_uri` must match a Callback URL registered on the channel exactly. Publishing a channel is irreversible.
- **Dates arrive as Excel serials.** In `.xlsx` the reader detects date cells via `Shared\Date::isDateTime()` and converts them; the sample files carry serial `25569` (= 1970-01-01) in `process_datetime` as a "no value" sentinel, which `phr_parse_datetime()` maps to `NULL`. The helper also still handles the CSV path, where the format depends on the user's Excel locale and Thai locale emits Buddhist-era years — it subtracts 543 from years > 2400. `EncounterImporter::readCsv()` additionally sniffs the delimiter and transcodes TIS-620. Do not simplify any of this without a sample file proving it is unnecessary.

`EncounterImporter` reads `.xlsx`/`.xls` via PhpSpreadsheet and `.csv` by hand, normalising both to a header array plus row arrays before a single shared `buildRows()` does validation, `code` extraction, and de-duplication. It is deliberately shared by `Controllers/Upload.php` and `Commands/ImportFile.php` so the import path can be exercised from the CLI without a browser session.

The spreadsheet reader uses `setReadDataOnly(true)`; number formats are still parsed (so date detection works) but styles are not, which matters for the multi-thousand-row exports.

## Conventions

Comments and all user-facing strings are Thai; match that when editing. Views use CI4 layout sections (`layout/main` for authenticated pages, `layout/blank` for auth screens) with Bootstrap/Chart.js from CDN.
