<?php
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle goal creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_goal'])) {
    $goal_type = $_POST['goal_type'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $target_value = $_POST['target_value'];
    $current_value = $_POST['current_value'];
    $unit = $_POST['unit'];
    $start_date = $_POST['start_date'];
    $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
    
    // Validate input
    if (empty($goal_type) || empty($title) || empty($target_value) || empty($start_date)) {
        $error = "Goal type, title, target value, and start date are required";
    } else {
        // Verify user exists in the database
        $check_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_result = $check_user_stmt->get_result();
        
        if ($check_user_result->num_rows === 0) {
            // User doesn't exist, redirect to login
            session_destroy();
            header("Location: login.php");
            exit();
        }
        
        // Insert goal - Fixed the SQL query
        $insert_sql = "INSERT INTO fitness_goals (user_id, goal_type, title, description, target_value, current_value, unit, start_date, target_date, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        $insert_stmt = $conn->prepare($insert_sql);
        
        if ($insert_stmt === false) {
            $error = "Error preparing statement: " . $conn->error;
        } else {
            $insert_stmt->bind_param("isssddsss", $user_id, $goal_type, $title, $description, $target_value, $current_value, $unit, $start_date, $target_date);
            
            if ($insert_stmt->execute()) {
                $goal_id = $conn->insert_id;
                
                // Add initial progress record
                $progress_sql = "INSERT INTO goal_progress (goal_id, value, notes, recorded_date) VALUES (?, ?, 'Initial value', ?)";
                $progress_stmt = $conn->prepare($progress_sql);
                
                if ($progress_stmt === false) {
                    $error = "Error preparing progress statement: " . $conn->error;
                } else {
                    $progress_stmt->bind_param("ids", $goal_id, $current_value, $start_date);
                    $progress_stmt->execute();
                    $progress_stmt->close();
                    
                    $success = "Goal created successfully!";
                }
            } else {
                $error = "Error creating goal: " . $insert_stmt->error;
            }
            
            $insert_stmt->close();
        }
    }
}

// Handle goal update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_goal'])) {
    $goal_id = $_POST['goal_id'];
    $new_value = $_POST['new_value'];
    $notes = $_POST['notes'];
    $recorded_date = $_POST['recorded_date'];
    
    // Check if goal belongs to user
    $check_sql = "SELECT * FROM fitness_goals WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $goal_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        $goal = $check_result->fetch_assoc();
        
        // Add progress record
        $progress_sql = "INSERT INTO goal_progress (goal_id, value, notes, recorded_date) VALUES (?, ?, ?, ?)";
        $progress_stmt = $conn->prepare($progress_sql);
        
        if ($progress_stmt === false) {
            $error = "Error preparing progress statement: " . $conn->error;
        } else {
            $progress_stmt->bind_param("idss", $goal_id, $new_value, $notes, $recorded_date);
            
            if ($progress_stmt->execute()) {
                // Update current value in goal
                $update_sql = "UPDATE fitness_goals SET current_value = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                
                if ($update_stmt === false) {
                    $error = "Error preparing update statement: " . $conn->error;
                } else {
                    $update_stmt->bind_param("di", $new_value, $goal_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Check if goal is completed
                    if (($goal['goal_type'] == 'weight_loss' && $new_value <= $goal['target_value']) || 
                        ($goal['goal_type'] != 'weight_loss' && $new_value >= $goal['target_value'])) {
                        // Mark goal as completed
                        $complete_sql = "UPDATE fitness_goals SET status = 'completed' WHERE id = ?";
                        $complete_stmt = $conn->prepare($complete_sql);
                        
                        if ($complete_stmt === false) {
                            $error = "Error preparing complete statement: " . $conn->error;
                        } else {
                            $complete_stmt->bind_param("i", $goal_id);
                            $complete_stmt->execute();
                            $complete_stmt->close();
                            
                            $success = "Goal updated and marked as completed!";
                        }
                    } else {
                        $success = "Goal progress updated successfully!";
                    }
                }
            } else {
                $error = "Error updating goal progress: " . $progress_stmt->error;
            }
            
            $progress_stmt->close();
        }
    } else {
        $error = "Invalid goal or you don't have permission to update it";
    }
    
    $check_stmt->close();
}

