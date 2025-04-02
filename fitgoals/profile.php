<?php
require_once 'config.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);
$stats = getUserStats($user_id);

$success = "";
$error = "";

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
  $full_name = $_POST['full_name'];
  $email = $_POST['email'];
  
  // Update user data
  $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("ssi", $full_name, $email, $user_id);
  
  if ($update_stmt->execute()) {
      $success = "Profile updated successfully!";
      
      // Refresh user data
      $user = getUserData($user_id);
  } else {
      $error = "Error updating profile: " . $update_stmt->error;
  }
  
  $update_stmt->close();
}

// Handle stats update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_stats'])) {
  $height = !empty($_POST['height']) ? $_POST['height'] : null;
  $weight = !empty($_POST['weight']) ? $_POST['weight'] : null;
  $age = !empty($_POST['age']) ? $_POST['age'] : null;
  $activity_level = $_POST['activity_level'];
  $fitness_level = $_POST['fitness_level'];
  
  // Update user stats
  $stats_sql = "UPDATE user_stats SET height = ?, weight = ?, age = ?, activity_level = ?, fitness_level = ? WHERE user_id = ?";
  $stats_stmt = $conn->prepare($stats_sql);
  
  if ($stats_stmt === false) {
      $error = "Error preparing statement: " . $conn->error;
  } else {
      // Fix the bind_param types - use doubles for height and weight, integer for age
      $stats_stmt->bind_param("ddissi", $height, $weight, $age, $activity_level, $fitness_level, $user_id);
      
      if ($stats_stmt->execute()) {
          $success = "Stats updated successfully!";
          
          // Refresh stats data
          $stats = getUserStats($user_id);
      } else {
          $error = "Error updating stats: " . $stats_stmt->error;
      }
      
      $stats_stmt->close();
  }
}

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['upload_picture']) || isset($_FILES['profile_pic']))) {
  if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $upload_dir = 'uploads/';
      
      // Create directory if it doesn't exist
      if (!file_exists($upload_dir)) {
          mkdir($upload_dir, 0777, true);
      }
      
      $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
      $new_filename = $user_id . '_' . time() . '.' . $file_extension;
      $upload_path = $upload_dir . $new_filename;
      
      // Check file type
      $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
      if (!in_array(strtolower($file_extension), $allowed_types)) {
          $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
      } else {
          // Move uploaded file
          if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
              // Update database
              $pic_sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
              $pic_stmt = $conn->prepare($pic_sql);
              
              if ($pic_stmt === false) {
                  $error = "Error preparing statement: " . $conn->error;
              } else {
                  $pic_stmt->bind_param("si", $new_filename, $user_id);
                  
                  if ($pic_stmt->execute()) {
                      $success = "Profile picture updated successfully!";
                      
                      // Refresh user data
                      $user = getUserData($user_id);
                  } else {
                      $error = "Error updating profile picture in database.";
                  }
                  
                  $pic_stmt->close();
              }
          } else {
              $error = "Error uploading file.";
          }
      }
  } else if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
      $error = "Error uploading file: " . $_FILES['profile_pic']['error'];
  }
}

// Calculate BMI if height and weight are available
$bmi = null;
$bmi_category = 'Unknown';
if ($stats['height'] && $stats['weight']) {
  $bmi = calculateBMI($stats['weight'], $stats['height']);
  $bmi_category = getBMICategory($bmi);
}

// Check if bookings table exists before querying
$bookings_result = null;
$table_check_sql = "SHOW TABLES LIKE 'bookings'";
$table_check_result = $conn->query($table_check_sql);
if ($table_check_result->num_rows > 0) {
    // Bookings table exists, proceed with query
    $bookings_sql = "SELECT b.*, c.class_name, c.instructor, c.schedule 
                FROM bookings b 
                JOIN classes c ON b.class_id = c.id 
                WHERE b.user_id = ? 
                ORDER BY b.booking_date DESC";
    $bookings_stmt = $conn->prepare($bookings_sql);

    if ($bookings_stmt === false) {
        $error = "Error preparing bookings statement: " . $conn->error;
    } else {
        $bookings_stmt->bind_param("i", $user_id);
        $bookings_stmt->execute();
        $bookings_result = $bookings_stmt->get_result();
        $bookings_stmt->close();
    }
}

