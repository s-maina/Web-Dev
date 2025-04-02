<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitgoals_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to display error messages
function showError($message) {
    echo '<div class="error-message">' . $message . '</div>';
}

// Function to display success messages
function showSuccess($message) {
    echo '<div class="success-message">' . $message . '</div>';
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to get user data
function getUserData($user_id) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    } else {
        // User not found, destroy session and redirect to login
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

// Function to get user stats
function getUserStats($user_id) {
    global $conn;
    $sql = "SELECT * FROM user_stats WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
    } else {
        // Create default stats if none exist
        $sql = "INSERT INTO user_stats (user_id) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
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
    
    $stmt->close();
    return $stats;
}

// Function to calculate BMI
function calculateBMI($weight, $height) {
    if (!$weight || !$height || $height <= 0) {
        return null;
    }
    
    // Convert height from cm to m
    $height_m = $height / 100;
    
    // Calculate BMI: weight (kg) / (height (m) * height (m))
    $bmi = $weight / ($height_m * $height_m);
    
    return round($bmi, 1);
}

// Function to get BMI category
function getBMICategory($bmi) {
    if ($bmi === null) {
        return 'Unknown';
    } elseif ($bmi < 18.5) {
        return 'Underweight';
    } elseif ($bmi >= 18.5 && $bmi < 25) {
        return 'Normal weight';
    } elseif ($bmi >= 25 && $bmi < 30) {
        return 'Overweight';
    } else {
        return 'Obese';
    }
}

// Function to format date
function formatDate($date) {
    if (empty($date)) {
        return 'Not set';
    }
    return date('F j, Y', strtotime($date));
}
?>

