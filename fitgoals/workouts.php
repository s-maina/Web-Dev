<?php
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle workout logging
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_workout'])) {
  $workout_type = $_POST['workout_type'];
  $duration = $_POST['duration'];
  $calories_burned = !empty($_POST['calories_burned']) ? $_POST['calories_burned'] : 0;
  $intensity = $_POST['intensity'];
  $notes = $_POST['notes'];
  $workout_date = $_POST['workout_date'];
  
  // Validate input
  if (empty($workout_type) || empty($duration) || empty($workout_date)) {
      $error = "Workout type, duration, and date are required";
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
      
      // Insert workout
      $insert_sql = "INSERT INTO workout_logs (user_id, workout_type, duration, calories_burned, intensity, notes, workout_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
      $insert_stmt = $conn->prepare($insert_sql);
      $insert_stmt->bind_param("isiisss", $user_id, $workout_type, $duration, $calories_burned, $intensity, $notes, $workout_date);
      
      if ($insert_stmt->execute()) {
          $success = "Workout logged successfully!";
      } else {
          $error = "Error logging workout: " . $insert_stmt->error;
      }
      
      $insert_stmt->close();
  }
}

// Get user's workouts with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$workouts_sql = "SELECT * FROM workout_logs WHERE user_id = ? ORDER BY workout_date DESC LIMIT ? OFFSET ?";
$workouts_stmt = $conn->prepare($workouts_sql);

if ($workouts_stmt === false) {
    $error = "Error preparing workouts statement: " . $conn->error;
} else {
    $workouts_stmt->bind_param("iii", $user_id, $limit, $offset);
    $workouts_stmt->execute();
    $workouts_result = $workouts_stmt->get_result();
    $workouts_stmt->close();
}

// Get total workouts count for pagination
$count_sql = "SELECT COUNT(*) as total FROM workout_logs WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);

if ($count_stmt === false) {
    $error = "Error preparing count statement: " . $conn->error;
} else {
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $total_workouts = $count_result['total'];
    $total_pages = ceil($total_workouts / $limit);
    $count_stmt->close();
}

// Determine the current action
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Define workout types and intensity levels
$workout_types = [
    'running' => 'Running',
    'walking' => 'Walking',
    'cycling' => 'Cycling',
    'swimming' => 'Swimming',
    'weight_training' => 'Weight Training',
    'yoga' => 'Yoga',
    'hiit' => 'HIIT',
    'pilates' => 'Pilates',
    'cardio' => 'Cardio',
    'other' => 'Other'
];

$intensity_levels = [
    'low' => 'Low',
    'moderate' => 'Moderate',
    'high' => 'High',
    'very_high' => 'Very High'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workouts - FitGoals</title>
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
        
        /* Workouts Styles */
        .workouts-section {
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
        
        /* Workouts Card */
        .workouts-card {
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
        
        /* Workout Item */
        .workout-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-radius: 10px;
            background-color: var(--light);
            margin-bottom: 1.5rem;
        }
        
        .workout-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }
        
        .workout-icon i {
            font-size: 1.5rem;
        }
        
        .workout-details {
            flex: 1;
        }
        
        .workout-type {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .workout-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .workout-meta span {
            display: flex;
            align-items: center;
        }
        
        .workout-meta i {
            margin-right: 0.3rem;
        }
        
        .workout-notes {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .workout-date {
            font-size: 0.9rem;
            color: var(--gray);
            text-align: right;
            flex-shrink: 0;
            margin-left: 1.5rem;
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
            
            .workout-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .workout-icon {
                margin-bottom: 1rem;
            }
            
            .workout-date {
                margin-left: 0;
                margin-top: 1rem;
                text-align: left;
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
    
    <section class="workouts-section">
        <div class="container">
            <?php if ($action == 'list'): ?>
                <div class="section-header">
                    <h1>My Workouts</h1>
                    <p>Track and manage your workout history</p>
                </div>
                
                <div class="action-buttons">
                    <a href="workouts.php?action=log" class="primary-btn"><i class="fas fa-plus"></i> Log New Workout</a>
                </div>
                
                <?php 
                if (!empty($error)) {
                    echo '<div class="error-message">' . $error . '</div>';
                }
                if (!empty($success)) {
                    echo '<div class="success-message">' . $success . '</div>';
                }
                ?>
                
                <div class="workouts-card">
                    <div class="card-header">
                        <h2><i class="fas fa-dumbbell"></i> Your Workouts</h2>
                    </div>
                    
                    <?php if (isset($workouts_result) && $workouts_result->num_rows > 0): ?>
                        <?php while ($workout = $workouts_result->fetch_assoc()): ?>
                            <div class="workout-item">
                                <div class="workout-icon">
                                    <i class="fas fa-running"></i>
                                </div>
                                <div class="workout-details">
                                    <div class="workout-type"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($workout['workout_type']))); ?></div>
                                    <div class="workout-meta">
                                        <span><i class="far fa-clock"></i> <?php echo $workout['duration']; ?> minutes</span>
                                        <?php if ($workout['calories_burned']): ?>
                                            <span><i class="fas fa-fire"></i> <?php echo $workout['calories_burned']; ?> calories</span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-signal"></i> <?php echo ucfirst($workout['intensity']); ?> intensity</span>
                                    </div>
                                    <?php if (!empty($workout['notes'])): ?>
                                        <div class="workout-notes"><?php echo htmlspecialchars($workout['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="workout-date">
                                    <?php echo date('F j, Y', strtotime($workout['workout_date'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="workouts.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 2rem 0;">You haven't logged any workouts yet. <a href="workouts.php?action=log" style="color: var(--primary);">Log one now</a>!</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($action == 'log'): ?>
                <div class="section-header">
                    <h1>Log New Workout</h1>
                    <p>Record your workout details</p>
                </div>
                
                <?php 
                if (!empty($error)) {
                    echo '<div class="error-message">' . $error . '</div>';
                }
                if (!empty($success)) {
                    echo '<div class="success-message">' . $success . '</div>';
                }
                ?>
                
                <div class="workouts-card">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label for="workout_type">Workout Type</label>
                            <select id="workout_type" name="workout_type" required>
                                <option value="">Select Workout Type</option>
                                <?php foreach ($workout_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration">Duration (minutes)</label>
                                <input type="number" id="duration" name="duration" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="calories_burned">Calories Burned (optional)</label>
                                <input type="number" id="calories_burned" name="calories_burned" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="intensity">Intensity</label>
                            <select id="intensity" name="intensity" required>
                                <option value="">Select Intensity</option>
                                <?php foreach ($intensity_levels as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="workout_date">Date</label>
                            <input type="date" id="workout_date" name="workout_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes (optional)</label>
                            <textarea id="notes" name="notes" placeholder="Add any notes about your workout"></textarea>
                        </div>
                        
                        <button type="submit" name="log_workout" class="form-submit">Log Workout</button>
                        <a href="workouts.php" class="outline-btn" style="margin-left: 1rem;">Cancel</a>
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

