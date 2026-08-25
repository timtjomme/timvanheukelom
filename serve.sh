#!/usr/bin/env bash
# Serve the site at http://localhost:8765
cd "$(dirname "$0")" && exec python3 -m http.server 8765
