# Orbit Admin UI M4 shared-shell recovery v8

This recovery is for projects affected by the M4 v6 sidebar repair bug.

It does not guess or reconstruct the shared shell. It finds the newest intact post-M4 backup that already contains:

- the full canonical sidebar structure,
- one active Moderation & Reports route,
- one M4 JavaScript import,
- one M4 web-route include.

It restores the shared files from that backup, repairs only the adjacent icon text nodes using ASCII-only PowerShell code points, creates an emergency backup of the current state, and validates the result before clearing Laravel caches.
