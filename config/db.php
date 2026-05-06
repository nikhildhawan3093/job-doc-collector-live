<?php
$db_url = getenv('DATABASE_URL');

if ($db_url) {
    // Railway provides DATABASE_URL — parse it
    $parts = parse_url($db_url);
    $conn  = pg_connect(sprintf(
        "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'], '/'),
        $parts['user'],
        rawurldecode($parts['pass'])
    ));
} else {
    // Local XAMPP fallback
    $conn = pg_connect("host=localhost port=5432 dbname=jdc user=postgres password=flowtrade");
}

if (!$conn) {
    die("Database connection failed.");
}
