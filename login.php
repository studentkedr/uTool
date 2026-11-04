<?php
session_start();
include 'db.php';

$message = "";

if (isset($_POST['login_btn'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        if ($user['status'] == 'deactivated') {
            $message = "Account deactivated. Please contact support.";
        } else {
            if (password_verify($password, $user['password'])) {
                // Success: Reset attempts
                mysqli_query($conn, "UPDATE users SET attempts = 0 WHERE id = '{$user['id']}'");

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name']; // Optional: useful for "Welcome, Name"

                // Redirect all users to the dashboard
                header("Location: user.php?section=dashboard");
                exit();

            } else {
                // Fail: Increment attempts
                $new_attempts = $user['attempts'] + 1;

                if ($new_attempts >= 3) {
                    mysqli_query($conn, "UPDATE users SET attempts = $new_attempts, status = 'deactivated' WHERE id = '{$user['id']}'");
                    $message = "3 failed attempts. Account deactivated.";
                } else {
                    mysqli_query($conn, "UPDATE users SET attempts = $new_attempts WHERE id = '{$user['id']}'");
                    $message = "Wrong password. " . (3 - $new_attempts) . " attempts remaining.";
                }
            }
        }
    } else {
        $message = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uTool - Login</title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #fbc02d;
            --primary-hover: #f9a825;
            --dark: #2d3436;
            --bg: #f8f9fa;
        }

        body {
            background-color: var(--bg);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }

        /* --- Animations/GIFs Section --- */
        /* .gif-container {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .gif-items img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s ease;
        }

        .gif-items img:hover {
            transform: scale(1.2) rotate(5deg);
        } */

        /* --- Login Form --- */
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-card h2 {
            margin-top: 5px;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 700;
        }

        h2 span {
            color: var(--primary);
        }

        p {
            color: #636e72;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        /* .error-box {
            background: #fff5f5;
            color: #e53e3e;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fed7d7;
        } */

        /* --- Modern Role Selector --- */
        .role-selector {
            display: flex;
            background: #eee;
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .role-selector input[type="radio"] {
            display: none;
        }

        .role-selector label {
            flex: 1;
            padding: 10px;
            cursor: pointer;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #636e72;
            transition: 0.3s;
        }

        .role-selector input[type="radio"]:checked+label {
            background: white;
            color: var(--dark);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        input {
            width: 90%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: 0.3s;
            outline: none;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(251, 192, 45, 0.2);
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: var(--dark);
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .footer-links {
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .footer-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }
    </style>
</head>

<body>

    <!-- <div class="gif-container">
        <div class="gif-items"><img src="first.gif"></div>
        <div class="gif-items"><img src="middle.gif"></div>
        <div class="gif-items"><img src="last.gif"></div>
    </div> -->

    <div class="login-card">
        <form method="POST">
            <h2>u<span>Tool</span> Login</h2>
            <p>Welcome back! Please enter your details.</p>

            <!-- <div class="role-selector">
                <input type="radio" name="role" value="user" id="user" checked>
                <label for="user">User</label>

                <input type="radio" name="role" value="admin" id="admin">
                <label for="admin">Admin</label>
            </div> -->

            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="login_btn">Log In</button>
            <?php
            if (isset($_GET['registration']) && $_GET['registration'] == 'success') {
                echo "<p style='color: green; font-weight: 600;'>Registration successful! Please log in.</p>";
            }

            if (!empty($message)) {
                echo "<p style='color: red; font-size: 0.8rem;'>$message</p>";
            }
            ?>
        </form>

        <div class="footer-links">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>

</body>

</html>