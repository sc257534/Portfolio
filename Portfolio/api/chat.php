<?php
// Set headers and disable error reporting for a clean production environment.
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// --- CONFIGURATION ---
// IMPORTANT: Make sure this is your NEW, SECRET API key.
$apiKey = 'Your Gemini Api Key';

// --- MAIN AI LOGIC ---
try {
    if ($apiKey === 'Your Gemini Api Key') {
        throw new Exception('AI Configuration Error: The API key has not been set in the backend file.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_question = strtolower(trim($input['question'] ?? ''));

    if (empty($user_question)) {
        throw new Exception('Please ask a question.');
    }

    // --- DYNAMIC DATA FETCHING BASED ON USER INTENT ---
    
    // Include your database configuration.
    require_once __DIR__ . '/../admin/db_config.php';
    if ($conn->connect_error) {
        throw new Exception("Error: Could not connect to the database.");
    }

    $context = '';
    $topic = 'general information';

    // Determine user intent based on keywords.
    if (preg_match('/experience|work|job|role|UTIITSL/i', $user_question)) {
        $topic = 'work experience';
        $context .= "### Work Experience:\n";
        $exp_res = $conn->query("SELECT * FROM experience ORDER BY id DESC");
        while ($job = $exp_res->fetch_assoc()) {
            $context .= "- **" . $job['title'] . "** at " . $job['company'] . " (" . $job['duration'] . ")\n";
            $resp_stmt = $conn->prepare("SELECT responsibility FROM experience_responsibilities WHERE experience_id = ?");
            $resp_stmt->bind_param("i", $job['id']);
            $resp_stmt->execute();
            $resp_res = $resp_stmt->get_result();
            while ($resp = $resp_res->fetch_assoc()) {
                $context .= "  - " . $resp['responsibility'] . "\n";
            }
        }
    } elseif (preg_match('/skill|know|proficient|tally|technical|tool/i', $user_question)) {
        $topic = 'skills';
        $context .= "### Skills:\n";
        $skills_res = $conn->query("SELECT sc.category, s.skill FROM skills s JOIN skill_categories sc ON s.category_id = sc.id ORDER BY sc.id, s.id");
        $current_category = '';
        while ($skill = $skills_res->fetch_assoc()) {
            if ($skill['category'] !== $current_category) {
                $current_category = $skill['category'];
                $context .= "- **" . $current_category . ":** ";
            }
            $context .= $skill['skill'] . ", ";
        }
        $context = rtrim($context, ", ") . "\n";
    } elseif (preg_match('/education|degree|college|university|study/i', $user_question)) {
        $topic = 'education';
        $context .= "### Education:\n";
        $edu_res = $conn->query("SELECT * FROM education ORDER BY year DESC");
        while ($edu = $edu_res->fetch_assoc()) {
            $context .= "- **" . $edu['degree'] . "** from " . $edu['institution'] . " (" . $edu['year'] . ")\n";
        }
    } elseif (preg_match('/certificate|certification|certified|license/i', $user_question)) {
        $topic = 'certifications';
        $context .= "### Certifications:\n";
        $cert_res = $conn->query("SELECT title, date FROM certifications ORDER BY date DESC");
        while ($cert = $cert_res->fetch_assoc()) {
             $context .= "- " . $cert['title'] . "\n";
        }
    } elseif (preg_match('/resume|cv|download/i', $user_question)) {
        $topic = 'resume';
        $resume = $conn->query("SELECT filename FROM resumes ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc();
        $context .= "### Resume Information:\n- The latest resume file is named '" . $resume['filename'] . "'. The user can download it from the main page.\n";
    } else {
        // Default context if no specific keywords are matched.
        $profile = $conn->query("SELECT name, title, email, mobile, about FROM profile WHERE id = 1")->fetch_assoc();
        $context .= "### General Profile Information:\n";
        $context .= "- **Name:** " . $profile['name'] . "\n";
        $context .= "- **Title:** " . $profile['title'] . "\n";
        $context .= "- **Summary:** " . $profile['about'] . "\n";
        $context .= "- **Contact:** Email is " . $profile['email'] . " and Mobile is " . $profile['mobile'] . "\n";
    }
    
    $conn->close();
    
    // --- ADVANCED PROMPT ENGINEERING ---
    $prompt = "You are AI, a highly intelligent and conversational assistant for Your_name portfolio. Your personality is helpful, professional, and slightly friendly.

Your task is to answer the user's question based *only* on the specific context provided below.
- First, understand the user's question.
- Then, use the provided context to formulate a natural, conversational answer.
- Do NOT mention the words 'context' or 'knowledge base' in your response. Act as if you know this information yourself.
- If the context doesn't contain the answer, politely state that you don't have the specific details and suggest contacting owner.
- Do not repeat the question in your answer. Just give the answer directly.

**Provided Context on the topic of '{$topic}':**
{$context}

**User's Question:**
\"{$user_question}\"";

    // --- API CALL ---
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        // A lower temperature makes the model more focused and factual.
        'generationConfig' => ['temperature' => 0.2, 'topP' => 0.8, 'topK' => 10]
    ];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        throw new Exception('API Error: ' . $result['error']['message']);
    }

    $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? "I seem to be having trouble thinking clearly right now. Please try asking again.";
    echo json_encode(['answer' => $answer]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['answer' => 'Error: ' . $e->getMessage()]);
}
?>