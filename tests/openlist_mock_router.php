<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input'), true);
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

header('Content-Type: application/json');

if ($auth !== 'test-openlist-token') {
    http_response_code(401);
    echo json_encode(['code' => 401, 'message' => 'unauthorized', 'data' => null]);
    return;
}

if ($path === '/api/fs/list') {
    if (!is_array($body) || !isset($body['path'], $body['page'], $body['per_page'])) {
        http_response_code(400);
        echo json_encode(['code' => 400, 'message' => 'bad payload', 'data' => null]);
        return;
    }
    $page = (int)$body['page'];
    if ($page === 1) {
        $content = [
            ['name' => 'A.ipa', 'is_dir' => false, 'size' => 1048576, 'modified' => '2026-09-21T08:00:00Z'],
            ['name' => 'Sub', 'is_dir' => true, 'size' => 0, 'modified' => '2026-09-21T08:00:00Z'],
        ];
    } else {
        $content = [
            ['name' => 'B.ipa', 'is_dir' => false, 'size' => 2097152, 'modified' => '2026-09-21T08:01:00Z'],
        ];
    }
    echo json_encode(['code' => 200, 'message' => 'success', 'data' => ['content' => $content, 'total' => 3]]);
    return;
}

if ($path === '/api/fs/get') {
    if (!is_array($body) || empty($body['path'])) {
        http_response_code(400);
        echo json_encode(['code' => 400, 'message' => 'bad payload', 'data' => null]);
        return;
    }
    echo json_encode(['code' => 200, 'message' => 'success', 'data' => [
        'name' => basename($body['path']),
        'size' => 3145728,
        'raw_url' => 'http://127.0.0.1:18081/files/' . rawurlencode(basename($body['path'])),
        'modified' => '2026-09-21T08:02:00Z',
    ]]);
    return;
}

http_response_code(404);
echo json_encode(['code' => 404, 'message' => 'not found', 'data' => null]);
