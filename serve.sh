#!/usr/bin/env bash
# Serve the site at http://localhost:8765 with the self-hosted visit tracker.
# Dashboard: http://localhost:8765/dashboard
cd "$(dirname "$0")" && exec python3 tracker/server.py --port 8765 --site .
