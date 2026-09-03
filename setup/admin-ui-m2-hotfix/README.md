# Orbit Admin UI M2 unification hotfix

This hotfix corrects three integration defects in the first M2 UI overlay:

1. The M2 operations pages used a second light/white shell rather than the visual language already established by Admin UI Foundation M1.
2. The M1 sidebar still treated Users/Circles as placeholder actions instead of real M2 navigation.
3. M2 looked for only a narrow set of bearer-token storage keys, so an administrator who was authenticated in M1 could receive a 401 on M2 API calls.

The hotfix keeps the real M2 backend/routes intact. It adds capture-phase navigation for Users/Circles, expands the admin-session token bridge to support nested M1 session objects, and restyles the M2 shell to the established Orbit Administration foundation.

No migrations are added and no backend authorization is weakened.
