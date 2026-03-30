# Cron

Harbor schedules a WordPress cron event to keep catalog and licensing data fresh. The leader instance owns this schedule; thin instances do not register cron jobs.

## The refresh event

The cron hook `lw_harbor_data_refresh` fires on the `twicedaily` WordPress schedule (every 12 hours). Each firing runs two jobs in sequence:

1. **`Refresh_Catalog_Job`** — calls the Catalog API and updates the cached `Catalog_Collection`.
2. **`Refresh_License_Job`** — calls the Licensing API to refresh the cached `Product_Collection`. Skips if no license key is stored.

Both jobs are registered in `Cron\Provider` and gated behind `Version::should_handle('cron_data_refresh')`, so only the leader instance runs them.

## Scheduling

The event is scheduled on the `init` hook if it is not already registered:

```php
if ( ! wp_next_scheduled( CronHook::DATA_REFRESH ) ) {
    wp_schedule_event( time(), 'twicedaily', CronHook::DATA_REFRESH );
}
```

## Cleanup

When a plugin is deactivated (`deactivated_plugin`) or a theme is switched (`switch_theme`), the `Handle_Unschedule_Cron_Data_Refresh` action checks whether any catalog features (plugins or themes) are still installed and active. If none remain, it clears the cron hook so the refresh stops running on a site that no longer needs it.

## Key files

- `src/Harbor/Cron/Provider.php` — hooks and schedule registration
- `src/Harbor/Cron/Jobs/Refresh_Catalog_Job.php` — catalog refresh
- `src/Harbor/Cron/Jobs/Refresh_License_Job.php` — license refresh
- `src/Harbor/Cron/Actions/Handle_Unschedule_Cron_Data_Refresh.php` — cleanup on deactivation
- `src/Harbor/Cron/ValueObjects/CronHook.php` — hook name constant (`lw_harbor_data_refresh`)
