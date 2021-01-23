<?php

$channelId = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];

function notFoundErrorResponse(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

function internalServerErrorResponse(): void
{
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
    exit;
}

function successResponse(?string $twitterHandle): void
{
    http_response_code(200);
    echo json_encode(['twitter_handle' => $twitterHandle]);
    exit;
}

function setAsUnprocessableRequest(PDO &$connection, array $requestParams): void
{
    try {
        $statement = $connection->prepare('INSERT INTO unprocessable_request (request) VALUES (:request);');
        $statement->execute($requestParams);
    } catch (PDOException $e) {
        $connection = null;
        internalServerErrorResponse();
    }

    $connection = null;
    notFoundErrorResponse();
}

if (! $channelId) {
    notFoundErrorResponse();
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
    internalServerErrorResponse();
}

$requestParams = ['request' => $channelId];
try {
    $statement = $connection->prepare('SELECT id FROM unprocessable_request WHERE request = :request;');
    $statement->execute($requestParams);
} catch (PDOException $e) {
    $connection = null;
    internalServerErrorResponse();
}

$fetchedRequests = $statement->fetchAll();

if ($fetchedRequests) {
    $connection = null;
    notFoundErrorResponse();
}

try {
    $statement = $connection->prepare('SELECT twitter_handle FROM channel WHERE youtube_id = :youtube_id;');
    $statement->execute(['youtube_id' => $channelId]);
} catch (PDOException $e) {
    $connection = null;
    internalServerErrorResponse();
}

$fetchedChannels = $statement->fetchAll();

if ($fetchedChannels) {
    $connection = null;
    successResponse($fetchedChannels[0]['twitter_handle'] ?? null);
}

// Check if channel exists on Youtube API
$accessTokenCurl = curl_init();
curl_setopt_array($accessTokenCurl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://www.googleapis.com/oauth2/v4/token',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $config['refresh_token'],
        'grant_type' => 'refresh_token'
    ])
]);
$accessTokenCurlResult = curl_exec($accessTokenCurl);

if ($accessTokenCurlResult === false) {
    internalServerErrorResponse();
}

$accessTokenJsonResponse = json_decode($accessTokenCurlResult);
if (! empty($accessTokenJsonResponse->error)) {
    internalServerErrorResponse();
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://www.googleapis.com/youtube/v3/channels?id=' . $channelId
]);
$authorization = "Authorization: Bearer " . $accessTokenJsonResponse->access_token;
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json' , $authorization]);

$result = curl_exec($curl);

if ($result === false) {
    internalServerErrorResponse();
}

$jsonResponse = json_decode($result);
if (! empty($jsonResponse->error)) {
    internalServerErrorResponse();
}

if (empty($jsonResponse->pageInfo) || empty($jsonResponse->pageInfo->totalResults)) {
    setAsUnprocessableRequest($connection, $requestParams);
}


// Not in cache -> Scrape
set_time_limit(120);
$scrapingResult = exec('node scrape.js ' . $channelId);

if ($scrapingResult === 'not found') {
    setAsUnprocessableRequest($connection, $requestParams);
}

if ($scrapingResult === 'null') {
    $scrapingResult = null;
}

try {
    $statement = $connection->prepare('INSERT INTO channel (youtube_id, twitter_handle) VALUES (:youtube_id, :twitter_handle)');
    $statement->execute(['youtube_id' => $channelId, 'twitter_handle' => $scrapingResult]);
} catch (PDOException $e) {
    $connection = null;
    internalServerErrorResponse();
}

$connection = null;
successResponse($scrapingResult);
