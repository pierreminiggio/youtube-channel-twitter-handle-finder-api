<?php

$channelId = substr($_SERVER['REQUEST_URI'], 1);

if (! $channelId) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

/*
try {
    $this->connection = new PDO(
        'mysql:host=' . $this->host . ';dbname=' . $this->database . ';charset=' . $this->charset,
        $this->username,
        $this->password
    );
} catch (PDOException $e) {
    throw new ConnectionException(
        message: 'An error occured while trying to connect to database : ' . $e->getMessage(),
        previous: $e,
    );
} 
*/
