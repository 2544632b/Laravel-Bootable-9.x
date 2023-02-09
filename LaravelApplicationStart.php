<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

print("Starting autoload, please wait...\n");
require __DIR__.'/vendor/autoload.php';

print("Loading the bootstrapper\n");
$app = require_once __DIR__.'/bootstrap/app.php';

print("Making the http kernel for laravel\n");
$http_kernel = $app->make(Kernel::class);

$address = '127.0.0.1';
$port = 8000;
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_block($socket);
socket_bind($socket, $address, $port);
socket_listen($socket, 4);

if($port != 443) {
    print("Server is listening on http://" . $address . ":" . $port . "\n");
}
else {
    print("Server is listening on https://" . $address . "\n");
}

while(true) {
    $newSocket = socket_accept($socket);
    if($newSocket) {
        $buffer = socket_read($newSocket,1024);
        socket_write($newSocket, $http_kernel->handle($request = Request::capture()));
        socket_close($newSocket);
    }
}
