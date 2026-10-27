<?php
/* The download gate's settings. Copy this to config.php and edit that; like
 * the analytics config, config.php is gitignored, so the secret never travels
 * through GitHub and is never served.
 *
 * Nothing here is clever. The gate asks for a follow, opens the real link, and
 * then lets the file go. It cannot see whether the follow happened -- no
 * platform will tell us without OAuth -- so treat the numbers as encouragement
 * rather than proof. That is the same deal every click-through gate offers;
 * ours just says so out loud.
 */
return [
  /* The file being given away. It lives in go/files/, which .htaccess keeps
   * unreachable, so the only way to it is get.php with a signed token. */
  'file'     => 'justin-timberlake-rock-your-body-sander-kleinenberg-1999-mix.mp3',
  'title'    => 'Rock Your Body',
  'subtitle' => 'Sander Kleinenberg 1999 Remix — unreleased',

  /* Signs the download tokens. Any long random string; change it and every
   * token already handed out stops working, which is the point. */
  'secret'   => 'CHANGE-ME-to-a-long-random-string',

  /* How long a token stays good for, in seconds. Long enough to start a
   * download on a slow phone, short enough that a copied link is useless. */
  'ttl'      => 900,

  /* The steps. Each one opens its url in a new tab and then counts itself
   * done. Set 'required' to false for a step that is offered but not insisted
   * on. Order is the order they appear. */
  'steps' => [
    ['id' => 'sc-follow', 'label' => 'Follow on SoundCloud',
     'url' => 'https://soundcloud.com/sanderkleinenberg', 'required' => true],
    ['id' => 'sc-like',   'label' => 'Like “Make It Rain” on SoundCloud',
     'url' => 'https://soundcloud.com/darmon-sc/make-it-rain',
     'required' => true],
    ['id' => 'ig-follow', 'label' => 'Follow on Instagram',
     'url' => 'https://www.instagram.com/sanderkleinenberg/', 'required' => true],
    ['id' => 'sp-follow', 'label' => 'Follow on Spotify',
     'url' => 'https://open.spotify.com/artist/3CfH3WZPzbk5mNDWXpGIy6', 'required' => false],
  ],
];
