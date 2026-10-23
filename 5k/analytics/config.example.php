<?php
/**
 * One password hash per person. Written by dashboard.php itself — the first
 * visit asks whoever arrives for their address and a password, and from then
 * on the "Who can get in" panel inside the dashboard sets the rest.
 *
 * You only need this file by hand if the analytics/ folder is not writable.
 * config.php is gitignored, so it never travels through GitHub/Plesk and no
 * password ever sits in the repo. The addresses themselves are not secret and
 * live in dashboard.php, in PEOPLE.
 *
 * To make a hash:
 *   php -r "echo password_hash('the password', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * null means that person has not got a password yet and cannot sign in.
 */
return [
    'people' => [
        'timvanheukelom@me.com'       => '$2y$10$replace.with.a.real.password_hash.output',
        'sanne@sanderkleinenberg.com' => null,
    ],
];
