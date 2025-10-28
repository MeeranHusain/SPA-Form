<?php
// get_states.php
include 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['country_id'])) {
    echo json_encode([]);
    exit;
}

$country_id = (int) $_GET['country_id'];

try {
    $stmt = $conn->prepare("SELECT id, state_name FROM states WHERE country_id = ? ORDER BY state_name ASC");
    $stmt->execute([$country_id]);
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($states);
} catch (Exception $e) {
    echo json_encode([]);
}
