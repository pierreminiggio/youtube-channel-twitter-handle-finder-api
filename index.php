<?php

$channelId = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];

function badRequestErrorResponse(?string $error = null): void
{
    http_response_code(400);
    echo json_encode(['error' => 'Bad request' . ($error ? ' : ' . $error : '')]);
    exit;
}

function unauthorizedErrorResponse(): void
{
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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

$channelApiUrl = 'https://youtube-channel-infos-api.miniggiodev.fr';

$getYoutubeChannelInfos = function (string $channelId) use ($channelApiUrl): ?string {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $channelApiUrl . '/' . $channelId
    ]);

    $result = curl_exec($curl);

    if ($result === false) {
        internalServerErrorResponse();
    }

    return $result;
};

$projectDir = __DIR__ . DIRECTORY_SEPARATOR;
$srcDir = $projectDir . 'src' . DIRECTORY_SEPARATOR;

if ($channelId === 'all') {
    require $srcDir . 'all.php';
    exit;
}

if ($channelId === 'tenchi-news') {
    require $srcDir . 'tenchi-news-update.php';
    exit;
}

$reversePrefix = 'reverse/';

if (str_starts_with($channelId, $reversePrefix) {
    $twitterHandle = substr($channelId, strlen($reversePrefix));
    require $srcDir . 'reverse.php';
    exit;
}

preg_match('/^UC[\w-]{21}[AQgw]/', $channelId, $matches);

if (count($matches) !== 1) {
    notFoundErrorResponse();
}

$config = require $projectDir . 'config.php';

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
    /** @var PDO $connection */
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

$result = $getYoutubeChannelInfos($channelId);

if (empty($result)) {
    /** @var PDO $connection */
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
