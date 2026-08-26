<?php
/**
 * Copy this file to config.php on the server (config.php is gitignored — it
 * never goes through the GitHub/Plesk pipeline, so the real password never
 * sits in the repo) and set a real password below.
 *
 * You don't have to: the first visit to analytics/dashboard.php on the live
 * site lets you set a password in the browser, which writes config.php on
 * the server directly.
 */
return [
    'dashboard_password' => 'change-me-to-something-only-you-know',
];
