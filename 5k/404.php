<?php
/* Every address that is neither a file nor a folder lands here (see the
   rewrite rule in .htaccess): the server ignores ErrorDocument, so this sends
   the 404 status itself, with the site's own 404 page. */
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
readfile(__DIR__ . '/404.html');
