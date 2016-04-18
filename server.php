<?php

use Lstr\SayItToMySocket\FizzbuzzServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require_once __DIR__ . '/vendor/autoload.php';

$ws_server = new WsServer(new FizzbuzzServer());
$http_server = new HttpServer($ws_server);
$io_server = IoServer::factory($http_server, 8085);

$io_server->run();
