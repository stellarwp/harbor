# Cron

Harbor schedules a WordPress cron event to keep catalog and licensing data fresh. The leader instance owns this schedule; thin instances do not register cron jobs.

## What happens every 12 hours

The `lw_harbor_data_refresh` hook fires on the `twicedaily` WordPress schedule. Each firing refreshes two things:

1. **Catalog** — fetches the latest product catalog from the Commerce Portal API and updates the local cache. This picks up new features, tier changes, version bumps, and download URLs without any user action.

2. **Licensing** — fetches the latest product list for the stored license key. This picks up tier upgrades or downgrades, subscription status changes (expired, cancelled, renewed), and seat count updates. Skipped entirely if no key is stored.

After both jobs complete, the next page load in wp-admin reflects the updated data. The feature manager page, update notices, and `harbor_has_feature()` checks all read from the refreshed cache.

## What happens when you enter a license key

Entering a key through the admin UI or `POST /license` triggers an immediate fetch of products from the Licensing API. The cron job does not need to run first — the data is available right away. The cron job's role is to keep that data current over time without requiring the user to revisit the page.

## What happens when you deactivate the last plugin

When a plugin is deactivated or a theme is switched, Harbor checks whether any catalog features (plugins or themes) are still active on the site. If none remain, the cron event is cleared so the refresh stops running on a site that no longer needs it.

This check is conservative: if the cached catalog is missing or contains no installable features, the event is left in place since Harbor cannot confirm it is safe to remove. The cron will reschedule itself on the next page load if any Harbor instance is still active.

## Implementation details

The cron hook name is `lw_harbor_data_refresh`, defined in `Cron\ValueObjects\CronHook`. The schedule is registered on the `init` hook if not already present. Both jobs are gated behind `Version::should_handle('cron_data_refresh')` so only the leader instance runs them.

Cleanup listens to the `deactivated_plugin` and `switch_theme` hooks.

### Key files

- `src/Harbor/Cron/Provider.php` — hooks and schedule registration
- `src/Harbor/Cron/Jobs/Refresh_Catalog_Job.php` — catalog refresh
- `src/Harbor/Cron/Jobs/Refresh_License_Job.php` — license refresh
- `src/Harbor/Cron/Actions/Handle_Unschedule_Cron_Data_Refresh.php` — cleanup on deactivation
