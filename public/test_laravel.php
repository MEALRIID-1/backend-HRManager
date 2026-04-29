<?php

use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simuler une requête POST
$request = Request::create('/test', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], '{"email":"test@test.com","password":"test123"}');

// Ou capturer la requête actuelle
$request = Request::capture();

header('Content-Type: application/json');
echo json_encode([
    'method' => $request->method(),
    'content_type' => $request->header('Content-Type'),
    'is_json' => $request->isJson(),
    'all' => $request->all(),
    'input_email' => $request->input('email'),
    'json_email' => $request->json('email'),
    'raw' => $request->getContent(),
], JSON_PRETTY_PRINT);
