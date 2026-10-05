<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

$repair_id = isset($_GET['repair_id']) ? intval($_GET['repair_id']) : 0;

// Get repair details
$repair = null;
if ($repair_id) {
    $repair = $conn->query("SELECT r.*, u.full_name as technician_name FROM repairs r LEFT JOIN users u ON r.technician_id = u.id WHERE r.id = $repair_id")->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_solution') {
        $repair_id = intval($_POST['repair_id']);
        $problem_keywords = trim($_POST['problem_keywords']);
        $solution_description = trim($_POST['solution_description']);
        $steps_taken = trim($_POST['steps_taken']);
        $parts_used = trim($_POST['parts_used']);
        $time_required = trim($_POST['time_required']);
        $difficulty_level = $_POST['difficulty_level'];
        $tags = trim($_POST['tags']);
        
        if (empty($solution_description)) {
            $error = 'Solution description is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO repair_solutions (repair_id, technician_id, problem_keywords, solution_description, steps_taken, parts_used, time_required, difficulty_level, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssss", $repair_id, $user['id'], $problem_keywords, $solution_description, $steps_taken, $parts_used, $time_required, $difficulty_level, $tags);
            
            if ($stmt->execute()) {
                $message = 'Solution saved successfully!';
            } else {
                $error = 'Error saving solution.';
            }
        }
    } elseif ($action === 'update_solution') {
        $solution_id = intval($_POST['solution_id']);
        $problem_keywords = trim($_POST['problem_keywords']);
        $solution_description = trim($_POST['solution_description']);
        $steps_taken = trim($_POST['steps_taken']);
        $parts_used = trim($_POST['parts_used']);
        $time_required = trim($_POST['time_required']);
        $difficulty_level = $_POST['difficulty_level'];
        $tags = trim($_POST['tags']);
        
        $stmt = $conn->prepare("UPDATE repair_solutions SET problem_keywords=?, solution_description=?, steps_taken=?, parts_used=?, time_required=?, difficulty_level=?, tags=? WHERE id=?");
        $stmt->bind_param("sssssssi", $problem_keywords, $solution_description, $steps_taken, $parts_used, $time_required, $difficulty_level, $tags, $solution_id);
        
        if ($stmt->execute()) {
            $message = 'Solution updated successfully!';
        } else {
            $error = 'Error updating solution.';
        }
    }
}

