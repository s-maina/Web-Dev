<?php
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Verify user exists in the database
$check_user_sql = "SELECT id, username, full_name, profile_pic FROM users WHERE id = ?";
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

$user = $check_user_result->fetch_assoc();
$check_user_stmt->close();

// Get user stats
$stats_sql = "SELECT * FROM user_stats WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

if ($stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
} else {
    // Create default stats if none exist
    $create_stats_sql = "INSERT INTO user_stats (user_id) VALUES (?)";
    $create_stats_stmt = $conn->prepare($create_stats_sql);
    $create_stats_stmt->bind_param("i", $user_id);
    $create_stats_stmt->execute();
    $create_stats_stmt->close();
    
    $stats = [
        'id' => $conn->insert_id,
        'user_id' => $user_id,
        'height' => null,
        'weight' => null,
        'age' => null,
        'activity_level' => 'moderate',
        'fitness_level' => 'beginner'
    ];
}
$stats_stmt->close();

// Get user's active goals
$goals_sql = "SELECT * FROM fitness_goals WHERE user_id = ? AND status = 'active' ORDER BY target_date ASC LIMIT 3";
$goals_stmt = $conn->prepare($goals_sql);
$goals_stmt->bind_param("i", $user_id);
$goals_stmt->execute();
$goals_result = $goals_stmt->get_result();

// Get recent workouts
$workouts_sql = "SELECT * FROM workout_logs WHERE user_id = ? ORDER BY workout_date DESC LIMIT 5";
$workouts_stmt = $conn->prepare($workouts_sql);
$workouts_stmt->bind_param("i", $user_id);
$workouts_stmt->execute();
$workouts_result = $workouts_stmt->get_result();

// Calculate workout stats
$workout_stats_sql = "SELECT 
                        COUNT(*) as total_workouts,
                        SUM(duration) as total_duration,
                        SUM(calories_burned) as total_calories,
                        AVG(duration) as avg_duration
                      FROM workout_logs 
                      WHERE user_id = ?";
$workout_stats_stmt = $conn->prepare($workout_stats_sql);
$workout_stats_stmt->bind_param("i", $user_id);
$workout_stats_stmt->execute();
$workout_stats = $workout_stats_stmt->get_result()->fetch_assoc();

