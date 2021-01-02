<?php

$channelId = substr($_SERVER['REQUEST_URI'], 1);

function notFoundError(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

function internalServerError(): void
{
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
    exit;
}

if (! $channelId) {
    notFoundError();
}

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

try {
    $connection = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=utf8',
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_TIMEOUT => 130,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (PDOException $e) {
    internalServerError();
}

$requestParams = ['request' => $channelId];
try {
    $statement = $connection->prepare('SELECT id FROM unprocessable_request WHERE request = :request;');
    $statement->execute($requestParams);
} catch (PDOException $e) {
    $connection = null;
    internalServerError();
}

$fetchedRequests = $statement->fetchAll();

if ($fetchedRequests) {
    $connection = null;
    notFoundError();
}

try {
    $statement = $connection->prepare('SELECT twitter_handle FROM channel WHERE youtube_id = :youtube_id;');
    $statement->execute(['youtube_id' => $channelId]);
} catch (PDOException $e) {
    $connection = null;
    internalServerError();
}

$fetchedChannels = $statement->fetchAll();

if ($fetchedChannels) {
    http_response_code(200);
    echo json_encode(['twitter_handle' => $fetchedChannels[0]['twitter_handle'] ?? null]);
    exit;
}

set_time_limit(120);
$scrapingResult = exec('node scrape.js ' . $channelId);

if ($scrapingResult === 'not found') {

    try {
        $statement = $connection->prepare('INSERT INTO unprocessable_request (request) VALUES (:request);');
        $statement->execute($requestParams);
    } catch (PDOException $e) {
        $connection = null;
        internalServerError();
    }

    $connection = null;
    notFoundError();
}

if ($scrapingResult === 'null') {
    $scrapingResult = null;
}

try {
    $statement = $connection->prepare('INSERT INTO channel (youtube_id, twitter_handle) VALUES (:youtube_id, :twitter_handle)');
    $statement->execute(['youtube_id' => $channelId, 'twitter_handle' => $scrapingResult]);
} catch (PDOException $e) {
    $connection = null;
    internalServerError();
}

http_response_code(200);
echo json_encode(['twitter_handle' => $scrapingResult]);
exit;