// Find similar past repairs based on device type, brand, and problem keywords
$similar_repairs = [];
if ($repair) {
    $device_type = $conn->real_escape_string($repair['device_type']);
    $device_brand = $conn->real_escape_string($repair['device_brand'] ?? '');
    $problem_words = explode(' ', strtolower($repair['problem_description']));
    
    // Build similarity query
    $where = "WHERE r.status IN ('completed', 'delivered') AND r.id != $repair_id";
    if ($device_brand) {
        $where .= " AND (r.device_type = '$device_type' OR r.device_brand = '$device_brand')";
    } else {
        $where .= " AND r.device_type = '$device_type'";
    }
    
    $similar_repairs = $conn->query("
        SELECT r.*, rs.solution_description, rs.steps_taken, rs.parts_used, rs.time_required, rs.difficulty_level, rs.tags,
               u.full_name as technician_name
        FROM repairs r
        LEFT JOIN repair_solutions rs ON r.id = rs.repair_id
        LEFT JOIN users u ON r.technician_id = u.id
        $where
        ORDER BY r.completed_date DESC
        LIMIT 10
    ");
}

// Get solution for current repair if exists
$current_solution = null;
if ($repair_id) {
    $current_solution = $conn->query("SELECT rs.*, u.full_name as technician_name FROM repair_solutions rs LEFT JOIN users u ON rs.technician_id = u.id WHERE rs.repair_id = $repair_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Solutions - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f7fafc; }
        .solutions-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        .solutions-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .solutions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .solution-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .solution-card h3 {
            color: #1a365d;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .difficulty-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .difficulty-easy { background: #c6f6d5; color: #22543d; }
        .difficulty-medium { background: #feebc8; color: #744210; }
        .difficulty-hard { background: #fed7d7; color: #742a2a; }
        .difficulty-expert { background: #e9d8fd; color: #44337a; }
        .search-online {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .search-online input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            margin-top: 10px;
        }
        .search-online button {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        .search-online button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .steps-list {
            list-style: none;
            padding: 0;
        }
        .steps-list li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }
        .steps-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #38a169;
            font-weight: bold;
        }
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
        .tag {
            background: #ebf8ff;
            color: #2c5282;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        .no-solutions {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .no-solutions i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="solutions-container">
        <div class="solutions-header">
            <h1><i class="fas fa-lightbulb"></i> Repair Solutions</h1>
            <p>Document and find solutions for repair problems</p>
            <?php if ($repair): ?>
                <div style="margin-top: 15px; padding: 15px; background: #f7fafc; border-radius: 8px; border-left: 4px solid #3182ce;">
                    <strong>Current Repair:</strong> <?php echo htmlspecialchars($repair['ticket_number']); ?>
                    <br>
                    <strong>Device:</strong> <?php echo htmlspecialchars($repair['device_type']); ?>
                    <?php if ($repair['device_brand']): ?> - <?php echo htmlspecialchars($repair['device_brand']); ?><?php endif; ?>
                    <?php if ($repair['device_model']): ?> <?php echo htmlspecialchars($repair['device_model']); ?><?php endif; ?>
                    <br>
                    <strong>Problem:</strong> <?php echo htmlspecialchars(substr($repair['problem_description'], 0, 100)); ?>...
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="solutions-grid">
            <!-- Left Column: Current Solution & Similar Solutions -->
            <div>
                <!-- Search Online -->
                <div class="search-online">
                    <h3><i class="fas fa-globe"></i> Search Online Solutions</h3>
                    <p>Find solutions from the internet for similar problems</p>
                    <?php if ($repair): ?>
                        <input type="text" id="onlineSearchQuery" placeholder="Search query..." value="<?php echo htmlspecialchars($repair['device_type'] . ' ' . ($repair['device_brand'] ?? '') . ' ' . substr($repair['problem_description'], 0, 50)); ?>">
                    <?php else: ?>
                        <input type="text" id="onlineSearchQuery" placeholder="Enter problem description...">
                    <?php endif; ?>
                    <button type="button" onclick="searchOnline()">
                        <i class="fas fa-search"></i> Search on Google
                    </button>
                    <button type="button" onclick="searchYouTube()" style="margin-left: 10px;">
                        <i class="fab fa-youtube"></i> Search YouTube
                    </button>
                </div>
                
                <!-- Similar Past Repairs -->
                <div class="solution-card">
                    <h3><i class="fas fa-history"></i> Similar Past Repairs</h3>
                    <?php if ($similar_repairs && $similar_repairs->num_rows > 0): ?>
                        <?php while ($similar = $similar_repairs->fetch_assoc()): ?>
                            <div style="padding: 15px; background: #f7fafc; border-radius: 8px; margin-bottom: 15px; border-left: 3px solid #3182ce;">
                                <div style="font-weight: 600; color: #1a365d; margin-bottom: 8px;">
                                    <?php echo htmlspecialchars($similar['ticket_number']); ?>
                                    <span style="float: right; font-size: 0.85rem; color: #718096;">
                                        <?php echo date('M d, Y', strtotime($similar['completed_date'] ?? $similar['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.9rem; color: #4a5568; margin-bottom: 8px;">
                                    <strong>Device:</strong> <?php echo htmlspecialchars($similar['device_type']); ?>
                                    <?php if ($similar['device_brand']): ?> - <?php echo htmlspecialchars($similar['device_brand']); ?><?php endif; ?>
                                </div>
                                <div style="font-size: 0.9rem; color: #4a5568; margin-bottom: 8px;">
                                    <strong>Problem:</strong> <?php echo htmlspecialchars(substr($similar['problem_description'], 0, 80)); ?>...
                                </div>
                                <?php if ($similar['solution_description']): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: white; border-radius: 5px; border: 1px solid #e2e8f0;">
                                        <strong><i class="fas fa-lightbulb" style="color: #d69e2e;"></i> Solution:</strong>
                                        <p style="margin: 5px 0; font-size: 0.9rem; color: #2d3748;">
                                            <?php echo htmlspecialchars($similar['solution_description']); ?>
                                        </p>
                                        <?php if ($similar['steps_taken']): ?>
                                            <div style="margin-top: 8px;">
                                                <strong>Steps:</strong>
                                                <ul class="steps-list">
                                                    <?php foreach (explode("\n", $similar['steps_taken']) as $step): ?>
                                                        <?php if (trim($step)): ?>
                                                            <li><?php echo htmlspecialchars(trim($step)); ?></li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($similar['difficulty_level']): ?>
                                            <span class="difficulty-badge difficulty-<?php echo $similar['difficulty_level']; ?>">
                                                <?php echo ucfirst($similar['difficulty_level']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($similar['technician_name']): ?>
                                            <span style="margin-left: 10px; font-size: 0.8rem; color: #718096;">
                                                <i class="fas fa-user-cog"></i> <?php echo htmlspecialchars($similar['technician_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-solutions">
                            <i class="fas fa-history"></i>
                            <p>No similar past repairs found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column: Document Solution -->
            <div>
                <div class="solution-card">
                    <h3><i class="fas fa-edit"></i> Document Solution</h3>
                    <?php if ($repair): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $current_solution ? 'update_solution' : 'add_solution'; ?>">
                            <input type="hidden" name="repair_id" value="<?php echo $repair_id; ?>">
                            <?php if ($current_solution): ?>
                                <input type="hidden" name="solution_id" value="<?php echo $current_solution['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Problem Keywords</label>
                                <input type="text" name="problem_keywords" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_solution['problem_keywords'] ?? ''); ?>"
                                       placeholder="e.g., screen replacement, battery issue, software update">
                                <small style="color: #718096;">Keywords to help find this solution later</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Solution Description *</label>
                                <textarea name="solution_description" class="form-control" rows="4" required
                                          placeholder="Describe the solution you applied..."><?php echo htmlspecialchars($current_solution['solution_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Steps Taken</label>
                                <textarea name="steps_taken" class="form-control" rows="4"
                                          placeholder="List the steps you took (one per line)..."><?php echo htmlspecialchars($current_solution['steps_taken'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Parts Used</label>
                                <input type="text" name="parts_used" class="form-control"
                                       value="<?php echo htmlspecialchars($current_solution['parts_used'] ?? ''); ?>"
                                       placeholder="e.g., LCD screen, battery, screws">
                            </div>
                            
                            <div class="form-group">
                                <label>Time Required</label>
                                <input type="text" name="time_required" class="form-control"
                                       value="<?php echo htmlspecialchars($current_solution['time_required'] ?? ''); ?>"
                                       placeholder="e.g., 30 minutes, 1 hour">
                            </div>
                            
                            <div class="form-group">
                                <label>Difficulty Level</label>
                                <select name="difficulty_level" class="form-control">
                                    <option value="easy" <?php echo ($current_solution['difficulty_level'] ?? '') === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                    <option value="medium" <?php echo ($current_solution['difficulty_level'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="hard" <?php echo ($current_solution['difficulty_level'] ?? '') === 'hard' ? 'selected' : ''; ?>>Hard</option>
                                    <option value="expert" <?php echo ($current_solution['difficulty_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Tags</label>
                                <input type="text" name="tags" class="form-control"
                                       value="<?php echo htmlspecialchars($current_solution['tags'] ?? ''); ?>"
                                       placeholder="e.g., hardware, software, screen, battery">
                                <small style="color: #718096;">Comma-separated tags for categorization</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> <?php echo $current_solution ? 'Update Solution' : 'Save Solution'; ?>
                            </button>
                            <a href="repairs.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                                <i class="fas fa-arrow-left"></i> Back to Repairs
                            </a>
                        </form>
                    <?php else: ?>
                        <div class="no-solutions">
                            <i class="fas fa-tools"></i>
                            <p>Please select a repair from the repairs page to document a solution</p>
                            <a href="repairs.php" class="btn btn-primary" style="margin-top: 15px;">
                                <i class="fas fa-arrow-left"></i> Go to Repairs
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function searchOnline() {
            const query = document.getElementById('onlineSearchQuery').value;
            console.log('Search Online clicked. Query:', query);
            if (query && query.trim() !== '') {
                const searchUrl = 'https://www.google.com/search?q=' + encodeURIComponent(query + ' repair solution');
                console.log('Opening URL:', searchUrl);
                window.open(searchUrl, '_blank');
            } else {
                alert('Please enter a search query first.');
            }
        }
        
        function searchYouTube() {
            const query = document.getElementById('onlineSearchQuery').value;
            console.log('Search YouTube clicked. Query:', query);
            if (query && query.trim() !== '') {
                const searchUrl = 'https://www.youtube.com/results?search_query=' + encodeURIComponent(query + ' repair tutorial');
                console.log('Opening URL:', searchUrl);
                window.open(searchUrl, '_blank');
            } else {
                alert('Please enter a search query first.');
            }
        }
    </script>
</body>
</html>
