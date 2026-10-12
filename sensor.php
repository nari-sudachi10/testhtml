<?php

header('Content-Type: application/json; charset=utf-8');

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'POST only'
    ]);

    exit;
}

// JSONデータを取得
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// データの形式を確認
if (
    !is_array($data) ||
    !isset($data['temperature'], $data['humidity'], $data['pressure']) ||
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

// 数値に変換
$temperature = (float)$data['temperature'];
$humidity = (float)$data['humidity'];
$pressure = (float)$data['pressure'];

// 異常な値を拒否
if (
    !is_finite($temperature) ||
    !is_finite($humidity) ||
    !is_finite($pressure) ||
    $temperature < -40 || $temperature > 85 ||
    $humidity < 0 || $humidity > 100 ||
    $pressure < 300 || $pressure > 1200
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Sensor values out of range'
    ]);

    exit;
}

// データベース接続
try {
    $databaseUrl = getenv('DATABASE_URL');

    if (!$databaseUrl) {
        throw new RuntimeException('DATABASE_URL is not configured');
    }

    $url = parse_url($databaseUrl);

    if (
        $url === false ||
        empty($url['host']) ||
        empty($url['path']) ||
        !isset($url['user'], $url['pass'])
    ) {
        throw new RuntimeException('Invalid DATABASE_URL');
    }

    $host = $url['host'];
    $port = $url['port'] ?? 5432;
    $dbname = ltrim($url['path'], '/');
    $user = rawurldecode($url['user']);
    $password = rawurldecode($url['pass']);

    $db = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // センサーデータを保存
    $sql = '
        INSERT INTO sensor_data
            (temperature, humidity, pressure)
        VALUES
            (:temperature, :humidity, :pressure)
        RETURNING id, measured_at
    ';

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':temperature' => $temperature,
        ':humidity' => $humidity,
        ':pressure' => $pressure
    ]);

    $saved = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Sensor data saved',
        'data' => [
            'id' => (int)$saved['id'],
            'temperature' => $temperature,
            'humidity' => $humidity,
            'pressure' => $pressure,
            'measured_at' => $saved['measured_at']
        ]
    ]);

} catch (Throwable $e) {
    error_log('Sensor API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save sensor data'
    ]);
}