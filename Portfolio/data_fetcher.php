<?php
// Start a session to handle user-related logic if needed.
session_start();

// Include the database connection file.
require_once __DIR__ . '/admin/db_config.php';

// Get the current page from the URL, defaulting to 'home'
$page = $_GET['page'] ?? 'home';

// --- VISITOR LOGGING (Only on homepage) ---
if ($page === 'home') {
    // Get visitor's IP address. Handle different server variables.
    $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $log_path = __DIR__ . '/admin/data/visitor_logs.json';

    if ($ip_address && $conn) {
        $stmt_check = $conn->prepare("SELECT ip_address FROM visitor_logs WHERE ip_address = ? AND DATE(timestamp) = CURDATE()");
        $stmt_check->bind_param("s", $ip_address);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows == 0) {
            // Geolocation lookup
            $location = ['city' => 'Unknown', 'region' => 'Unknown', 'country' => 'Unknown'];
            $api_url = "http://ip-api.com/json/" . $ip_address;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout to prevent page slowness
            $api_response = curl_exec($ch);
            curl_close($ch);
            
            if ($api_response) {
                $geo_data = json_decode($api_response, true);
                if ($geo_data['status'] === 'success') {
                    $location['city'] = $geo_data['city'] ?? 'Unknown';
                    $location['region'] = $geo_data['regionName'] ?? 'Unknown';
                    $location['country'] = $geo_data['country'] ?? 'Unknown';
                }
            }
            
            $stmt_insert = $conn->prepare("INSERT INTO visitor_logs (ip_address, city, region, country) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $ip_address, $location['city'], $location['region'], $location['country']);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
// --- END VISITOR LOGGING ---

// Fetch all necessary data for the portfolio from the database
$profile = $conn->query("SELECT * FROM profile WHERE id = 1")->fetch_assoc() ?? [];
$education = $conn->query("SELECT * FROM education ORDER BY `year` DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
$experience_raw = $conn->query("SELECT * FROM experience ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
$skills_raw = $conn->query("SELECT sc.id AS cat_id, sc.category, s.id AS skill_id, s.skill FROM skill_categories sc LEFT JOIN skills s ON sc.id = s.category_id ORDER BY sc.id")->fetch_all(MYSQLI_ASSOC) ?? [];
$certifications = $conn->query("SELECT * FROM certifications ORDER BY `order` ASC, date DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
$resumes = $conn->query("SELECT * FROM resumes ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
$config = [];
$config_res = $conn->query("SELECT * FROM portfolio_config");
if ($config_res) {
    while($row = $config_res->fetch_assoc()) {
        $config[$row['config_key']] = $row['config_value'];
    }
}

// Group experience responsibilities
$experience = [];
foreach ($experience_raw as $exp) {
    $sql_resp = "SELECT responsibility FROM experience_responsibilities WHERE experience_id = ? ORDER BY id";
    if ($stmt = $conn->prepare($sql_resp)) {
        $stmt->bind_param("i", $exp['id']);
        $stmt->execute();
        $resps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $exp['responsibilities'] = array_column($resps, 'responsibility');
        $experience[] = $exp;
        $stmt->close();
    }
}

// Group skills by category
$skills = [];
foreach ($skills_raw as $skill_item) {
    $cat_id = $skill_item['cat_id'];
    if (!isset($skills[$cat_id])) {
        $skills[$cat_id] = [
            'id' => $cat_id,
            'category' => $skill_item['category'],
            'skills' => []
        ];
    }
    if ($skill_item['skill_id']) {
        $skills[$cat_id]['skills'][] = ['id' => $skill_item['skill_id'], 'skill' => $skill_item['skill']];
    }
}

// Get latest resume for download link
$latestResumeFile = "Santosh_Chowdhury_CV.pdf";
$resumeDownloadPath = "resume/" . $latestResumeFile;

// Set the page title and check visibility based on the retrieved configuration
$pageTitle = 'Home';
$isVisible = true;

switch ($page) {
    case 'home':
        $pageTitle = htmlspecialchars($profile['name']);
        break;
    case 'profile':
        $pageTitle = 'Profile';
        $isVisible = $config['show_profile'] ?? true;
        break;
    case 'experience':
        $pageTitle = 'Experience';
        $isVisible = $config['show_experience'] ?? true;
        break;
    case 'education':
        $pageTitle = 'Education';
        $isVisible = $config['show_education'] ?? true;
        break;
    case 'skills':
        $pageTitle = 'Skills';
        $isVisible = $config['show_skills'] ?? true;
        break;
    case 'certifications':
        $pageTitle = 'Certifications';
        $isVisible = $config['show_certifications'] ?? true;
        break;
    case 'languages':
        $pageTitle = 'Languages';
        $isVisible = $config['show_languages'] ?? true;
        break;
    case 'privacy':
        $pageTitle = 'Privacy Policy';
        break;
    default:
        $pageTitle = 'Page Not Found';
        $isVisible = false;
        break;
}

if (!$isVisible) {
    echo "This section is currently hidden by the admin.";
    exit;
}
?>
