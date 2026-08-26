#!/usr/bin/env bash
# Serve the site with PHP so analytics/track.php and the dashboard work,
# the same way it runs on the live host.
#   site      http://localhost:8765/
#   dashboard http://localhost:8765/analytics/dashboard.php
cd "$(dirname "$0")" && exec php -S localhost:8765
