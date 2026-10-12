<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'POST only'
    ]);

    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (
    !is_array($data) ||
    !isset(
        $data['temperature'],
        $data['humidity'],
        $data['pressure']
    ) ||
    !is_numeric($data['temperature']) ||
    !is_numeric($data['humidity']) ||
    !is_numeric($data['pressure'])
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid sensor data'
    ]);

    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Sensor data received',
    'data' => [
        'temperature' => (float)$data['temperature'],
        'humidity' => (float)$data['humidity'],
        'pressure' => (float)$data['pressure']
    ]
]);