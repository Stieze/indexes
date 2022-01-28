<?php

use App\Config\DB;
use App\Helper\Env;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;

require __DIR__ . '/vendor/autoload.php';

(new Env(__DIR__.'/.env'))->load();

$app = AppFactory::create();
$db = DB::getInstance();

$app->addRoutingMiddleware();

// Add MethodOverride middleware
$methodOverrideMiddleware = new MethodOverrideMiddleware();

$app->add($methodOverrideMiddleware);

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, Response $response) {
    $file = 'public/index.html';
    if (file_exists($file)) {
        //echo "YES";
        $content = file_get_contents($file);
        $response->getBody()->write("$content");
        $body = $response->getBody();
        return $response;
    }
    return $response;
});


$app->get('/api/vpz', function (Request $request, Response $response) use ($db) {
    header('Content-Type: application/json');

    $params = $request->getQueryParams();

    $result = $db->select($params);
    if (isset($result['code'])) {
        $response = $response->withStatus($result['code']);
        echo json_encode($result['message'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    return $response;
});

$app->get('/api/vpz/{index}', function (Request $request, Response $response) use($db) {
    header('Content-Type: application/json');

    $index = $request->getAttribute('index');

    if (!$result = $db->selectByIndex($index)) {
        $response = $response->withStatus(404);
        $result['message'] = "Index not found";

    } elseif (isset($result['code'])){
        $response = $response->withStatus($result['code']);
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return $response;
    }

    echo json_encode($result['message'], JSON_UNESCAPED_UNICODE);
    return $response;
});

$app->post('/api/vpz', function (Request $request, Response $response) use($db) {
    header('Content-Type: application/json');

    $requestData = json_decode(file_get_contents('php://input'), true);
    $result = $db->insert($requestData, 1);

    $response = $response->withStatus(201);

    echo json_encode($result['messages'], JSON_UNESCAPED_UNICODE);
    return $response;
});

$app->delete('/api/vpz', function (Request $request, Response $response) use ($db) {
    header('Content-Type: application/json');


    $requestData = json_decode(file_get_contents('php://input'), true);
    $result = $db->delete($requestData['indexes']);

    $response = $response->withStatus($result['code']);
    if ($result['code'] !== 204) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    return $response;
});

$app->run();