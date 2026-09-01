Orbit Presence Hotfix

Problem fixed:
PresencePresenter::present() expected a bool for global Ghost Mode, but a newly-created user could expose NULL for global_ghost_mode in the in-memory model during tests.

Fix:
The presenter now safely normalizes NULL to false for both owner and Circle presence views.

Copy/merge the top-level orbit_api folder into:
C:\laravel-projects\orbit_api

Then run:
vendor\bin\pint
vendor\bin\pint --test
php artisan test

No migration is required for this hotfix.
