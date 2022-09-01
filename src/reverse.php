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
    $statement = $connection->prepare('SELECT youtube_id FROM channel WHERE twitter_handle = :twitter_handle;');
    $statement->execute(['twitter_handle' => $twitterHandle]);
} catch (PDOException $e) {
    $connection = null;
    internalServerErrorResponse();
}

$fetchedChannels = $statement->fetchAll();

if (! $fetchedChannels) {
    notFoundErrorResponse();
}

$channelsInfos = [];

foreach ($fetchedChannels as $fetchedChannel) {
    $channelId = $fetchedChannel['youtube_id'];

    if (! $channelId) {
        internalServerErrorResponse();
        exit;
    }

    $channelInfos = $getYoutubeChannelInfos($channelId);

    if (! $channelInfos) {
        internalServerErrorResponse();
        exit;
    }
    
    $channelsInfos[] = $channelInfos;
}

http_response_code(200);

echo '[' . implode(',', $channelInfos) . ']';

$connection = null;
