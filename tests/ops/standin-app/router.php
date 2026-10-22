<?php
// Stand-in for the NMSOLT app container in the ops integration test only.
// Answers /up like Laravel's health route; everything else is 404.
if (file_exists('/opt/standin/BROKEN')) {
    http_response_code(500);
    return true;
}
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/up') {
    http_response_code(200);
    echo "up\n";
    return true;
}
http_response_code(404);
return true;
