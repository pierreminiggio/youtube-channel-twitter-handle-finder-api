<?php

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

try {
    $statement = $connection->prepare('SELECT youtube_id, twitter_handle FROM channel;');
    $statement->execute();
} catch (PDOException $e) {
    $connection = null;
    internalServerErrorResponse();
}

$fetchedChannels = $statement->fetchAll();

http_response_code(200);

echo json_encode(array_map(fn (array $fetchedChannel): array => [
    'youtube_id' => $fetchedChannel['youtube_id'],
    'twitter_handle' => $fetchedChannel['twitter_handle']
], $fetchedChannels));

$connection = null;
