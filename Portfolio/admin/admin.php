<?php
// Start session at the very beginning of the script
session_start();
date_default_timezone_set('Asia/Kolkata');

// --- AUTHENTICATION & ROUTING ---
$adminUser = 'admin';
$adminPass = 'Admin123';
$action = $_GET['action'] ?? 'dashboard';

// --- Handle Login Attempt (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['token']) || !isset($_SESSION['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        $login_error = "Invalid form submission. Please try again.";
    } else {
        unset($_SESSION['token']);
        if ($_POST['username'] === $adminUser && $_POST['password'] === $adminPass) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: admin.php?action=dashboard');
            exit;
        } else {
            $login_error = "Invalid username or password.";
        }
    }
}

// --- Handle Logout ---
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// --- Security Check: Redirect to login if not authenticated ---
if (!isset($_SESSION['admin'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glow-border {
            border: 1px solid #00A9FF;
            box-shadow: inset 0 0 10px rgba(0, 169, 255, 0.5), 0 0 10px rgba(0, 169, 255, 0.3);
        }
    </style>
</head>
<body class="bg-[#0A0A0B] flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-sm p-8 space-y-6 bg-gray-900/50 backdrop-blur-lg border border-gray-700/50 rounded-2xl shadow-2xl shadow-cyan-500/10">
        <h2 class="text-3xl font-bold text-center text-white">Admin Access</h2>
        <?php if (isset($login_error)): ?>
            <div class="p-3 text-sm text-center text-red-300 bg-red-500/20 rounded-lg">
                <?= htmlspecialchars($login_error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="admin.php">
            <input type="hidden" name="login" value="1">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
            <div class="space-y-4">
                <div>
                    <label for="username" class="text-sm font-medium text-gray-300">Username</label>
                    <input id="username" name="username" type="text" required class="w-full px-3 py-2 mt-1 text-white bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:outline-none" autofocus>
                </div>
                <div>
                    <label for="password" class="text-sm font-medium text-gray-300">Password</label>
                    <input id="password" name="password" type="password" required class="w-full px-3 py-2 mt-1 text-white bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                </div>
            </div>
            <button type="submit" class="w-full py-3 mt-6 font-semibold text-cyan-300 transition-all rounded-lg glow-border hover:bg-cyan-500/20">
                Authenticate
            </button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// ===================================================================
//  DASHBOARD LOGIC (This code only runs if the user is logged in)
// ===================================================================

require_once 'db_config.php';

$message = '';
$edit_id = $_GET['edit_id'] ?? null;
$edit_data = null;

function handle_db_error($conn, $query_type) {
    global $message;
    $message = "❌ Database error during $query_type: " . $conn->error;
    error_log("Database error: " . $conn->error);
}

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['token']) || !isset($_SESSION['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        $message = "❌ Invalid or expired form submission. Please try again.";
    } else {
        unset($_SESSION['token']);
        // ... (All your PHP form handling logic remains exactly the same) ...
        switch ($action) {
            case 'profile':
                $stmt = $conn->prepare("UPDATE profile SET name = ?, title = ?, email = ?, mobile = ?, about = ? WHERE id = 1");
                $stmt->bind_param("sssss", $_POST['name'], $_POST['title'], $_POST['email'], $_POST['mobile'], $_POST['about']);
                if ($stmt->execute()) $message = "✅ Profile updated successfully."; else handle_db_error($conn, 'Profile Update');
                $stmt->close();
                break;
    
            case 'education':
                if (isset($_POST['add'])) {
                    $stmt = $conn->prepare("INSERT INTO education (degree, institution, year, details) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $_POST['degree'], $_POST['institution'], $_POST['year'], $_POST['details']);
                    if ($stmt->execute()) $message = "✅ Education entry added."; else handle_db_error($conn, 'Education Insert');
                    $stmt->close();
                } elseif (isset($_POST['update'])) {
                    $stmt = $conn->prepare("UPDATE education SET degree = ?, institution = ?, year = ?, details = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $_POST['degree'], $_POST['institution'], $_POST['year'], $_POST['details'], $_POST['update']);
                    if ($stmt->execute()) $message = "✅ Education entry updated."; else handle_db_error($conn, 'Education Update');
                    $stmt->close();
                } elseif (isset($_POST['delete'])) {
                    $stmt = $conn->prepare("DELETE FROM education WHERE id = ?");
                    $stmt->bind_param("i", $_POST['delete']);
                    if ($stmt->execute()) $message = "🗑️ Entry deleted."; else handle_db_error($conn, 'Education Delete');
                    $stmt->close();
                }
                break;
            
            case 'experience':
                 if (isset($_POST['add'])) {
                    $conn->begin_transaction();
                    try {
                        $stmt_exp = $conn->prepare("INSERT INTO experience (title, company, duration) VALUES (?, ?, ?)");
                        $stmt_exp->bind_param("sss", $_POST['title'], $_POST['company'], $_POST['duration']);
                        $stmt_exp->execute();
                        $exp_id = $conn->insert_id;
                        $stmt_exp->close();
                        $stmt_resp = $conn->prepare("INSERT INTO experience_responsibilities (experience_id, responsibility) VALUES (?, ?)");
                        foreach ($_POST['responsibilities'] as $resp) { if (!empty(trim($resp))) { $stmt_resp->bind_param("is", $exp_id, $resp); $stmt_resp->execute(); } }
                        $stmt_resp->close();
                        $conn->commit();
                        $message = "✅ Experience entry added.";
                    } catch (Exception $e) { $conn->rollback(); handle_db_error($conn, 'Experience Insert Transaction'); }
                } elseif (isset($_POST['update'])) {
                    $conn->begin_transaction();
                    try {
                        $exp_id = $_POST['update'];
                        $stmt_exp = $conn->prepare("UPDATE experience SET title = ?, company = ?, duration = ? WHERE id = ?");
                        $stmt_exp->bind_param("sssi", $_POST['title'], $_POST['company'], $_POST['duration'], $exp_id);
                        $stmt_exp->execute();
                        $stmt_exp->close();
                        $conn->query("DELETE FROM experience_responsibilities WHERE experience_id = $exp_id");
                        $stmt_resp = $conn->prepare("INSERT INTO experience_responsibilities (experience_id, responsibility) VALUES (?, ?)");
                        if(isset($_POST['responsibilities'])) { foreach ($_POST['responsibilities'] as $resp) { if (!empty(trim($resp))) { $stmt_resp->bind_param("is", $exp_id, $resp); $stmt_resp->execute(); } } }
                        $stmt_resp->close();
                        $conn->commit();
                        $message = "✅ Experience entry updated.";
                    } catch (Exception $e) { $conn->rollback(); handle_db_error($conn, 'Experience Update Transaction'); }
                } elseif (isset($_POST['delete'])) {
                     $conn->begin_transaction();
                    try {
                        $stmt_resp = $conn->prepare("DELETE FROM experience_responsibilities WHERE experience_id = ?"); $stmt_resp->bind_param("i", $_POST['delete']); $stmt_resp->execute(); $stmt_resp->close();
                        $stmt_exp = $conn->prepare("DELETE FROM experience WHERE id = ?"); $stmt_exp->bind_param("i", $_POST['delete']); $stmt_exp->execute(); $stmt_exp->close();
                        $conn->commit();
                        $message = "🗑️ Entry deleted.";
                    } catch (Exception $e) { $conn->rollback(); handle_db_error($conn, 'Experience Delete Transaction'); }
                }
                break;
    
            case 'skills':
                if (isset($_POST['add_category'])) {
                    $stmt = $conn->prepare("INSERT INTO skill_categories (category) VALUES (?)"); $stmt->bind_param("s", $_POST['category']);
                    if ($stmt->execute()) $message = "✅ Category added."; else handle_db_error($conn, 'Skill Category Insert'); $stmt->close();
                } elseif (isset($_POST['add_skill'])) {
                    $stmt = $conn->prepare("INSERT INTO skills (category_id, skill) VALUES (?, ?)"); $stmt->bind_param("is", $_POST['cat_id'], $_POST['skill']);
                    if ($stmt->execute()) $message = "✅ Skill added."; else handle_db_error($conn, 'Skill Insert'); $stmt->close();
                } elseif (isset($_POST['update_skill'])) {
                    $stmt = $conn->prepare("UPDATE skills SET skill = ? WHERE id = ?"); $stmt->bind_param("si", $_POST['skill_name'], $_POST['update_skill']);
                    if ($stmt->execute()) $message = "✅ Skill updated."; else handle_db_error($conn, 'Skill Update'); $stmt->close();
                } elseif (isset($_POST['delete_skill'])) {
                    $stmt = $conn->prepare("DELETE FROM skills WHERE id = ?"); $stmt->bind_param("i", $_POST['delete_skill']);
                    if ($stmt->execute()) $message = "🗑️ Skill removed."; else handle_db_error($conn, 'Skill Delete'); $stmt->close();
                } elseif (isset($_POST['delete_category'])) {
                    $conn->begin_transaction();
                    try {
                        $stmt_skills = $conn->prepare("DELETE FROM skills WHERE category_id = ?"); $stmt_skills->bind_param("i", $_POST['delete_category']); $stmt_skills->execute(); $stmt_skills->close();
                        $stmt_cat = $conn->prepare("DELETE FROM skill_categories WHERE id = ?"); $stmt_cat->bind_param("i", $_POST['delete_category']); $stmt_cat->execute(); $stmt_cat->close();
                        $conn->commit();
                        $message = "🗑️ Category and its skills removed.";
                    } catch (Exception $e) { $conn->rollback(); handle_db_error($conn, 'Category Delete Transaction'); }
                }
                break;
            
            case 'languages':
                if (isset($_POST['add'])) {
                    $stmt = $conn->prepare("INSERT INTO languages (language, proficiency) VALUES (?, ?)"); $stmt->bind_param("ss", $_POST['language'], $_POST['proficiency']);
                    if ($stmt->execute()) $message = "✅ Language added."; else handle_db_error($conn, 'Language Insert'); $stmt->close();
                } elseif (isset($_POST['update'])) {
                    $stmt = $conn->prepare("UPDATE languages SET language = ?, proficiency = ? WHERE id = ?"); $stmt->bind_param("ssi", $_POST['language'], $_POST['proficiency'], $_POST['update']);
                    if ($stmt->execute()) $message = "✅ Language updated."; else handle_db_error($conn, 'Language Update'); $stmt->close();
                } elseif (isset($_POST['delete'])) {
                    $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?"); $stmt->bind_param("i", $_POST['delete']);
                    if ($stmt->execute()) $message = "🗑️ Language deleted."; else handle_db_error($conn, 'Language Delete'); $stmt->close();
                }
                break;
            
            case 'resumes':
                if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
                    $resumeDir = __DIR__ . '/../resume/';
                    if (!is_dir($resumeDir)) {
                        if (!mkdir($resumeDir, 0755, true)) {
                            $message = "❌ Error: Could not create the resume directory. Please check permissions.";
                            break;
                        }
                    }
                    $old_resume_res = $conn->query("SELECT filename FROM resumes LIMIT 1");
                    if ($old_resume_res && $old_resume = $old_resume_res->fetch_assoc()) {
                        $old_file_path = $resumeDir . $old_resume['filename'];
                        if (file_exists($old_file_path)) {
                            @unlink($old_file_path);
                        }
                    }
                    $conn->query("DELETE FROM resumes");
                    $original_filename = basename($_FILES['resume_file']['name']);
                    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
                    if (strtolower($file_extension) !== 'pdf') {
                         $message = "❌ Error: Only PDF files are allowed for the resume.";
                         break;
                    }
                    $new_filename = "Resume." . $file_extension;
                    $target_path = $resumeDir . $new_filename;
                    if (move_uploaded_file($_FILES['resume_file']['tmp_name'], $target_path)) {
                        $stmt = $conn->prepare("INSERT INTO resumes (filename, uploaded_at) VALUES (?, NOW())");
                        $stmt->bind_param("s", $new_filename);
                        if ($stmt->execute()) {
                            $message = "✅ Resume uploaded successfully.";
                        } else {
                            handle_db_error($conn, 'Resume Insert');
                        }
                        $stmt->close();
                    } else {
                        $message = '❌ Failed to move uploaded file. Check folder permissions.';
                    }
                } elseif (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $message = '❌ An error occurred during file upload. Error code: ' . $_FILES['resume_file']['error'];
                }
                break;
    
            case 'certifications':
                $certDir = __DIR__ . '/../certificates/';
                if (!is_dir($certDir)) mkdir($certDir, 0755, true);
    
                if (isset($_POST['reorder'])) {
                    $order = json_decode($_POST['reorder'], true);
                    $stmt = $conn->prepare("UPDATE certifications SET `order` = ? WHERE id = ?");
                    foreach ($order as $index => $id) {
                        $stmt->bind_param("ii", $index, $id);
                        $stmt->execute();
                    }
                    $stmt->close();
                    exit; 
                } elseif (isset($_POST['delete'])) {
                    $id_to_delete = $_POST['delete'];
                    $stmt_get = $conn->prepare("SELECT filename FROM certifications WHERE id = ?");
                    $stmt_get->bind_param("i", $id_to_delete);
                    $stmt_get->execute();
                    $result = $stmt_get->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (!empty($row['filename']) && file_exists($certDir . $row['filename'])) {
                            @unlink($certDir . $row['filename']);
                        }
                    }
                    $stmt_get->close();
    
                    $stmt_del = $conn->prepare("DELETE FROM certifications WHERE id = ?");
                    $stmt_del->bind_param("i", $id_to_delete);
                    if ($stmt_del->execute()) $message = '🗑️ Deleted successfully.'; else handle_db_error($conn, 'Cert Delete');
                    $stmt_del->close();
                } elseif (isset($_POST['update']) || isset($_POST['add'])) {
                    $title = $_POST['title'];
                    $date = $_POST['date'];
                    $filename = $_POST['existing_filename'] ?? null;
                    $is_uploading = isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] == UPLOAD_ERR_OK;
    
                    if ($is_uploading) {
                        if (isset($_POST['update']) && !empty($filename) && file_exists($certDir . $filename)) {
                            @unlink($certDir . $filename);
                        }
                        $new_filename = time() . '_' . basename($_FILES['cert_file']['name']);
                        if (move_uploaded_file($_FILES['cert_file']['tmp_name'], $certDir . $new_filename)) {
                            $filename = $new_filename;
                        } else {
                            $message = "❌ Error uploading file.";
                        }
                    }
    
                    if (empty($message)) {
                        if (isset($_POST['add'])) {
                            $stmt = $conn->prepare("INSERT INTO certifications (title, date, filename) VALUES (?, ?, ?)");
                            $stmt->bind_param("sss", $title, $date, $filename);
                            if ($stmt->execute()) $message = "✅ Certification added."; else handle_db_error($conn, 'Cert Add');
                        } else { 
                            $stmt = $conn->prepare("UPDATE certifications SET title = ?, date = ?, filename = ? WHERE id = ?");
                            $stmt->bind_param("sssi", $title, $date, $filename, $_POST['update']);
                            if ($stmt->execute()) $message = "✅ Certification updated."; else handle_db_error($conn, 'Cert Update');
                        }
                        $stmt->close();
                    }
                }
                break;
    
    
            case 'settings':
                if (isset($_POST['toggle_section'])) {
                    $key = 'show_' . strtolower(trim($_POST['toggle_section']));
                    $value = ($_POST['enabled'] == '1') ? '1' : '0';
                    $stmt = $conn->prepare("INSERT INTO portfolio_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                    $stmt->bind_param("ss", $key, $value);
                    if($stmt->execute()) { echo json_encode(['status' => 'success']); } else { echo json_encode(['status' => 'error']); }
                    $stmt->close();
                    exit;
                }
                break;
        }
    }
    
    if (!empty($message) && !isset($_POST['reorder'])) {
        header("Location: admin.php?action=" . $action . "&message=" . urlencode($message));
        exit;
    }
}
if(isset($_GET['message'])) { $message = htmlspecialchars($_GET['message']); }

// --- DATA FETCHING ---
// ... (All your PHP data fetching logic remains exactly the same) ...
function calculate_experience_years($durations) {
    $total_months = 0;
    foreach ($durations as $duration) { preg_match_all('/(\w+\s\d{4})\s-\s(\w+\s\d{4}|Present)/i', $duration, $matches); if (!empty($matches[1]) && !empty($matches[2])) { $start_date = new DateTime($matches[1][0]); $end_date_str = $matches[2][0]; $end_date = (strtolower($end_date_str) === 'present') ? new DateTime() : new DateTime($end_date_str); $interval = $start_date->diff($end_date); $total_months += $interval->y * 12 + $interval->m; } }
    $years = floor($total_months / 12); return $years > 0 ? $years . "+" : ($total_months > 0 ? $total_months . " M" : "0");
}
$profile = $conn->query("SELECT * FROM profile WHERE id = 1")->fetch_assoc() ?? [];
$certifications_all = $conn->query("SELECT * FROM certifications ORDER BY `order` ASC, date DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
$experience_durations = $conn->query("SELECT duration FROM experience")->fetch_all(MYSQLI_ASSOC);
$experience_years = calculate_experience_years(array_column($experience_durations, 'duration'));
$resume = $conn->query("SELECT * FROM resumes ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc() ?? null;
$latest_visitor = $conn->query("SELECT * FROM visitor_logs ORDER BY timestamp DESC LIMIT 1")->fetch_assoc() ?? null;
$visitor_regions = $conn->query("SELECT country, COUNT(*) as count FROM visitor_logs WHERE country IS NOT NULL AND country != '' AND country != 'Unknown' GROUP BY country ORDER BY count DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

if ($action == 'education') {
    $education_all = $conn->query("SELECT * FROM education ORDER BY year DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
    if ($edit_id) { $stmt = $conn->prepare("SELECT * FROM education WHERE id = ?"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close(); }
}
if ($action == 'experience') {
    $experience_raw = $conn->query("SELECT * FROM experience ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC) ?? [];
    $experience_all = [];
    foreach ($experience_raw as $exp) { $stmt = $conn->prepare("SELECT responsibility FROM experience_responsibilities WHERE experience_id = ? ORDER BY id"); $stmt->bind_param("i", $exp['id']); $stmt->execute(); $resps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $exp['responsibilities'] = array_column($resps, 'responsibility'); $experience_all[] = $exp; $stmt->close(); }
    if ($edit_id) { $stmt = $conn->prepare("SELECT * FROM experience WHERE id = ?"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close(); if ($edit_data) { $stmt = $conn->prepare("SELECT responsibility FROM experience_responsibilities WHERE experience_id = ? ORDER BY id"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $resps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $edit_data['responsibilities'] = array_column($resps, 'responsibility'); $stmt->close(); } }
}
if ($action == 'skills') {
    $skills_raw = $conn->query("SELECT sc.id as cat_id, sc.category, s.id as skill_id, s.skill FROM skill_categories sc LEFT JOIN skills s ON sc.id = s.category_id ORDER BY sc.id, s.id")->fetch_all(MYSQLI_ASSOC) ?? [];
    $skills_all = [];
    foreach ($skills_raw as $skill_item) { $cat_id = $skill_item['cat_id']; if (!isset($skills_all[$cat_id])) { $skills_all[$cat_id] = ['id' => $cat_id, 'category' => $skill_item['category'], 'skills' => []]; } if ($skill_item['skill_id']) { $skills_all[$cat_id]['skills'][] = ['id' => $skill_item['skill_id'], 'skill' => $skill_item['skill']]; } }
    $skills_all = array_values($skills_all);
    if ($edit_id) { $stmt = $conn->prepare("SELECT * FROM skills WHERE id = ?"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close(); }
}
if ($action == 'languages') {
    $languages_all = $conn->query("SELECT * FROM languages ORDER BY language ASC")->fetch_all(MYSQLI_ASSOC) ?? [];
    if ($edit_id) { $stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close(); }
}
if ($action == 'certifications' && $edit_id) {
    $stmt = $conn->prepare("SELECT * FROM certifications WHERE id = ?"); $stmt->bind_param("i", $edit_id); $stmt->execute(); $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
if ($action == 'logs') {
    $visitor_logs = $conn->query("SELECT * FROM visitor_logs ORDER BY timestamp DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC) ?? [];
}
if ($action == 'settings') {
    $config = []; $config_res = $conn->query("SELECT * FROM portfolio_config"); if ($config_res) { while($row = $config_res->fetch_assoc()) { $config[$row['config_key']] = $row['config_value']; } }
}

$_SESSION['token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Control Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --background-dark: #0A0A0B; --glass-edge: rgba(107, 114, 128, 0.1); --glow-accent: #00A9FF; --glow-accent-rgb: 0, 169, 255; --text-primary: #E5E7EB; --text-secondary: #9CA3AF; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-dark); color: var(--text-primary); overflow-x: hidden; }
        .glass-pane { background: rgba(16, 18, 27, 0.5); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid var(--glass-edge); border-radius: 1rem; box-shadow: 0 0 40px rgba(var(--glow-accent-rgb), 0.1); }
        .glow-text { color: var(--glow-accent); text-shadow: 0 0 8px rgba(var(--glow-accent-rgb), 0.7); }
        .nav-item.active { background: rgba(var(--glow-accent-rgb), 0.1); color: var(--glow-accent); box-shadow: inset 2px 0 0 var(--glow-accent); }
        .background-grid { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: linear-gradient(rgba(var(--glow-accent-rgb), 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(var(--glow-accent-rgb), 0.05) 1px, transparent 1px); background-size: 30px 30px; z-index: -1; }
        .form-input { background-color: rgba(31, 41, 55, 0.5); border: 1px solid var(--glass-edge); border-radius: 0.5rem; padding: 0.75rem; color: var(--text-primary); transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: var(--glow-accent); box-shadow: 0 0 0 2px rgba(var(--glow-accent-rgb), 0.5); }
        .btn-primary { background-color: transparent; border: 1px solid var(--glow-accent); color: var(--glow-accent); font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 0.5rem; transition: all 0.2s; text-align: center; }
        .btn-primary:hover { background-color: rgba(var(--glow-accent-rgb), 0.2); box-shadow: 0 0 15px rgba(var(--glow-accent-rgb), 0.5); }
        .sortable-ghost { opacity: 0.4; background-color: rgba(var(--glow-accent-rgb), 0.2); border-radius: 0.5rem; }
        .drag-handle { cursor: grab; } .drag-handle:active { cursor: grabbing; }
    </style>
</head>
<body class="min-h-screen">
    <div class="background-grid"></div>

    <div class="flex">
        <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 glass-pane p-4 flex flex-col z-40 transition-transform duration-300 ease-in-out">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-xl font-bold glow-text flex items-center">
                    <i data-lucide="command" class="mr-2"></i><span class="nav-text">Control Center</span>
                </h1>
                <button id="sidebar-toggle" class="p-1 rounded-md hover:bg-gray-700/50">
                    <i data-lucide="chevrons-left"></i>
                </button>
            </div>
            
            <nav class="flex-grow">
                <?php
                $nav_links = [ 'dashboard' => 'layout-dashboard', 'profile' => 'user-circle', 'education' => 'graduation-cap', 'experience' => 'briefcase', 'certifications' => 'award', 'skills' => 'lightbulb', 'languages' => 'languages', 'resumes' => 'file-text', 'logs' => 'history' ];
                foreach ($nav_links as $nav_action => $icon) { $is_active = ($action === $nav_action) ? 'active' : ''; echo "<a href='?action={$nav_action}' class='nav-item flex items-center p-3 my-1 rounded-lg transition-all duration-200 hover:bg-gray-700/50 {$is_active}'><i data-lucide='{$icon}' class='w-5 h-5 mr-3'></i><span class='nav-text'>" . ucfirst($nav_action) . "</span></a>"; }
                ?>
            </nav>
            <div class="mt-auto">
                <a href="?action=settings" class="nav-item flex items-center p-3 my-1 rounded-lg transition-all duration-200 hover:bg-gray-700/50 <?= $action === 'settings' ? 'active' : '' ?>"><i data-lucide="sliders-horizontal" class="w-5 h-5 mr-3"></i><span class="nav-text">Settings</span></a>
                <a href="?action=logout" class="nav-item flex items-center p-3 my-1 rounded-lg transition-all duration-200 text-red-400 hover:bg-red-500/20"><i data-lucide="log-out" class="w-5 h-5 mr-3"></i><span class="nav-text">Logout</span></a>
            </div>
        </aside>

        <main id="main-content" class="flex-1 p-4 sm:p-8 transition-all duration-300 ease-in-out">
            <?php if ($message): ?><div id="alert-message" class="glass-pane p-4 mb-6 text-center text-cyan-300 glow-border"><?= $message ?></div><?php endif; ?>

            <?php switch($action):
                case 'dashboard': ?>
                <section>
                    <div class="glass-pane p-6 mb-8"><h2 class="text-2xl sm:text-3xl font-bold"><?= htmlspecialchars($profile['name'] ?? '[Your Name]') ?></h2><p class="text-md sm:text-lg glow-text"><?= htmlspecialchars($profile['title'] ?? '[Your Title]') ?></p></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="glass-pane p-5"><div class="flex justify-between items-center"><h3 class="text-md font-semibold text-text-secondary">Total Certifications</h3><i data-lucide="award" class="text-glow-accent"></i></div><p class="text-4xl font-bold mt-2"><?= count($certifications_all) ?></p></div>
                        <div class="glass-pane p-5"><div class="flex justify-between items-center"><h3 class="text-md font-semibold text-text-secondary">Total Experience</h3><i data-lucide="trending-up" class="text-glow-accent"></i></div><p class="text-4xl font-bold mt-2"><?= $experience_years ?> <span class="text-xl">Years</span></p></div>
                        <div class="glass-pane p-5"><div class="flex justify-between items-center"><h3 class="text-md font-semibold text-text-secondary">Latest Visitor</h3><i data-lucide="globe" class="text-glow-accent"></i></div><p class="text-lg font-semibold mt-2"><?= htmlspecialchars($latest_visitor['city'] ?? 'N/A') ?>, <?= htmlspecialchars($latest_visitor['country'] ?? 'N/A') ?></p><p class="text-sm text-text-secondary"><?= $latest_visitor ? date('Y-m-d h:i A', strtotime($latest_visitor['timestamp'])) : 'No visitors' ?></p></div>
                        <div class="glass-pane p-5"><div class="flex justify-between items-center"><h3 class="text-md font-semibold text-text-secondary">Resume Status</h3><?php if ($resume): ?><i data-lucide="check-circle" class="text-green-400"></i><?php else: ?><i data-lucide="file-question" class="text-yellow-400"></i><?php endif; ?></div><p class="text-lg font-semibold mt-2"><?= $resume ? 'Uploaded' : 'Not Uploaded' ?></p><p class="text-sm text-text-secondary"><?= $resume ? date('Y-m-d', strtotime($resume['uploaded_at'])) : 'N/A' ?></p></div>
                    </div>
                    <div class="glass-pane p-6"><h3 class="text-lg font-semibold mb-4">Top Visitor Regions</h3><?php if(!empty($visitor_regions)): ?><ul class="space-y-3"><?php foreach($visitor_regions as $region): ?><li class="flex items-center justify-between text-sm"><span><?= htmlspecialchars($region['country']) ?></span><span class="font-bold"><?= $region['count'] ?></span></li><?php endforeach; ?></ul><?php else: ?><div class="flex items-center justify-center h-full"><p class="text-text-secondary">No visitor data.</p></div><?php endif; ?></div>
                </section>
                <?php break; ?>

                <?php case 'profile': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Profile Management</h2>
                    <div class="glass-pane p-6 sm:p-8">
                        <form method="post" action="?action=profile">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div><label class="block text-sm font-medium text-text-secondary mb-1">Name</label><input type="text" name="name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" class="form-input w-full"></div>
                                <div><label class="block text-sm font-medium text-text-secondary mb-1">Title</label><input type="text" name="title" value="<?= htmlspecialchars($profile['title'] ?? '') ?>" class="form-input w-full"></div>
                                <div><label class="block text-sm font-medium text-text-secondary mb-1">Email</label><input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="form-input w-full"></div>
                                <div><label class="block text-sm font-medium text-text-secondary mb-1">Mobile</label><input type="tel" name="mobile" value="<?= htmlspecialchars($profile['mobile'] ?? '') ?>" class="form-input w-full"></div>
                            </div>
                            <div class="mt-6"><label class="block text-sm font-medium text-text-secondary mb-1">About</label><textarea name="about" rows="8" class="form-input w-full resize-none"><?= htmlspecialchars($profile['about'] ?? '') ?></textarea></div>
                            <button type="submit" class="btn-primary mt-6 w-full">Save Changes</button>
                        </form>
                    </div>
                </section>
                <?php break; ?>
                
                <?php case 'education': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Education Management</h2>
                    <div class="glass-pane p-6 sm:p-8 mb-8">
                        <h3 class="text-xl font-bold mb-4"><?= ($edit_id && $edit_data) ? 'Editing Entry' : 'Add New Entry' ?></h3>
                        <form method="post" action="?action=education">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                            <?php if ($edit_id && $edit_data): ?><input type="hidden" name="update" value="<?= $edit_id ?>"><?php else: ?><input type="hidden" name="add" value="1"><?php endif; ?>
                            <div class="space-y-4">
                                <input type="text" name="degree" value="<?= htmlspecialchars($edit_data['degree'] ?? '') ?>" placeholder="Degree" class="form-input w-full" required>
                                <input type="text" name="institution" value="<?= htmlspecialchars($edit_data['institution'] ?? '') ?>" placeholder="Institution" class="form-input w-full" required>
                                <input type="text" name="year" value="<?= htmlspecialchars($edit_data['year'] ?? '') ?>" placeholder="Year" class="form-input w-full" required>
                                <textarea name="details" placeholder="Optional details" class="form-input w-full"><?= htmlspecialchars($edit_data['details'] ?? '') ?></textarea>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-4 mt-4">
                                <button type="submit" class="btn-primary flex-grow"><?= ($edit_id && $edit_data) ? 'Save Changes' : 'Add Education' ?></button>
                                <?php if ($edit_id && $edit_data): ?><a href="?action=education" class="btn-primary !border-gray-500 !text-gray-300 hover:!bg-gray-700/50">Cancel</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($education_all as $edu): ?>
                        <div class="glass-pane p-6 flex flex-col"><h3 class="text-lg font-bold"><?= htmlspecialchars($edu['degree']) ?></h3><p class="text-text-secondary"><?= htmlspecialchars($edu['institution']) ?></p><p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($edu['year']) ?></p><div class="mt-auto pt-4 flex gap-2"><a href="?action=education&edit_id=<?= $edu['id'] ?>" class="p-2 hover:text-glow-accent"><i data-lucide="edit-2"></i></a><form method="post" action="?action=education" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>"><input type="hidden" name="delete" value="<?= $edu['id'] ?>"><button type="submit" class="p-2 hover:text-red-500"><i data-lucide="trash-2"></i></button></form></div></div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'experience': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Experience Management</h2>
                    <div class="glass-pane p-6 sm:p-8 mb-8">
                        <h3 class="text-xl font-bold mb-4"><?= ($edit_id && $edit_data) ? 'Editing Experience' : 'Add New Experience' ?></h3>
                        <form method="post" action="?action=experience">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                            <?php if ($edit_id && $edit_data): ?><input type="hidden" name="update" value="<?= $edit_id ?>"><?php else: ?><input type="hidden" name="add" value="1"><?php endif; ?>
                            <div class="space-y-4 mb-4"><input type="text" name="title" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" placeholder="Job Title" class="form-input w-full" required><input type="text" name="company" value="<?= htmlspecialchars($edit_data['company'] ?? '') ?>" placeholder="Company" class="form-input w-full" required><input type="text" name="duration" value="<?= htmlspecialchars($edit_data['duration'] ?? '') ?>" placeholder="e.g. Jan 2020 - Present" class="form-input w-full" required></div>
                            <div id="resp-container"><label class="block text-sm font-medium text-text-secondary mb-2">Responsibilities</label>
                                <?php $resps = $edit_data['responsibilities'] ?? ['']; foreach ($resps as $resp): ?>
                                <div class="flex gap-2 mb-2"><input type="text" name="responsibilities[]" value="<?= htmlspecialchars($resp) ?>" class="form-input w-full"><button type="button" class="p-2 text-red-500 hover:bg-red-500/20 rounded-lg remove-resp"><i data-lucide="x"></i></button></div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-resp" class="text-sm mt-2 text-glow-accent hover:underline">Add Responsibility</button>
                            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                                <button type="submit" class="btn-primary flex-grow"><?= ($edit_id && $edit_data) ? 'Save Changes' : 'Add Experience' ?></button>
                                <?php if ($edit_id && $edit_data): ?><a href="?action=experience" class="btn-primary !border-gray-500 !text-gray-300 hover:!bg-gray-700/50">Cancel</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($experience_all as $exp): ?>
                        <div class="glass-pane p-6 flex flex-col"><div><h3 class="text-lg font-bold"><?= htmlspecialchars($exp['title']) ?></h3><p class="text-text-secondary"><?= htmlspecialchars($exp['company']) ?></p><p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($exp['duration']) ?></p><?php if (!empty($exp['responsibilities'])): ?><ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-400"><?php foreach ($exp['responsibilities'] as $task): ?><li><?= htmlspecialchars($task) ?></li><?php endforeach; ?></ul><?php endif; ?></div><div class="mt-auto pt-4 flex gap-2"><a href="?action=experience&edit_id=<?= $exp['id'] ?>" class="p-2 hover:text-glow-accent"><i data-lucide="edit-2"></i></a><form method="post" action="?action=experience" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>"><input type="hidden" name="delete" value="<?= $exp['id'] ?>"><button type="submit" class="p-2 hover:text-red-500"><i data-lucide="trash-2"></i></button></form></div></div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'skills': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Skills Management</h2>
                    <?php if ($edit_id && $edit_data): ?>
                    <div class="glass-pane p-6 sm:p-8 mb-8">
                        <h3 class="text-xl font-bold mb-4">Editing Skill</h3>
                        <form method="post" action="?action=skills" class="flex flex-col sm:flex-row gap-4">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                            <input type="hidden" name="update_skill" value="<?= $edit_id ?>">
                            <input type="text" name="skill_name" value="<?= htmlspecialchars($edit_data['skill']) ?>" class="form-input w-full">
                            <button type="submit" class="btn-primary">Save</button>
                            <a href="?action=skills" class="btn-primary !border-gray-500 !text-gray-300 hover:!bg-gray-700/50">Cancel</a>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($skills_all as $group): ?>
                        <div class="glass-pane p-6 space-y-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-bold"><?= htmlspecialchars($group['category']) ?></h3>
                                <form method="post" action="?action=skills" onsubmit="return confirm('Delete category and all its skills?')">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                    <input type="hidden" name="delete_category" value="<?= $group['id'] ?>">
                                    <button type="submit" class="p-2 hover:text-red-500"><i data-lucide="trash-2"></i></button>
                                </form>
                            </div>
                            <ul class="space-y-2">
                                <?php foreach($group['skills'] as $skill): ?>
                                <li class="flex justify-between items-center bg-gray-800/50 p-2 rounded-md">
                                    <span><?= htmlspecialchars($skill['skill']) ?></span>
                                    <div class="flex items-center gap-2">
                                        <a href="?action=skills&edit_id=<?= $skill['id'] ?>" class="p-1 hover:text-glow-accent"><i data-lucide="edit-2" class="w-4 h-4"></i></a>
                                        <form method="post" action="?action=skills" onsubmit="return confirm('Delete skill?')">
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                            <input type="hidden" name="delete_skill" value="<?= $skill['id'] ?>">
                                            <button type="submit" class="p-1 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </form>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <form method="post" action="?action=skills" class="flex gap-2">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                <input type="hidden" name="add_skill" value="1"><input type="hidden" name="cat_id" value="<?= $group['id'] ?>">
                                <input type="text" name="skill" class="form-input w-full" placeholder="Add new skill..." required>
                                <button type="submit" class="btn-primary p-2"><i data-lucide="plus"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                     <div class="glass-pane p-6 sm:p-8 mt-8">
                        <h3 class="text-xl font-bold mb-4">Add New Skill Category</h3>
                        <form method="post" action="?action=skills" class="flex flex-col sm:flex-row gap-4">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                            <input type="hidden" name="add_category" value="1">
                            <input type="text" name="category" placeholder="Category Name" class="form-input w-full" required>
                            <button type="submit" class="btn-primary">Add Category</button>
                        </form>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'languages': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Languages Management</h2>
                     <div class="glass-pane p-6 sm:p-8 mb-8">
                        <h3 class="text-xl font-bold mb-4"><?= ($edit_id && $edit_data) ? 'Editing Language' : 'Add New Language' ?></h3>
                        <form method="post" action="?action=languages" class="flex flex-col sm:flex-row flex-wrap gap-4">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                             <?php if ($edit_id && $edit_data): ?><input type="hidden" name="update" value="<?= $edit_id ?>"><?php else: ?><input type="hidden" name="add" value="1"><?php endif; ?>
                            <input type="text" name="language" value="<?= htmlspecialchars($edit_data['language'] ?? '') ?>" placeholder="Language" class="form-input flex-grow" required>
                            <input type="text" name="proficiency" value="<?= htmlspecialchars($edit_data['proficiency'] ?? '') ?>" placeholder="Proficiency" class="form-input flex-grow" required>
                            <div class="w-full sm:w-auto flex gap-4">
                               <button type="submit" class="btn-primary flex-grow"><?= ($edit_id && $edit_data) ? 'Save' : 'Add' ?></button>
                               <?php if ($edit_id && $edit_data): ?><a href="?action=languages" class="btn-primary !border-gray-500 !text-gray-300 hover:!bg-gray-700/50 flex-grow">Cancel</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                         <?php foreach($languages_all as $lang): ?>
                        <div class="glass-pane p-6 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold"><?= htmlspecialchars($lang['language']) ?></h3>
                                <p class="text-text-secondary"><?= htmlspecialchars($lang['proficiency']) ?></p>
                            </div>
                            <div class="flex gap-2">
                                <a href="?action=languages&edit_id=<?= $lang['id'] ?>" class="p-2 hover:text-glow-accent"><i data-lucide="edit-2"></i></a>
                                <form method="post" action="?action=languages" onsubmit="return confirm('Delete this language?')">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                    <input type="hidden" name="delete" value="<?= $lang['id'] ?>">
                                    <button type="submit" class="p-2 hover:text-red-500"><i data-lucide="trash-2"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'certifications': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Certifications Management</h2>
                    <div class="glass-pane p-6 sm:p-8 mb-8">
                        <h3 class="text-xl font-bold mb-4"><?= ($edit_id && $edit_data) ? 'Editing Certification' : 'Add New Certification' ?></h3>
                        <form method="post" action="?action=certifications" enctype="multipart/form-data">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                             <?php if ($edit_id && $edit_data): ?><input type="hidden" name="update" value="<?= $edit_id ?>"><?php else: ?><input type="hidden" name="add" value="1"><?php endif; ?>
                            <div class="space-y-4">
                                <input type="text" name="title" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" placeholder="Certification Title" class="form-input w-full" required>
                                <div>
                                    <label class="block text-sm font-medium text-text-secondary mb-1">Date</label>
                                    <input type="date" name="date" value="<?= htmlspecialchars($edit_data['date'] ?? '') ?>" class="form-input w-full" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-secondary mb-1"><?= ($edit_id && $edit_data) ? 'Replace File (Optional)' : 'Certificate File (Image/PDF)' ?></label>
                                    <input type="file" name="cert_file" accept="image/*,.pdf" class="form-input w-full file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-cyan-500/10 file:text-cyan-300 hover:file:bg-cyan-500/20">
                                    <input type="hidden" name="existing_filename" value="<?= htmlspecialchars($edit_data['filename'] ?? '') ?>">
                                </div>
                            </div>
                             <?php if ($edit_id && !empty($edit_data['filename'])):
                                $filepath = '../certificates/' . $edit_data['filename'];
                                if (file_exists($filepath)):
                            ?>
                            <div class="mt-4 p-4 bg-gray-800/50 rounded-lg">
                                <h4 class="text-md font-semibold text-text-secondary mb-2">Current File Preview</h4>
                                <div class="flex items-center">
                                    <?php
                                    $is_pdf = strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) == 'pdf';
                                    if ($is_pdf): ?>
                                        <i data-lucide="file-type-2" class="w-10 h-10 text-red-400 mr-4"></i>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($filepath) ?>" class="w-20 h-auto object-cover rounded-md mr-4">
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($filepath) ?>" target="_blank" class="text-cyan-400 hover:underline">View Current File</a>
                                </div>
                            </div>
                            <?php endif; endif; ?>
                            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                                <button type="submit" class="btn-primary flex-grow"><?= ($edit_id && $edit_data) ? 'Save Changes' : 'Add Certification' ?></button>
                                <?php if ($edit_id && $edit_data): ?><a href="?action=certifications" class="btn-primary !border-gray-500 !text-gray-300 hover:!bg-gray-700/50">Cancel</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="glass-pane p-6">
                        <h3 class="text-xl font-bold mb-4">Existing Certifications</h3>
                        <p class="text-sm text-text-secondary mb-4">Drag and drop to reorder.</p>
                        <div id="cert-list" class="space-y-4">
                            <?php foreach($certifications_all as $cert): ?>
                            <div class="flex items-center bg-gray-800/50 p-3 rounded-lg" data-id="<?= $cert['id'] ?>">
                                <i data-lucide="grip-vertical" class="drag-handle text-text-secondary mr-3"></i>
                                <div class="flex-grow">
                                    <p class="font-semibold"><?= htmlspecialchars($cert['title']) ?></p>
                                    <p class="text-sm text-text-secondary"><?= date("F j, Y", strtotime($cert['date'])) ?></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php $filepath = '../certificates/' . ($cert['filename'] ?? ''); if (!empty($cert['filename']) && file_exists($filepath)): ?>
                                    <a href="<?= htmlspecialchars($filepath) ?>" target="_blank" class="p-2 hover:text-green-400" title="View File"><i data-lucide="eye" class="w-5 h-5"></i></a>
                                    <?php endif; ?>
                                    <a href="?action=certifications&edit_id=<?= $cert['id'] ?>" class="p-2 hover:text-glow-accent" title="Edit"><i data-lucide="edit-2" class="w-5 h-5"></i></a>
                                    <form method="post" action="?action=certifications" onsubmit="return confirm('Delete this certification?')">
                                        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                        <input type="hidden" name="delete" value="<?= $cert['id'] ?>">
                                        <button type="submit" class="p-2 hover:text-red-500" title="Delete"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'resumes': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Resume Management</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="glass-pane p-8">
                            <h3 class="text-xl font-bold mb-4">Upload New Resume</h3>
                            <p class="text-sm text-text-secondary mb-4">Uploading a new PDF will replace the existing one and rename it to "Resume.pdf".</p>
                            <form method="post" action="?action=resumes" enctype="multipart/form-data">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']); ?>">
                                <input type="file" name="resume_file" accept=".pdf" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-cyan-500/10 file:text-cyan-300 hover:file:bg-cyan-500/20" required>
                                <button type="submit" class="btn-primary mt-4 w-full">Upload</button>
                            </form>
                        </div>
                        <div class="glass-pane p-8">
                             <h3 class="text-xl font-bold mb-4">Current Resume</h3>
                             <?php if($resume): ?>
                             <div class="flex items-center justify-between bg-gray-800/50 p-4 rounded-lg">
                                <div>
                                    <p class="font-semibold flex items-center"><i data-lucide="file-text" class="w-4 h-4 mr-2"></i><?= htmlspecialchars($resume['filename']) ?></p>
                                    <p class="text-sm text-text-secondary mt-1">Uploaded on: <?= date("F j, Y", strtotime($resume['uploaded_at'])) ?></p>
                                </div>
                                <div class="flex items-center gap-2">
                                     <a href="../resume/<?= htmlspecialchars($resume['filename']) ?>" target="_blank" class="p-2 hover:text-green-400" title="View Resume"><i data-lucide="eye"></i></a>
                                 </div>
                             </div>
                             <?php else: ?>
                             <p class="text-text-secondary text-center py-8">No resume has been uploaded yet.</p>
                             <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php break; ?>
                
                <?php case 'logs': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Visitor Logs</h2>
                    <div class="glass-pane p-6">
                        <h3 class="text-xl font-bold mb-4">Recent 50 Visitors</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-text-secondary uppercase bg-gray-700/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">IP Address</th>
                                        <th scope="col" class="px-6 py-3">Location</th>
                                        <th scope="col" class="px-6 py-3">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($visitor_logs)): ?>
                                        <?php foreach($visitor_logs as $log): ?>
                                        <tr class="border-b border-gray-700">
                                            <td class="px-6 py-4 font-medium whitespace-nowrap"><?= htmlspecialchars($log['ip_address']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($log['city'] . ', ' . $log['region'] . ', ' . $log['country']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars(date('M j, Y, g:i a', strtotime($log['timestamp']))) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-8 text-text-secondary">No visitor logs found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php break; ?>

                <?php case 'settings': ?>
                <section>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Section Visibility</h2>
                    <div class="glass-pane p-6 sm:p-8">
                        <div class="space-y-4">
                            <?php $sections = ['profile', 'education', 'experience', 'skills', 'certifications', 'languages', 'resumes'];
                            foreach ($sections as $section_name):
                                $config_key = 'show_' . $section_name;
                                $is_visible = $config[$config_key] ?? '1';
                            ?>
                            <div class="flex justify-between items-center bg-gray-800/50 p-4 rounded-lg">
                                <span class="text-lg font-medium"><?= ucwords($section_name) ?></span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                  <input type="checkbox" value="" class="sr-only peer" onchange="toggleSection('<?= $section_name ?>', this.checked)" <?= $is_visible == '1' ? 'checked' : '' ?>>
                                  <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-focus:ring-4 peer-focus:ring-cyan-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php break; ?>


                <?php default: ?>
                <section><h2 class="text-2xl sm:text-3xl font-bold mb-6 glow-text">Page Not Found</h2><div class="glass-pane p-8"><p>The page requested (<?= htmlspecialchars($action) ?>) does not exist.</p></div></section>
            <?php endswitch; ?>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleButton = document.getElementById('sidebar-toggle');
        const navTexts = document.querySelectorAll('.nav-text');

        const isMobile = () => window.innerWidth < 768;

        const setSidebarState = (collapsed) => {
            if (collapsed) {
                sidebar.classList.replace('w-64', 'w-20');
                mainContent.style.marginLeft = '5rem'; // w-20
                navTexts.forEach(t => t.classList.add('hidden'));
                toggleButton.innerHTML = '<i data-lucide="chevrons-right"></i>';
            } else {
                sidebar.classList.replace('w-20', 'w-64');
                mainContent.style.marginLeft = '16rem'; // w-64
                navTexts.forEach(t => t.classList.remove('hidden'));
                toggleButton.innerHTML = '<i data-lucide="chevrons-left"></i>';
            }
            lucide.createIcons();
        };
        
        toggleButton.addEventListener('click', () => {
            setSidebarState(sidebar.classList.contains('w-64'));
        });

        // Initial setup
        if (isMobile()) {
            setSidebarState(true);
        } else {
            mainContent.style.marginLeft = '16rem'; // Default for desktop
        }
        
        // --- Responsibility Fields ---
        const respContainer = document.getElementById('resp-container');
        const addRespBtn = document.getElementById('add-resp');
        if(respContainer && addRespBtn) {
            const addField = () => { const div = document.createElement('div'); div.className = 'flex gap-2 mb-2'; div.innerHTML = `<input type="text" name="responsibilities[]" class="form-input w-full"><button type="button" class="p-2 text-red-500 hover:bg-red-500/20 rounded-lg remove-resp"><i data-lucide="x"></i></button>`; respContainer.appendChild(div); lucide.createIcons(); };
            addRespBtn.addEventListener('click', addField);
            respContainer.addEventListener('click', e => { const rmBtn = e.target.closest('.remove-resp'); if (rmBtn) rmBtn.parentElement.remove(); });
        }
        
        // --- Sortable Certifications ---
        const certList = document.getElementById('cert-list');
        if (certList) {
            new Sortable(certList, {
                animation: 150, handle: '.drag-handle', ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const order = Array.from(certList.children).map(item => item.getAttribute('data-id'));
                    saveCertOrder(order);
                },
            });
        }
        
        // --- Alert Message Auto-hide ---
        const alertMessage = document.getElementById('alert-message');
        if (alertMessage) {
            setTimeout(() => {
                alertMessage.style.transition = 'opacity 0.5s ease';
                alertMessage.style.opacity = '0';
                setTimeout(() => alertMessage.remove(), 500);
            }, 5000);
        }
    });

    function saveCertOrder(order) {
        fetch('?action=certifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `reorder=${JSON.stringify(order)}&token=<?= htmlspecialchars($_SESSION['token']); ?>`
        }).catch(error => console.error('Failed to save order:', error));
    }

    function toggleSection(section, isEnabled) {
        fetch('?action=settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `toggle_section=${section}&enabled=${isEnabled ? '1' : '0'}&token=<?= htmlspecialchars($_SESSION['token']); ?>`
        }).then(res => { if(!res.ok) console.error('Failed to update section.'); });
    }
    </script>
</body>
</html>
