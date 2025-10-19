<?php
// ===================================================================
// START: CONFIGURATION, DATABASE CONNECTION & DATA FETCHING
// ===================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/admin/db_config.php';

$page = $_GET['page'] ?? 'home';

// --- VISITOR LOGGING ---
if ($page === 'home' && isset($conn) && $conn) {
    $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    if ($ip_address) {
        $stmt_check = $conn->prepare("SELECT ip_address FROM visitor_logs WHERE ip_address = ? AND DATE(timestamp) = CURDATE()");
        $stmt_check->bind_param("s", $ip_address);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows == 0) {
            $location = ['city' => 'Unknown', 'region' => 'Unknown', 'country' => 'Unknown'];
            $api_url = "http://ip-api.com/json/" . $ip_address;
            
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $api_response = curl_exec($ch);
            curl_close($ch);
            
            if ($api_response && ($geo_data = json_decode($api_response, true)) && $geo_data['status'] === 'success') {
                $location = [
                    'city' => $geo_data['city'] ?? 'Unknown',
                    'region' => $geo_data['regionName'] ?? 'Unknown',
                    'country' => $geo_data['country'] ?? 'Unknown'
                ];
            }
            
            $stmt_insert = $conn->prepare("INSERT INTO visitor_logs (ip_address, city, region, country) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $ip_address, $location['city'], $location['region'], $location['country']);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
// ===================================================================
// START: CONFIGURATION, DATABASE CONNECTION & DATA FETCHING
// ===================================================================

// Function to safely execute a query and return results or an empty array on failure
function safeQueryFetchAll($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// --- FETCH ALL PORTFOLIO DATA ---
$profile = $conn->query("SELECT * FROM profile WHERE id = 1")->fetch_assoc() ?? [];
$education = safeQueryFetchAll($conn, "SELECT * FROM education ORDER BY `year` DESC");
$experience_raw = safeQueryFetchAll($conn, "SELECT * FROM experience ORDER BY id DESC");
$skills_raw = safeQueryFetchAll($conn, "SELECT sc.id AS cat_id, sc.category, s.id AS skill_id, s.skill FROM skill_categories sc LEFT JOIN skills s ON sc.id = s.category_id ORDER BY sc.id, s.id");
$certifications = safeQueryFetchAll($conn, "SELECT * FROM certifications ORDER BY `order` ASC, date DESC");
$languages = safeQueryFetchAll($conn, "SELECT * FROM languages ORDER BY id");
$resume = $conn->query("SELECT filename FROM resumes ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc() ?? null;
$resumeDownloadPath = $resume ? 'resume/' . htmlspecialchars($resume['filename']) : '#';

// Fetch display configuration
$config = [];
$config_res = $conn->query("SELECT * FROM portfolio_config");
if ($config_res) {
    while($row = $config_res->fetch_assoc()) {
        $config[$row['config_key']] = (bool)$row['config_value'];
    }
}

// Process experience data to include responsibilities.
$experience = [];
foreach ($experience_raw as $exp) {
    $stmt = $conn->prepare("SELECT responsibility FROM experience_responsibilities WHERE experience_id = ? ORDER BY id");
    $stmt->bind_param("i", $exp['id']);
    $stmt->execute();
    $resps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $exp['responsibilities'] = array_column($resps, 'responsibility');
    $experience[] = $exp;
    $stmt->close();
}

// Group skills by their category for structured display.
$skills = [];
foreach ($skills_raw as $skill_item) {
    $cat_id = $skill_item['cat_id'];
    if (!isset($skills[$cat_id])) {
        $skills[$cat_id] = ['id' => $cat_id, 'category' => $skill_item['category'], 'skills' => []];
    }
    if ($skill_item['skill_id']) {
        $skills[$cat_id]['skills'][] = ['id' => $skill_item['skill_id'], 'skill' => $skill_item['skill']];
    }
}
$skills = array_values($skills);

if ($page === 'privacy') {
    $pageTitle = 'Privacy Policy';
} else {
    $pageTitle = htmlspecialchars($profile['name'] ?? 'Portfolio');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Digital Portfolio</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ===================================================================
           THEME: PROFESSIONAL FUTURISM
           =================================================================== */

        :root {
            --bg-color: #12141D;
            --surface-color: #1A1D29;
            --primary-color: #00A9FF;
            --secondary-color: #E5E7EB;
            --text-color: #A0AEC0;
            --border-color: #2D3748;
            --shadow-color: rgba(0, 169, 255, 0.1);
            --font-primary: 'Roboto', sans-serif;
            --font-secondary: 'Open Sans', sans-serif;
        }

        /* --- Base & Reset --- */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px; /* Offset for fixed header */
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-secondary);
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3 {
            font-family: var(--font-primary);
            color: var(--secondary-color);
            font-weight: 700;
        }

        h2.section-title {
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            padding-bottom: 1rem;
        }

        h2.section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }

        p {
            margin-bottom: 1rem;
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        a:hover {
            color: var(--secondary-color);
        }

        .container {
            max-width: 1200px;
            width: 90%;
            margin: 0 auto;
        }

        section {
            padding: 6rem 0;
        }

        /* --- Header & Navigation --- */
        #top-nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(18, 20, 29, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            transition: top 0.3s;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .nav-logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--secondary-color);
        }

        #top-nav nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
        }

        #top-nav nav a {
            color: var(--text-color);
            font-weight: 600;
            position: relative;
            padding: 5px 0;
        }

        #top-nav nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        #top-nav nav a:hover::after,
        #top-nav nav a.active::after {
            transform: scaleX(1);
        }

        #menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--secondary-color);
            font-size: 1.5rem;
            cursor: pointer;
        }


        /* --- Hero Section --- */
        #hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 70px; /* Nav height */
        }

        .hero-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            align-items: center;
            gap: 4rem;
        }

        .hero-name {
            font-size: 4rem;
            margin-bottom: 0.5rem;
        }

        .hero-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .hero-bio {
            max-width: 60ch;
            margin-bottom: 2rem;
        }

        .hero-contact {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .hero-contact a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero-image img {
            width: 100%;
            max-width: 350px;
            border-radius: 50%;
            border: 5px solid var(--surface-color);
            box-shadow: 0 0 30px var(--shadow-color);
        }

        /* --- Buttons --- */
        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--bg-color);
            border: 2px solid var(--primary-color);
        }

        .btn-primary:hover {
            background-color: transparent;
            color: var(--primary-color);
        }

        /* --- Experience Timeline --- */
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .timeline::after {
            content: '';
            position: absolute;
            width: 3px;
            background-color: var(--border-color);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -1.5px;
        }

        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            right: -10px;
            background-color: var(--bg-color);
            border: 4px solid var(--primary-color);
            top: 25px;
            border-radius: 50%;
            z-index: 1;
        }

        .timeline-item:nth-child(even)::after {
            left: -10px;
        }

        .timeline-content {
            padding: 2rem;
            background-color: var(--surface-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .timeline-content h3 { font-size: 1.25rem; }
        .timeline-content .company { color: var(--primary-color); font-weight: 600; margin-bottom: 0.5rem; }
        .timeline-content .duration { font-size: 0.9rem; margin-bottom: 1rem; }
        .timeline-content details { font-size: 0.9rem; }
        .timeline-content summary { cursor: pointer; font-weight: 600; }
        .timeline-content ul { list-style-position: inside; padding-left: 1rem; margin-top: 0.5rem; }
        .timeline-content li { margin-bottom: 0.5rem; }

        /* --- Grids & Cards (Education, Certs, Langs) --- */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .card {
            background: var(--surface-color);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px var(--shadow-color);
        }

        .card .institution, .card .year {
            color: var(--text-color);
        }

        .certification-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            cursor: pointer;
        }
        .cert-icon { font-size: 2rem; color: var(--primary-color); }
        .cert-text h3 { font-size: 1.1rem; }

        .language-card { text-align: center; }

        /* --- Skills Section --- */
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .skill-category {
            background: var(--surface-color);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .skill-category h3 { margin-bottom: 1.5rem; }
        .skill-tags { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .skill-chip {
            background: var(--bg-color);
            color: var(--secondary-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
        }

        /* --- Footer --- */
        footer {
            background-color: var(--surface-color);
            text-align: center;
            padding: 2rem 0;
            margin-top: 4rem;
            border-top: 1px solid var(--border-color);
        }
        .social-links { margin: 1rem 0; }
        .social-links a { font-size: 1.5rem; margin: 0 0.75rem; }

        /* --- Modal (for Certifications) --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
            align-items: center;
            justify-content: center;
        }
        .modal-wrapper { position: relative; }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90vw;
            max-height: 85vh;
        }
        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 10px 0;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            background: none;
            border: none;
            cursor: pointer;
        }
        .close:hover, .close:focus { color: var(--primary-color); }

        /* --- Privacy Page --- */
        .privacy-policy { padding: 8rem 0 4rem; }
        .privacy-policy-container {
            background: var(--surface-color);
            padding: 3rem;
            border-radius: 8px;
        }
        .privacy-policy h1 { font-size: 2.5rem; }
        .privacy-policy h2 { font-size: 1.8rem; margin-top: 2rem; margin-bottom: 1rem; }

        /* --- AI Chat Widget (NEW) --- */
        .ai-bot-float {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 1010;
            transition: transform 0.3s ease;
        }
        .ai-bot-float:hover {
            transform: scale(1.1);
        }
        .ai-bot-float svg {
            width: 32px;
            height: 32px;
        }
        .ai-bot-chat {
            position: fixed;
            bottom: 100px;
            right: 25px;
            width: 350px;
            max-width: 90vw;
            height: 500px;
            max-height: 70vh;
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            opacity: 0;
            pointer-events: none;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1009;
        }
        .ai-bot-chat.open {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }
        .ai-bot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .ai-bot-header span {
            font-weight: bold;
            color: var(--secondary-color);
        }
        .ai-bot-close {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .ai-bot-messages {
            flex-grow: 1;
            padding: 1rem;
            overflow-y: auto;
        }
        .ai-bot-msg {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 15px;
            max-width: 80%;
            line-height: 1.5;
        }
        .ai-bot-msg-user {
            background-color: var(--primary-color);
            color: var(--bg-color);
            border-bottom-right-radius: 3px;
            margin-left: auto;
        }
        .ai-bot-msg-bot {
            background-color: var(--bg-color);
            color: var(--text-color);
            border-bottom-left-radius: 3px;
            margin-right: auto;
        }
        .ai-bot-form {
            display: flex;
            padding: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .ai-bot-form input {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            background-color: var(--bg-color);
            color: var(--secondary-color);
            padding: 0.75rem;
            border-radius: 5px 0 0 5px;
            outline: none;
        }
        .ai-bot-form button {
            border: 1px solid var(--primary-color);
            background-color: var(--primary-color);
            color: var(--bg-color);
            padding: 0.75rem 1rem;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            font-weight: bold;
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .hero-content { grid-template-columns: 1fr; text-align: center; }
            .hero-text { order: 2; }
            .hero-image { order: 1; margin-bottom: 2rem; }
            .hero-image img { margin: 0 auto; }
            .hero-contact { justify-content: center; }
        }

        @media (max-width: 768px) {
            h2.section-title { font-size: 2rem; }
            #top-nav nav {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: var(--surface-color);
            }
            #top-nav nav.active { display: block; }
            #top-nav nav ul { flex-direction: column; padding: 1rem; gap: 0; }
            #top-nav nav li { width: 100%; }
            #top-nav nav a { display: block; padding: 1rem; }
            #menu-toggle { display: block; }

            .timeline::after { left: 10px; }
            .timeline-item { width: 100%; padding-left: 40px; padding-right: 10px; }
            .timeline-item:nth-child(even) { left: 0; }
            .timeline-item::after { left: 1px; }
        }
    </style>
</head>
<body>

    <?php if ($page === 'privacy'): ?>
        <main class="container privacy-policy">
             <div class="privacy-policy-container">
                <h1>Privacy Policy</h1>
                <p><strong>Last updated:</strong> <?= date("F j, Y") ?></p>
                <p>This website is a personal portfolio for Santosh Chowdhury. Your privacy is important, and this policy explains how your information is handled when you visit this site.</p>
                <h2>Information Collection</h2>
                <p>This website logs the IP address and approximate location (city, country) of visitors for analytical purposes to understand website traffic. No other personal information is collected automatically. You may choose to contact owner via the email, phone, or social media links provided. Any information you provide through these channels is voluntary.</p>
                <h2>Cookies & Analytics</h2>
                <p>This website does not use cookies for tracking. Anonymized visitor logs are used for simple traffic analysis.</p>
                <h2>Third-Party Links</h2>
                <p>This website contains links to third-party sites (e.g., LinkedIn, Instagram). Please review the privacy policies of those sites, as this website is not responsible for their content or practices.</p>
                <h2>Contact</h2>
                <p>If you have any questions about this privacy policy, please contact owner at <a href="mailto:<?= htmlspecialchars($profile['email'] ?? '') ?>"><?= htmlspecialchars($profile['email'] ?? '') ?></a>.</p>
                <hr>
                <p style="text-align:center;"><a href="admin/admin.php" class="admin-login-link">Admin Login</a></p>
                <div style="margin-top: 2rem; text-align: center;"><a href="index.php" class="btn">← Back to Home</a></div>
            </div>
        </main>
    <?php else: ?>
        <header id="top-nav">
            <div class="container nav-container">
                <a href="#hero" class="nav-logo"><?= htmlspecialchars(strtok($profile['name'] ?? 'S C', ' ')) ?></a>
                <nav>
                    <ul>
                        <?php if($config['show_experience'] ?? true): ?><li><a href="#experience">Experience</a></li><?php endif; ?>
                        <?php if($config['show_education'] ?? true): ?><li><a href="#education">Education</a></li><?php endif; ?>
                        <?php if($config['show_skills'] ?? true): ?><li><a href="#skills">Skills</a></li><?php endif; ?>
                        <?php if($config['show_certifications'] ?? true): ?><li><a href="#certifications">Certifications</a></li><?php endif; ?>
                        <?php if($config['show_languages'] ?? true): ?><li><a href="#languages">Languages</a></li><?php endif; ?>
                    </ul>
                </nav>
                 <button id="menu-toggle" aria-label="Open Menu"><i class="fas fa-bars"></i></button>
            </div>
        </header>

        <main>
            <section id="hero" class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1 class="hero-name"><?= htmlspecialchars($profile['name'] ?? 'Your Name') ?></h1>
                        <p class="hero-title"><?= htmlspecialchars($profile['title'] ?? 'Your Professional Title') ?></p>
                        <p class="hero-bio"><?= htmlspecialchars($profile['about'] ?? 'Your professional summary goes here.') ?></p>
                        <div class="hero-contact">
                            <a href="mailto:<?= htmlspecialchars($profile['email'] ?? '') ?>"><i class="fas fa-envelope"></i> <?= htmlspecialchars($profile['email'] ?? '') ?></a>
                            <span>&bull;</span>
                            <a href="tel:<?= htmlspecialchars($profile['mobile'] ?? '') ?>"><i class="fas fa-phone"></i> <?= htmlspecialchars($profile['mobile'] ?? '') ?></a>
                        </div>
                        <a href="<?= $resumeDownloadPath ?>" class="btn btn-primary" download="Resume.pdf"><i class="fas fa-download"></i> Download Resume</a>
                    </div>
                    <div class="hero-image">
                        <img src="images/profile.jpg" alt="Profile photo of <?= htmlspecialchars($profile['name'] ?? '') ?>">
                    </div>
                </div>
            </section>

            <?php if($config['show_experience'] ?? true): ?>
            <section id="experience" class="container">
                <h2 class="section-title">Experience</h2>
                <div class="timeline">
                    <?php foreach ($experience as $job): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <h3><?= htmlspecialchars($job['title']) ?></h3>
                            <p class="company"><?= htmlspecialchars($job['company']) ?></p>
                            <p class="duration"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($job['duration']) ?></p>
                            <?php if (!empty($job['responsibilities'])): ?>
                            <details class="responsibilities">
                                <summary>View Responsibilities</summary>
                                <ul>
                                    <?php foreach ($job['responsibilities'] as $task): ?>
                                    <li><?= htmlspecialchars($task) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if($config['show_education'] ?? true): ?>
            <section id="education" class="container">
                <h2 class="section-title">Education</h2>
                <div class="grid-container">
                    <?php foreach ($education as $edu): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($edu['degree']) ?></h3>
                        <p class="institution"><?= htmlspecialchars($edu['institution']) ?></p>
                        <p class="year"><?= htmlspecialchars($edu['year']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if($config['show_skills'] ?? true): ?>
            <section id="skills" class="container">
                <h2 class="section-title">Skills</h2>
                <div class="skills-grid">
                    <?php foreach ($skills as $group): ?>
                    <div class="skill-category">
                        <h3><?= htmlspecialchars($group['category']) ?></h3>
                        <div class="skill-tags">
                            <?php foreach ($group['skills'] as $skill): ?>
                            <span class="skill-chip"><?= htmlspecialchars($skill['skill']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if($config['show_certifications'] ?? true): ?>
            <section id="certifications" class="container">
                <h2 class="section-title">Certifications</h2>
                <div class="grid-container certifications-grid">
                    <?php foreach ($certifications as $cert):
                        $filename = htmlspecialchars($cert['filename']);
                        $title = htmlspecialchars($cert['title']);
                        $date = date("M Y", strtotime($cert['date']));
                    ?>
                    <div class="card certification-card" onclick="showModal('certificates/<?= $filename ?>', '<?= $title ?>')">
                        <div class="cert-icon"><i class="fas fa-award"></i></div>
                        <div class="cert-text">
                            <h3><?= $title ?></h3>
                            <p>Issued: <?= $date ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if($config['show_languages'] ?? true): ?>
            <section id="languages" class="container">
                <h2 class="section-title">Languages</h2>
                 <div class="grid-container">
                    <?php foreach ($languages as $lang): ?>
                    <div class="card language-card">
                        <h3><?= htmlspecialchars($lang['language']) ?></h3>
                        <p><?= htmlspecialchars($lang['proficiency']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </main>

        <footer>
            <div class="container footer-content">
                <p>&copy; <?= date("Y") ?> <?= htmlspecialchars($profile['name'] ?? '') ?>. All Rights Reserved.</p>
                <div class="social-links">
                    <a href="https://www.linkedin.com/in/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="https://www.instagram.com/____" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/1234567890" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
                 <a href="?page=privacy" class="privacy-link">Privacy Policy</a>
            </div>
        </footer>

        <div id="certificate-modal" class="modal" style="display:none;" role="dialog" aria-modal="true">
            <button class="close" title="Close" aria-label="Close modal">&times;</button>
            <div class="modal-wrapper">
                <img class="modal-content" id="expanded-img" alt="Full certificate view">
            </div>
            <div id="caption"></div>
        </div>
        
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Mobile Navigation Toggle ---
            const menuToggle = document.getElementById('menu-toggle');
            const nav = document.querySelector('#top-nav nav');

            menuToggle.addEventListener('click', () => {
                nav.classList.toggle('active');
                const icon = menuToggle.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            // Close mobile menu when a link is clicked
            document.querySelectorAll('#top-nav nav a').forEach(link => {
                link.addEventListener('click', () => {
                    if (nav.classList.contains('active')) {
                        nav.classList.remove('active');
                        menuToggle.querySelector('i').className = 'fas fa-bars';
                    }
                });
            });

            // --- Active Nav Link Highlighting on Scroll ---
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('#top-nav nav a');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href').substring(1) === entry.target.id) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, { rootMargin: '-50% 0px -50% 0px' });

            sections.forEach(section => {
                observer.observe(section);
            });

            // --- Modal Logic for Certifications ---
            window.showModal = function(src, captionText) {
                const modal = document.getElementById("certificate-modal");
                if (!modal) return;
                
                const modalImg = document.getElementById("expanded-img");
                const caption = document.getElementById("caption");

                modal.style.display = "flex";
                modalImg.src = src;
                caption.innerHTML = captionText;
                document.body.style.overflow = 'hidden';
            }

            const modal = document.getElementById("certificate-modal");
            if (modal) {
                const closeModal = () => {
                    modal.style.display = "none";
                    document.body.style.overflow = 'auto';
                };

                modal.querySelector(".close").addEventListener('click', closeModal);
                
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }
        });
    </script>
    <script src="js/ai.js"></script>
</body>
</html>