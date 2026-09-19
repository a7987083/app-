<?php

$stateFile = getenv('PHASE20_OPENLIST_STATE');
if (!$stateFile) {
    http_response_code(500);
    echo json_encode(['code'=>500,'message'=>'missing state file']);
    exit;
}

function ol_load($file)
{
    $raw = @file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) ? $data : ['files'=>[],'requests'=>[]];
}

function ol_save($file, array $state)
{
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_SLASHES));
}

function ol_norm($path)
{
    $path = preg_replace('#/+#', '/', '/' . ltrim((string)$path, '/'));
    return strlen($path) > 1 ? rtrim($path, '/') : $path;
}

function ol_parent($path)
{
    $path = ol_norm($path);
    if ($path === '/') return '/';
    $p = dirname($path);
    return $p === '.' ? '/' : ol_norm($p);
}

function ol_name($path)
{
    return basename(ol_norm($path));
}

$state = ol_load($stateFile);
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$state['requests'][] = ['uri'=>$uri,'payload'=>$payload,'authorization'=>$auth];

if ($auth !== 'phase20-test-token') {
    ol_save($stateFile, $state);
    http_response_code(401);
    echo json_encode(['code'=>401,'message'=>'bad token']);
    exit;
}

if ($uri === '/api/fs/list') {
    $dir = ol_norm(isset($payload['path']) ? $payload['path'] : '/');
    $entries = [];
    $seenDirs = [];
    foreach ($state['files'] as $path => $meta) {
        $path = ol_norm($path);
        if (ol_parent($path) === $dir) {
            $entry = $meta;
            $entry['name'] = ol_name($path);
            $entry['is_dir'] = false;
            $entries[] = $entry;
            continue;
        }
        if ($dir === '/') {
            $trim = ltrim($path, '/');
            if (strpos($trim, '/') !== false) {
                $first = explode('/', $trim, 2)[0];
                if (!isset($seenDirs[$first])) {
                    $seenDirs[$first] = true;
                    $entries[] = ['name'=>$first,'is_dir'=>true,'size'=>0,'modified'=>1700000000];
                }
            }
        } else {
            $prefix = rtrim($dir, '/') . '/';
            if (strpos($path, $prefix) === 0) {
                $rest = substr($path, strlen($prefix));
                if (strpos($rest, '/') !== false) {
                    $first = explode('/', $rest, 2)[0];
                    if (!isset($seenDirs[$first])) {
                        $seenDirs[$first] = true;
                        $entries[] = ['name'=>$first,'is_dir'=>true,'size'=>0,'modified'=>1700000000];
                    }
                }
            }
        }
    }
    ol_save($stateFile, $state);
    header('Content-Type: application/json');
    echo json_encode(['code'=>200,'data'=>['content'=>$entries,'total'=>count($entries)]]);
    exit;
}

if ($uri === '/api/fs/get') {
    $path = ol_norm(isset($payload['path']) ? $payload['path'] : '/');
    ol_save($stateFile, $state);
    if (!isset($state['files'][$path])) {
        echo json_encode(['code'=>404,'message'=>'not found']);
        exit;
    }
    $data = $state['files'][$path];
    $data['name'] = ol_name($path);
    echo json_encode(['code'=>200,'data'=>$data]);
    exit;
}

if ($uri === '/api/fs/rename') {
    $path = ol_norm(isset($payload['path']) ? $payload['path'] : '/');
    $name = isset($payload['name']) ? (string)$payload['name'] : '';
    if (!isset($state['files'][$path]) || $name === '') {
        ol_save($stateFile, $state);
        echo json_encode(['code'=>400,'message'=>'rename invalid']);
        exit;
    }
    $target = ol_norm(ol_parent($path) . '/' . $name);
    if (isset($state['files'][$target])) {
        ol_save($stateFile, $state);
        echo json_encode(['code'=>409,'message'=>'target exists']);
        exit;
    }
    $state['files'][$target] = $state['files'][$path];
    unset($state['files'][$path]);
    ol_save($stateFile, $state);
    echo json_encode(['code'=>200,'data'=>null]);
    exit;
}

if ($uri === '/api/fs/move') {
    $src = ol_norm(isset($payload['src_dir']) ? $payload['src_dir'] : '/');
    $dst = ol_norm(isset($payload['dst_dir']) ? $payload['dst_dir'] : '/');
    $names = isset($payload['names']) && is_array($payload['names']) ? $payload['names'] : [];
    foreach ($names as $name) {
        $from = ol_norm($src . '/' . $name);
        $to = ol_norm($dst . '/' . $name);
        if (!isset($state['files'][$from]) || isset($state['files'][$to])) {
            ol_save($stateFile, $state);
            echo json_encode(['code'=>409,'message'=>'move conflict']);
            exit;
        }
        $state['files'][$to] = $state['files'][$from];
        unset($state['files'][$from]);
    }
    ol_save($stateFile, $state);
    echo json_encode(['code'=>200,'data'=>null]);
    exit;
}

ol_save($stateFile, $state);
http_response_code(404);
echo json_encode(['code'=>404,'message'=>'unknown route']);
