<?php

$config = require $projectDir . 'config.php';
$token = $config['tenchi_token'];

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

if (! $authHeader || $authHeader !== 'Bearer ' . $token) {
    unauthorizedErrorResponse();
}

$body = file_get_contents('php://input');

if (! $body) {
    badRequestErrorResponse('Empty body');
}

$jsonBody = json_decode($body, true);

if (! $jsonBody) {
    badRequestErrorResponse('Bad JSON body');
}

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

$getStoredIdAndTwitterHandle = function (string $channelId) use (&$connection): ?array {
    try {
        /** @var PDO $connection */
        $statement = $connection->prepare(
            'SELECT id, twitter_handle FROM channel WHERE youtube_id = :youtube_id;'
        );
        $statement->execute(['youtube_id' => $channelId]);
    } catch (PDOException $e) {
        $connection = null;
        internalServerErrorResponse();
    }

    $fetchedChannels = $statement->fetchAll();

    if (! $fetchedChannels) {
        return null;
    }

    $fetchedChannel = $fetchedChannels[0];

    return [
        'id' => (int) $fetchedChannel['id'],
        'twitter_handle' => $fetchedChannel['twitter_handle']
    ];
};

$badChannelMessages = [];

foreach ($jsonBody as $jsonBodyEntryIndex => $jsonBodyEntry) {
    $youtubeChannelKey = 'youtube_channel_id';
    $channelId = $jsonBodyEntry[$youtubeChannelKey] ?? null;

    if (! $channelId) {
        $badChannelMessages[] = 'Missing key "' . $youtubeChannelKey . '" for entry ' . $jsonBodyEntry;
        continue;
    }

    $channelInfos = $getYoutubeChannelInfos($channelId);

    if (! $channelInfos) {
        $badChannelMessages[] =
            'Youtube channel id "' . $channelId . '" passed at entry ' . $jsonBodyEntryIndex . ' seems to be invalid';
    }

    $storedChannel = $getStoredIdAndTwitterHandle($channelId);

    if (! $storedChannel) {
        try {
            $statement = $connection->prepare(
                'INSERT INTO channel (youtube_id) VALUES (:youtube_id)'
            );
            $statement->execute(['youtube_id' => $channelId]);
        } catch (PDOException $e) {
            $connection = null;
            internalServerErrorResponse();
        }
    }

    $storedChannel = $getStoredIdAndTwitterHandle($channelId);

    if (! $storedChannel) {
        internalServerErrorResponse();
    }

    $twitterHandle = $jsonBodyEntry['twitter_screen_name'] ?? null;

    if (! $twitterHandle) {
        continue;
    }

    $storedtwitterHandle = $storedChannel['twitter_handle'];

    if ($storedtwitterHandle === null) {
        try {
            $statement = $connection->prepare(
                'UPDATE channel SET twitter_handle = :twitter_handle WHERE youtube_id = :youtube_id;'
            );
            $statement->execute(['youtube_id' => $channelId, 'twitter_handle' => $twitterHandle]);
        } catch (PDOException $e) {
            $connection = null;
            internalServerErrorResponse();
        }
    }
}

$connection = null;

if ($badChannelMessages) {
    badRequestErrorResponse(json_encode($badChannelMessages));
}

http_response_code(204);

