<?php
/* What share/index.php needs to reach Dropbox. The real one is config.php,
 * written by set-dropbox.py (next to deploy.py) and uploaded by
 * "python3 deploy.py share". It never goes into a git repository.
 *
 * Without config.php the card on the homepage says the box isn't open yet.
 */
return [
    // From the Dropbox app (dropbox.com/developers/apps) and its one-time
    // authorisation. set-dropbox.py fills these in.
    'app_key'       => '',
    'app_secret'    => '',
    'refresh_token' => '',

    // Shown under the form: where someone asks to have their submission taken out.
    'contact'       => '',

    'max_files'     => 10,    // photos per submission
    'max_mb'        => 150,   // per photo: what Dropbox's one-time upload links take reliably
    'per_hour'      => 5,     // submissions from one visitor
    'per_day'       => 15,
    'all_day'       => 150,   // from everyone together
];
