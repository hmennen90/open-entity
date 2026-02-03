<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Websocket-Kanäle für die Echtzeit-Kommunikation
|
*/

// Öffentlicher Kanal für Entity-Gedanken (Mind Viewer)
Broadcast::channel('entity.mind', function () {
    return true; // Öffentlich zugänglich
});

// Öffentlicher Kanal für Entity-Status
Broadcast::channel('entity.status', function () {
    return true;
});

// Chat-Kanal
Broadcast::channel('entity.chat', function () {
    return true;
});

// Soziale Interaktionen
Broadcast::channel('entity.social', function () {
    return true;
});