// Handle goal deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_goal'])) {
    $goal_id = $_POST['goal_id'];
    
    // Check if goal belongs to user
    $check_sql = "SELECT * FROM fitness_goals WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $goal_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        // Delete goal
        $delete_sql = "DELETE FROM fitness_goals WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        
        if ($delete_stmt === false) {
            $error = "Error preparing delete statement: " . $conn->error;
        } else {
            $delete_stmt->bind_param("i", $goal_id);
            
            if ($delete_stmt->execute()) {
                $success = "Goal deleted successfully!";
            } else {
                $error = "Error deleting goal: " . $delete_stmt->error;
            }
            
            $delete_stmt->close();
        }
    } else {
        $error = "Invalid goal or you don't have permission to delete it";
    }
    
    $check_stmt->close();
}

// Get user's goals with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$goals_sql = "SELECT * FROM fitness_goals WHERE user_id = ? ORDER BY status ASC, target_date ASC LIMIT ? OFFSET ?";
$goals_stmt = $conn->prepare($goals_sql);

if ($goals_stmt === false) {
    $error = "Error preparing goals statement: " . $conn->error;
} else {
    $goals_stmt->bind_param("iii", $user_id, $limit, $offset);
    $goals_stmt->execute();
    $goals_result = $goals_stmt->get_result();
}

// Get total goals count for pagination
$count_sql = "SELECT COUNT(*) as total FROM fitness_goals WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);

if ($count_stmt === false) {
    $error = "Error preparing count statement: " . $conn->error;
} else {
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $total_goals = $count_result['total'];
    $total_pages = ceil($total_goals / $limit);
}

// Get goal types and units for dropdowns
$goal_types = [
    'weight_loss' => 'Weight Loss',
    'muscle_gain' => 'Muscle Gain',
    'endurance' => 'Endurance',
    'strength' => 'Strength',
    'flexibility' => 'Flexibility',
    'general_fitness' => 'General Fitness'
];

$units = [
    'kg' => 'Kilograms (kg)',
    'lbs' => 'Pounds (lbs)',
    'cm' => 'Centimeters (cm)',
    'in' => 'Inches (in)',
    'km' => 'Kilometers (km)',
    'mi' => 'Miles (mi)',
    'min' => 'Minutes (min)',
    'reps' => 'Repetitions',
    'steps' => 'Steps',
    '%' => 'Percentage (%)'
];