// Display success message from session if it exists
if (isset($_SESSION['success'])) {
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
}

// Display error message from session if it exists
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - FitGoals</title>
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
      
      /* Profile Styles */
      .profile-section {
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
      
      .profile-grid {
          display: grid;
          grid-template-columns: 1fr 2fr;
          gap: 2rem;
      }
      
      .profile-card {
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
      
      /* Profile Picture Card */
      .profile-picture-card {
          text-align: center;
      }
      
      .profile-pic-container {
          position: relative;
          width: 150px;
          height: 150px;
          margin: 0 auto 1.5rem;
          border-radius: 50%;
          overflow: hidden;
      }
      
      .profile-pic {
          width: 100%;
          height: 100%;
          object-fit: cover;
          border: 3px solid var(--primary);
      }
      
      .profile-pic-overlay {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(0, 0, 0, 0.5);
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          opacity: 0;
          transition: opacity 0.3s ease;
          cursor: pointer;
      }
      
      .profile-pic-overlay i {
          color: white;
          font-size: 1.5rem;
      }
      
      .profile-pic-container:hover .profile-pic-overlay {
          opacity: 1;
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
      
      .stat-box {
          background-color: var(--light);
          padding: 1rem;
          border-radius: 5px;
          text-align: center;
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
      
      .form-group input, .form-group select {
          width: 100%;
          padding: 0.8rem 1rem;
          border: 1px solid #cfd8dc;
          border-radius: 5px;
          font-family: 'Poppins', sans-serif;
          font-size: 1rem;
          transition: all 0.3s ease;
      }
      
      .form-group input:focus, .form-group select:focus {
          border-color: var(--primary);
          outline: none;
          box-shadow: 0 0 0 2px rgba(0,184,212,0.2);
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
      
      /* Tabs */
      .tabs {
          display: flex;
          margin-bottom: 1.5rem;
          border-bottom: 1px solid var(--light-gray);
      }
      
      .tab {
          padding: 0.8rem 1.5rem;
          cursor: pointer;
          font-weight: 500;
          color: var(--gray);
          border-bottom: 2px solid transparent;
          transition: all 0.3s ease;
      }
      
      .tab.active {
          color: var(--primary);
          border-bottom-color: var(--primary);
      }
      
      .tab-content {
          display: none;
      }
      
      .tab-content.active {
          display: block;
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
      @media (max-width: 992px) {
          .profile-grid {
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
  
  <section class="profile-section">
      <div class="container">
          <div class="section-header">
              <h1>My Profile</h1>
              <p>Manage your personal information and fitness stats</p>
          </div>
          
          <?php 
          if (!empty($error)) {
              echo '<div class="error-message">' . $error . '</div>';
          }
          if (!empty($success)) {
              echo '<div class="success-message">' . $success . '</div>';
          }
          ?>
          
          <div class="profile-grid">
              <div class="profile-sidebar">
                  <div class="profile-card profile-picture-card">
                      
<div class="profile-pic-container">
    <img src="<?php echo !empty($user['profile_pic']) ? 'uploads/' . htmlspecialchars($user['profile_pic']) : 'https://via.placeholder.com/150' ?>" alt="Profile Picture" class="profile-pic">
    <div class="profile-pic-overlay" id="change-pic-btn">
        <i class="fas fa-camera"></i>
    </div>
</div>
                      
                      <h3 class="profile-name"><?php echo $user['full_name'] ? $user['full_name'] : $user['username']; ?></h3>
                      <p class="profile-username">@<?php echo $user['username']; ?></p>
                      <p>Member since: <?php echo date('F j, Y', strtotime($user['join_date'])); ?></p>
                      
                      <?php if ($bmi): ?>
                      <div class="profile-stats">
                          <div class="stat-box">
                              <div class="stat-value"><?php echo $bmi; ?></div>
                              <div class="stat-label">BMI</div>
                          </div>
                          <div class="stat-box">
                              <div class="stat-value"><?php echo $bmi_category; ?></div>
                              <div class="stat-label">Category</div>
                          </div>
                      </div>
                      <?php endif; ?>
                      
                      <!-- Hidden form for profile picture upload -->
                      <form id="profile-pic-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" style="display: none;">
                          <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                          <input type="hidden" name="upload_picture" value="1">
                      </form>
                  </div>
              </div>
              
              <div class="profile-main">
                  <div class="profile-card">
                      <div class="tabs">
                          <div class="tab active" data-tab="personal-info">Personal Information</div>
                          <div class="tab" data-tab="fitness-stats">Fitness Stats</div>
                      </div>
                      
                      <div id="personal-info" class="tab-content active">
                          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                              <div class="form-group">
                                  <label for="username">Username</label>
                                  <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" disabled>
                              </div>
                              
                              <div class="form-group">
                                  <label for="full_name">Full Name</label>
                                  <input type="text" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>">
                              </div>
                              
                              <div class="form-group">
                                  <label for="email">Email</label>
                                  <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>">
                              </div>
                              
                              <button type="submit" name="update_profile" class="form-submit">Update Profile</button>
                          </form>
                      </div>
                      
                      <div id="fitness-stats" class="tab-content">
                          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                              <div class="form-row">
                                  <div class="form-group">
                                      <label for="height">Height (cm)</label>
                                      <input type="number" id="height" name="height" value="<?php echo $stats['height']; ?>" step="0.1" min="0">
                                  </div>
                                  
                                  <div class="form-group">
                                      <label for="weight">Weight (kg)</label>
                                      <input type="number" id="weight" name="weight" value="<?php echo $stats['weight']; ?>" step="0.1" min="0">
                                  </div>
                              </div>
                              
                              <div class="form-group">
                                  <label for="age">Age</label>
                                  <input type="number" id="age" name="age" value="<?php echo $stats['age']; ?>" min="0">
                              </div>
                              
                              <div class="form-row">
                                  <div class="form-group">
                                      <label for="activity_level">Activity Level</label>
                                      <select id="activity_level" name="activity_level">
                                          <option value="sedentary" <?php echo $stats['activity_level'] == 'sedentary' ? 'selected' : ''; ?>>Sedentary</option>
                                          <option value="light" <?php echo $stats['activity_level'] == 'light' ? 'selected' : ''; ?>>Lightly Active</option>
                                          <option value="moderate" <?php echo $stats['activity_level'] == 'moderate' ? 'selected' : ''; ?>>Moderately Active</option>
                                          <option value="active" <?php echo $stats['activity_level'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                          <option value="very_active" <?php echo $stats['activity_level'] == 'very_active' ? 'selected' : ''; ?>>Very Active</option>
                                      </select>
                                  </div>
                                  
                                  <div class="form-group">
                                      <label for="fitness_level">Fitness Level</label>
                                      <select id="fitness_level" name="fitness_level">
                                          <option value="beginner" <?php echo $stats['fitness_level'] == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                          <option value="intermediate" <?php echo $stats['fitness_level'] == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                          <option value="advanced" <?php echo $stats['fitness_level'] == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                      </select>
                                  </div>
                              </div>
                              
                              <button type="submit" name="update_stats" class="form-submit">Update Stats</button>
                          </form>
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
  
  <script>
      // Profile picture upload
const changePicBtn = document.getElementById('change-pic-btn');
const profilePicInput = document.getElementById('profile_pic');
const profilePicForm = document.getElementById('profile-pic-form');
const profilePic = document.querySelector('.profile-pic');

changePicBtn.addEventListener('click', () => {
    profilePicInput.click();
});

profilePicInput.addEventListener('change', (e) => {
    if (profilePicInput.files.length > 0) {
        // Show preview of the selected image
        const file = profilePicInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(event) {
            profilePic.src = event.target.result;
            
            // Make sure the overlay is still visible after image change
            const overlay = document.querySelector('.profile-pic-overlay');
            overlay.style.opacity = '0';
            
            // Show overlay on hover
            profilePic.parentElement.addEventListener('mouseenter', () => {
                overlay.style.opacity = '1';
            });
            
            profilePic.parentElement.addEventListener('mouseleave', () => {
                overlay.style.opacity = '0';
            });
        };
        
        reader.readAsDataURL(file);
        
        // Submit the form after a short delay to allow the user to see the preview
        setTimeout(() => {
            profilePicForm.submit();
        }, 1000); // Increased delay to 1 second for better user experience
    }
});
  </script>
</body>
</html>

