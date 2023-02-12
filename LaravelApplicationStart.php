<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

print("Starting autoload, please wait...\n");
require __DIR__.'/vendor/autoload.php';

print("Loading the bootstrapper\n");
$app = require_once __DIR__.'/bootstrap/app.php';

print("Making the http kernel for laravel\n");
$http_kernel = $app->make(Kernel::class);

$address = '192.168.1.16';
$port = 80;
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_block($socket);
socket_bind($socket, $address, $port);
socket_listen($socket, 4);

if($port == 443) {
    print("Server is listening on https://" . $address . "\n");
}
if($port == 80) {
    print("Server is listening on http://" . $address . "\n");
}
else {
    print("Server is listening on http://" . $address . ":" . $port . "\n");
}

print("Laravel is ready! (". LARAVEL_START . ")\n");

while(true) {
    $newSocket = socket_accept($socket);
    if($newSocket) {
        $buffer = socket_read($newSocket, 400000);
        socket_getpeername($newSocket, $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT']);
        /*
         * Example
        $_SERVER['REQUEST_URI'] = $path[1];
        $_SERVER['REQUEST_METHOD'] = $path[0];
        $_SERVER['SERVER_PROTOCOL'] = $path[2];
        $_SERVER['HTTP_HOST'] = $path[4];
        $_SERVER['HTTP_CONNECTION'] = $path[6];
        */
        // HTTP PATCH
        $request_content = preg_split("/\n/", $buffer);
        if(empty($buffer)) {
            socket_shutdown($newSocket);
            continue;
        }
        for($i = 0; $i < count($request_content); $i++) {
            if($i == 0) {
                $path = explode(" ", $request_content[$i]);
                $_SERVER['REQUEST_URI'] = $path[1];
                $_SERVER['REQUEST_METHOD'] = $path[0];
                $_SERVER['SERVER_PROTOCOL'] = $path[2];
                if($_SERVER['REQUEST_METHOD'] == "POST") {
                    $buffer_post = socket_read($newSocket, 400000);
                    $post_line = explode("&", $buffer_post);
                    if(empty($post_line)) {
                        socket_close($newSocket);
                    }
                    for($j = 0; $j < count($post_line); $j++) {
                        $post_kv = explode("=", $post_line[$j]);
                        $post_kv_1_result = urldecode($post_kv[1]);
                        $_POST[$post_kv[0]] = $post_kv_1_result;
                    }
                }
            }
            else {
                $value = explode(": ", $request_content[$i]);
                if(!$value || $request_content[$i] == "\n") {
                    print("Not Parameter line:" . $request_content[$i]. PHP_EOL);
                    continue;
                }
                switch($value[0]) {
                    case 'Host':
                        $_SERVER['HTTP_HOST'] = $value[1];
                        break;
                    case 'Connection':
                        $_SERVER['HTTP_CONNECTION'] = $value[1];
                        break;
                    case 'Accept':
                        $_SERVER['HTTP_ACCEPT'] = $value[1];
                        break;
                    case 'Accept-Encoding':
                        $_SERVER['HTTP_ACCEPT_ENCODING'] = $value[1];
                        break;
                    case 'Accept-Language':
                        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $value[1];
                        break;
                    case 'User-Agent':
                        $_SERVER['HTTP_USER_AGENT'] = $value[1];
                        break;
                    case 'Referer':
                        $_SERVER['HTTP_REFERER'] = $value[1];
                        break;
                    case 'Cookie':
                        $cookie_line = explode(';', $value[1]);
                        if(empty($cookie_line)) {
                            socket_close($newSocket);
                        }
                        for($k = 0; $k < count($cookie_line); $k++) {
                            $cookie_kv = explode('=', $cookie_line[$k]);
                            $_COOKIE[$cookie_kv[0]] = $cookie_kv[1];
                        }
                        break;
                    default:
                }
            }
        }
        //$request = Request::create("$path[1]", "$path[0]");
        socket_write($newSocket, $http_kernel->handle($request = Request::capture()));
        socket_shutdown($newSocket);
        socket_close($newSocket);
    }
}
