<?php
/**
 * Crob API - JSON endpoints for the web interface
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../src/Crob.php';

$crob = new Crob();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'intro':
        echo json_encode(['response' => $crob->introduce()]);
        break;

    case 'ask':
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';
        echo json_encode(['response' => $crob->ask($question)]);
        break;

    case 'stats':
        echo json_encode($crob->stats());
        break;

    case 'queue':
        echo json_encode(['queue' => $crob->queue()]);
        break;

    case 'learn':
        $result = $crob->backgroundLearn();
        echo json_encode([
            'success' => $result !== null,
            'result' => $result,
        ]);
        break;

    case 'teach':
        $input = json_decode(file_get_contents('php://input'), true);
        $topic = $input['topic'] ?? '';
        $fact = $input['fact'] ?? '';
        echo json_encode(['response' => $crob->teach($topic, $fact)]);
        break;

    case 'knows':
        $topic = $_GET['topic'] ?? '';
        echo json_encode(['knowledge' => $crob->knows($topic)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