// Calculate BMI if height and weight are available
$bmi = null;
$bmi_category = 'Unknown';
if (isset($stats['height']) && isset($stats['weight']) && $stats['height'] > 0 && $stats['weight'] > 0) {
    $bmi = calculateBMI($stats['weight'], $stats['height']);
    $bmi_category = getBMICategory($bmi);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FitGoals</title>
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
        
        /* Dashboard Styles */
        .dashboard {
            padding: 2rem 0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .welcome-message h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .welcome-message p {
            color: var(--gray);
        }
        
        .action-buttons a {
            display: inline-block;
            margin-left: 1rem;
            text-decoration: none;
        }
        
        .primary-btn {
            background-color: var(--primary);
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .primary-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .outline-btn {
            background-color: transparent;
            color: var(--primary);
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            border: 1px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .outline-btn:hover {
            background-color: var(--light);
            transform: translateY(-2px);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
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
        
        .card-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .card-header a:hover {
            color: var(--primary-dark);
        }
        
        /* Profile Card */
        .profile-card {
            text-align: center;
        }
        
        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .profile-username {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-card {
            background-color: var(--light);
            padding: 1rem;
            border-radius: 5px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* Goals Card */
        .goal-item {
            padding: 1rem;
            border-radius: 5px;
            background-color: var(--light);
            margin-bottom: 1rem;
        }
        
        .goal-item:last-child {
            margin-bottom: 0;
        }
        
        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .goal-title {
            font-weight: 600;
            color: var(--dark);
        }
        
        .goal-type {
            font-size: 0.8rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            background-color: var(--primary);
            color: white;
        }
        
        .goal-progress {
            margin: 0.8rem 0;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: 4px;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.3rem;
        }
        
        .goal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* Workout Card */
        .workout-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 5px;
            background-color: var(--light);
            margin-bottom: 1rem;
        }
        
        .workout-item:last-child {
            margin-bottom: 0;
        }
        
        .workout-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .workout-details {
            flex: 1;
        }
        
        .workout-type {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }
        
        .workout-meta {
            display: flex;
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .workout-meta span {
            margin-right: 1rem;
            display: flex;
            align-items: center;
        }
        
        .workout-meta i {
            margin-right: 0.3rem;
        }
        
        .workout-date {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-box {
            background-color: var(--light);
            padding: 1.5rem;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-box i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stat-box .value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .stat-box .label {
            font-size: 0.9rem;
            color: var(--gray);
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
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
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
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-buttons {
                margin-top: 1rem;
            }
            
            .action-buttons a {
                margin: 0 1rem 0 0;
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
    
    <section class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <div class="welcome-message">
                    <h1>Welcome back, <?php echo isset($user['full_name']) && $user['full_name'] ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']); ?>!</h1>
                    <p>Here's an overview of your fitness journey</p>
                </div>
                
                <div class="action-buttons">
                    <a href="goals.php?action=add" class="primary-btn"><i class="fas fa-plus"></i> Add Goal</a>
                    <a href="workouts.php?action=log" class="outline-btn"><i class="fas fa-dumbbell"></i> Log Workout</a>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-sidebar">
                    <div class="dashboard-card profile-card">
                        <img src="<?php echo !empty($user['profile_pic']) ? 'uploads/' . htmlspecialchars($user['profile_pic']) : 'https://via.placeholder.com/100' ?>" alt="Profile Picture" class="profile-pic">
                        <h3 class="profile-name"><?php echo isset($user['full_name']) && $user['full_name'] ? htmlspecialchars($user['full_name']) : htmlspecialchars($user['username']); ?></h3>
                        <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>
                        
                        <div class="profile-stats">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo isset($workout_stats['total_workouts']) ? intval($workout_stats['total_workouts']) : 0; ?></div>
                                <div class="stat-label">Workouts</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $goals_result->num_rows; ?></div>
                                <div class="stat-label">Active Goals</div>
                            </div>
                        </div>
                        
                        <?php if ($bmi): ?>
                        <div class="stat-card" style="margin-top: 1rem;">
                            <div class="stat-value"><?php echo $bmi; ?></div>
                            <div class="stat-label">BMI - <?php echo $bmi_category; ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <a href="profile.php" style="display: inline-block; margin-top: 1.5rem; color: var(--primary); text-decoration: none;">View Profile</a>
                    </div>
                </div>
                
                <div class="dashboard-main">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-bullseye"></i> Active Goals</h2>
                            <a href="goals.php">View All</a>
                        </div>
                        
                        <?php if ($goals_result->num_rows > 0): ?>
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
                                ?>
                                <div class="goal-item">
                                    <div class="goal-header">
                                        <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                                        <div class="goal-type"><?php echo str_replace('_', ' ', ucfirst($goal['goal_type'])); ?></div>
                                    </div>
                                    
                                    <div class="goal-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <div class="progress-text">
                                            <span><?php echo $goal['current_value'] . ' ' . $goal['unit']; ?></span>
                                            <span><?php echo $progress; ?>%</span>
                                            <span><?php echo $goal['target_value'] . ' ' . $goal['unit']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="goal-footer">
                                        <span><i class="far fa-calendar-alt"></i> Target: <?php echo formatDate($goal['target_date']); ?></span>
                                        <a href="goals.php?action=update&id=<?php echo $goal['id']; ?>" style="color: var(--primary);">Update Progress</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--gray); padding: 2rem 0;">You don't have any active goals. <a href="goals.php?action=add" style="color: var(--primary);">Create one now</a>!</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-dumbbell"></i> Recent Workouts</h2>
                            <a href="workouts.php">View All</a>
                        </div>
                        
                        <?php if ($workouts_result->num_rows > 0): ?>
                            <?php while ($workout = $workouts_result->fetch_assoc()): ?>
                                <div class="workout-item">
                                    <div class="workout-icon">
                                        <i class="fas fa-running"></i>
                                    </div>
                                    <div class="workout-details">
                                        <div class="workout-type"><?php echo htmlspecialchars($workout['workout_type']); ?></div>
                                        <div class="workout-meta">
                                            <span><i class="far fa-clock"></i> <?php echo $workout['duration']; ?> min</span>
                                            <?php if ($workout['calories_burned']): ?>
                                                <span><i class="fas fa-fire"></i> <?php echo $workout['calories_burned']; ?> cal</span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-signal"></i> <?php echo ucfirst($workout['intensity']); ?></span>
                                        </div>
                                    </div>
                                    <div class="workout-date">
                                        <?php echo formatDate($workout['workout_date']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--gray); padding: 2rem 0;">You haven't logged any workouts yet. <a href="workouts.php?action=log" style="color: var(--primary);">Log one now</a>!</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-line"></i> Workout Statistics</h2>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-box">
                                <i class="fas fa-calendar-check"></i>
                                <div class="value"><?php echo $workout_stats['total_workouts'] ?? 0; ?></div>
                                <div class="label">Total Workouts</div>
                            </div>
                            
                            <div class="stat-box">
                                <i class="far fa-clock"></i>
                                <div class="value"><?php echo $workout_stats['total_duration'] ?? 0; ?></div>
                                <div class="label">Total Minutes</div>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-fire"></i>
                                <div class="value"><?php echo $workout_stats['total_calories'] ?? 0; ?></div>
                                <div class="label">Calories Burned</div>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-stopwatch"></i>
                                <div class="value"><?php echo round($workout_stats['avg_duration'] ?? 0); ?></div>
                                <div class="label">Avg. Duration (min)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
