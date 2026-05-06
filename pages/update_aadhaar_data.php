<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized.");
}

$aadhaar_number = trim($_POST['aadhaar_number'] ?? '');
$name           = trim($_POST['name']           ?? '');
$dob            = trim($_POST['dob']            ?? '');

// Validate
$validation = validate_aadhaar_data([
    'aadhaar_number' => $aadhaar_number,
    'name'           => $name,
    'dob'            => $dob,
]);

if (!$validation['valid']) {
    die(implode(' ', $validation['errors']));
}

$clean_number = preg_replace('/\s+/', '', $aadhaar_number);

// Update existing record
if (!empty($_POST['aadhaar_data_id'])) {
    $aadhaar_data_id = (int)$_POST['aadhaar_data_id'];

    // Verify ownership via join
    $check = pg_fetch_assoc(pg_query_params($conn, "
        SELECT ad.id FROM aadhaar_data ad
        JOIN documents d ON d.id = ad.document_id
        JOIN applications a ON a.id = d.application_id
        WHERE ad.id = $1 AND a.created_by = $2
    ", [$aadhaar_data_id, $_SESSION['user_id']]));

    if (!$check) die("Not found.");

    pg_query_params($conn,
        "UPDATE aadhaar_data SET aadhaar_number = $1, name = $2, dob = $3 WHERE id = $4",
        [$clean_number, $name, $dob, $aadhaar_data_id]
    );

// Insert new record (manual entry when OCR didn't run)
} elseif (!empty($_POST['document_id'])) {
    $document_id = (int)$_POST['document_id'];

    // Verify ownership
    $check = pg_fetch_assoc(pg_query_params($conn, "
        SELECT d.id FROM documents d
        JOIN applications a ON a.id = d.application_id
        WHERE d.id = $1 AND a.created_by = $2
    ", [$document_id, $_SESSION['user_id']]));

    if (!$check) die("Not found.");

    // Delete any existing entry and re-insert
    pg_query_params($conn, "DELETE FROM aadhaar_data WHERE document_id = $1", [$document_id]);
    pg_query_params($conn,
        "INSERT INTO aadhaar_data (document_id, aadhaar_number, name, dob) VALUES ($1, $2, $3, $4)",
        [$document_id, $clean_number, $name, $dob]
    );
    pg_query_params($conn,
        "UPDATE documents SET processed_status = 'done' WHERE id = $1",
        [$document_id]
    );
} else {
    die("Invalid request.");
}

echo 'ok';
