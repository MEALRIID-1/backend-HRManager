<?php
require '../vendor/autoload.php';
\ = require_once '../bootstrap/app.php';
\->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\ = Illuminate\Http\Request::capture();
echo json_encode([
    'method' => \->method(),
    'content_type' => \->header('Content-Type'),
    'all' => \->all(),
    'input' => \->input(),
    'json' => \->json()->all(),
    'raw' => file_get_contents('php://input')
]);