// Determine the current action
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$goal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If updating a goal, get the goal data
$goal = null;
if ($action == 'update' && $goal_id > 0) {
    $goal_sql = "SELECT * FROM fitness_goals WHERE id = ? AND user_id = ?";
    $goal_stmt = $conn->prepare($goal_sql);
    
    if ($goal_stmt === false) {
        $error = "Error preparing goal statement: " . $conn->error;
    } else {
        $goal_stmt->bind_param("ii", $goal_id, $user_id);
        $goal_stmt->execute();
        $goal_result = $goal_stmt->get_result();
        
        if ($goal_result->num_rows === 1) {
            $goal = $goal_result->fetch_assoc();
        } else {
            $error = "Invalid goal or you don't have permission to update it";
            $action = 'list';
        }
        
        $goal_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Goals - FitGoals</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #00b8d4;
            --primary-dark: #0088a3;
            --secondary: #26c6da;
            --light: #e0f7fa;
            --dark: #263238;
            --success: #00c853;
            --warning: #ffc107;
            --error: #f44336;
            --gray: #607d8b;
            --light-gray: #eceff1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: #f5f5f5;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 1.5rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            color: var(--light);
        }
        
        .btn {
            display: inline-block;
            background-color: white;
            color: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: var(--light);
            transform: translateY(-2px);
        }
        
        /* Goals Styles */
        .goals-section {
            padding: 2rem 0;
        }
        
        .section-header {
            margin-bottom: 2rem;
        }
        
        .section-header h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .section-header p {
            color: var(--gray);
        }
        
        .action-buttons {
            margin-bottom: 2rem;
        }
        
        .primary-btn {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .primary-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .outline-btn {
            display: inline-block;
            background-color: transparent;
            color: var(--primary);
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .outline-btn:hover {
            background-color: var(--light);
            transform: translateY(-2px);
        }
        
        /* Goals Card */
        .goals-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .card-header h2 {
            font-size: 1.2rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .card-header h2 i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        /* Goal Item */
        .goal-item {
            padding: 1.5rem;
            border-radius: 10px;
            background-color: var(--light);
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .goal-item.completed {
            background-color: #e8f5e9;
            border-left: 4px solid var(--success);
        }
        
        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .goal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .goal-type {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            background-color: var(--primary);
            color: white;
        }
        
        .goal-description {
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .goal-progress {
            margin: 1rem 0;
        }
        
        .progress-bar {
            height: 10px;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: 5px;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }
        
        .goal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .goal-meta span {
            display: flex;
            align-items: center;
        }
        
        .goal-meta i {
            margin-right: 0.3rem;
        }
        
        .goal-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .goal-actions a {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .update-btn {
            background-color: var(--primary);
            color: white;
        }
        
        .update-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .delete-btn {
            background-color: var(--error);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #cfd8dc;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,184,212,0.2);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-submit {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0.8rem 1.5rem;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background-color: white;
            color: var(--primary);
            border: 1px solid var(--light-gray);
        }
        
        .pagination a:hover {
            background-color: var(--light);
        }
        
        .pagination span {
            background-color: var(--primary);
            color: white;
        }
        
        /* Messages */
        .error-message {
            background-color: #ffebee;
            color: var(--error);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--error);
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: var(--success);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .footer-logo i {
            margin-right: 10px;
        }
        
        .footer-links {
            display: flex;
        }
        
        .footer-links a {
            color: #b0bec5;
            text-decoration: none;
            margin-left: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .nav-links {
                margin-top: 1rem;
                flex-wrap: wrap;
            }
            
            .nav-links li {
                margin: 0.5rem 1rem 0.5rem 0;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-links {
                margin-top: 1rem;
                justify-content: center;
            }
            
            .footer-links a {
                margin: 0 0.75rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>FitGoals</span>
                </div>
                
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="goals.php">My Goals</a></li>
                    <li><a href="workouts.php">Workouts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php" class="btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <section class="goals-section">
        <div class="container">
            <?php if ($action == 'list'): ?>
                <div class="section-header">
                    <h1>My Fitness Goals</h1>
                    <p>Track and manage your fitness goals</p>
                </div>
                
                <div class="action-buttons">
                    <a href="goals.php?action=add" class="primary-btn"><i class="fas fa-plus"></i> Create New Goal</a>
                </div>
                
                <?php 
                if (!empty($error)) {
                    echo '<div class="error-message">' . $error . '</div>';
                }
                if (!empty($success)) {
                    echo '<div class="success-message">' . $success . '</div>';
                }
                ?>
                
                <div class="goals-card">
                    <div class="card-header">
                        <h2><i class="fas fa-bullseye"></i> Your Goals</h2>
                    </div>
                    
                    <?php if (isset($goals_result) && $goals_result->num_rows > 0): ?>
                        <?php while ($goal = $goals_result->fetch_assoc()): ?>
                            <?php 
                                // Calculate progress percentage
                                $progress = 0;
                                if (isset($goal['target_value']) && $goal['target_value'] > 0) {
                                    if ($goal['goal_type'] == 'weight_loss') {
                                        // For weight loss, progress is reversed (starting value to target)
                                        $total_change = $goal['current_value'] - $goal['target_value'];
                                        $initial_change = isset($goal['initial_value']) ? $goal['initial_value'] - $goal['target_value'] : $total_change;
                                        $progress = $initial_change > 0 ? min(100, max(0, round((1 - ($total_change / $initial_change)) * 100))) : 0;
                                    } else {
                                        // For other goals, progress is from 0 to target
                                        $progress = min(100, round(($goal['current_value'] / $goal['target_value']) * 100));
                                    }
                                }
                                
                                $is_completed = $goal['status'] == 'completed';
                            ?>
                            <div class="goal-item <?php echo $is_completed ? 'completed' : ''; ?>">
                                <div class="goal-header">
                                    <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                                    <div class="goal-type"><?php echo str_replace('_', ' ', ucfirst($goal['goal_type'])); ?></div>
                                </div>
                                
                                <?php if (!empty($goal['description'])): ?>
                                    <div class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></div>
                                <?php endif; ?>
                                
                                <div class="goal-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        <span>Current: <?php echo $goal['current_value'] . ' ' . $goal['unit']; ?></span>
                                        <span><?php echo $progress; ?>% Complete</span>
                                        <span>Target: <?php echo $goal['target_value'] . ' ' . $goal['unit']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="goal-meta">
                                    <span><i class="far fa-calendar-alt"></i> Started: <?php echo formatDate($goal['start_date']); ?></span>
                                    <span><i class="far fa-calendar-check"></i> Target: <?php echo formatDate($goal['target_date']); ?></span>
                                    <span><i class="fas fa-tag"></i> Status: <?php echo ucfirst($goal['status']); ?></span>
                                </div>
                                
                                <div class="goal-actions">
                                    <?php if ($goal['status'] == 'active'): ?>
                                        <a href="goals.php?action=update&id=<?php echo $goal['id']; ?>" class="update-btn">Update Progress</a>
                                    <?php endif; ?>
                                    
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this goal?');">
                                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                        <button type="submit" name="delete_goal" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="goals.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 2rem 0;">You don't have any goals yet. <a href="goals.php?action=add" style="color: var(--primary);">Create one now</a>!</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($action == 'add'): ?>
                <div class="section-header">
                    <h1>Create New Goal</h1>
                    <p>Set a new fitness goal to track your progress</p>
                </div>
                
                <?php 
                if (!empty($error)) {
                    echo '<div class="error-message">' . $error . '</div>';
                }
                if (!empty($success)) {
                    echo '<div class="success-message">' . $success . '</div>';
                }
                ?>
                
                <div class="goals-card">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label for="goal_type">Goal Type</label>
                            <select id="goal_type" name="goal_type" required>
                                <option value="">Select Goal Type</option>
                                <?php foreach ($goal_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Goal Title</label>
                            <input type="text" id="title" name="title" placeholder="e.g., Lose 10kg, Run 5km" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description (Optional)</label>
                            <textarea id="description" name="description" placeholder="Describe your goal and why it's important to you"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_value">Current Value</label>
                                <input type="number" id="current_value" name="current_value" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="target_value">Target Value</label>
                                <input type="number" id="target_value" name="target_value" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <select id="unit" name="unit" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach ($units as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="target_date">Target Date (Optional)</label>
                                <input type="date" id="target_date" name="target_date">
                            </div>
                        </div>
                        
                        <button type="submit" name="create_goal" class="form-submit">Create Goal</button>
                        <a href="goals.php" class="outline-btn" style="margin-left: 1rem;">Cancel</a>
                    </form>
                </div>
            <?php elseif ($action == 'update' && $goal): ?>
                <div class="section-header">
                    <h1>Update Goal Progress</h1>
                    <p>Track your progress for "<?php echo htmlspecialchars($goal['title']); ?>"</p>
                </div>
                
                <?php 
                if (!empty($error)) {
                    echo '<div class="error-message">' . $error . '</div>';
                }
                if (!empty($success)) {
                    echo '<div class="success-message">' . $success . '</div>';
                }
                ?>
                
                <div class="goals-card">
                    <div class="goal-item">
                        <div class="goal-header">
                            <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                            <div class="goal-type"><?php echo str_replace('_', ' ', ucfirst($goal['goal_type'])); ?></div>
                        </div>
                        
                        <?php if (!empty($goal['description'])): ?>
                            <div class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></div>
                        <?php endif; ?>
                        
                        <?php 
                            // Calculate progress percentage
                            $progress = 0;
                            if (isset($goal['target_value']) && $goal['target_value'] > 0) {
                                if ($goal['goal_type'] == 'weight_loss') {
                                    // For weight loss, progress is reversed (starting value to target)
                                    $total_change = $goal['current_value'] - $goal['target_value'];
                                    $initial_change = isset($goal['initial_value']) ? $goal['initial_value'] - $goal['target_value'] : $total_change;
                                    $progress = $initial_change > 0 ? min(100, max(0, round((1 - ($total_change / $initial_change)) * 100))) : 0;
                                } else {
                                    // For other goals, progress is from 0 to target
                                    $progress = min(100, round(($goal['current_value'] / $goal['target_value']) * 100));
                                }
                            }
                        ?>
                        
                        <div class="goal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <span>Current: <?php echo $goal['current_value'] . ' ' . $goal['unit']; ?></span>
                                <span><?php echo $progress; ?>% Complete</span>
                                <span>Target: <?php echo $goal['target_value'] . ' ' . $goal['unit']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                        
                        <div class="form-group">
                            <label for="new_value">New Value</label>
                            <input type="number" id="new_value" name="new_value" step="0.01" value="<?php echo $goal['current_value']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" placeholder="Add notes about your progress"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="recorded_date">Date</label>
                            <input type="date" id="recorded_date" name="recorded_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_goal" class="form-submit">Update Progress</button>
                        <a href="goals.php" class="outline-btn" style="margin-left: 1rem;">Cancel</a>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>FitGoals</span>
                </div>
                
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="#">About</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
