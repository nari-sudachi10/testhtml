<?php

header('Content-Type: application/json; charset=utf-8');

try {
    // データベース接続情報を取得
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

    // PostgreSQLに接続
    $db = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // 最新のセンサーデータを1件取得
    $sql = '
        SELECT
            id,
            temperature,
            humidity,
            pressure,
            measured_at
        FROM sensor_data
        ORDER BY id DESC
        LIMIT 1
    ';

    $stmt = $db->query($sql);
    $data = $stmt->fetch();

    // データが存在しない場合
    if (!$data) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'No sensor data found'
        ]);

        exit;
    }

    // JSON形式で返す
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$data['id'],
            'temperature' => (float)$data['temperature'],
            'humidity' => (float)$data['humidity'],
            'pressure' => (float)$data['pressure'],
            'measured_at' => $data['measured_at']
        ]
    ]);

} catch (Throwable $e) {
    // 詳細なエラーはサーバーのログに記録する
    error_log('Get sensor API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve sensor data'
    ]);
}