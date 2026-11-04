<?php
require 'db.php';

if (isset($_POST['register_btn'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // FORCED ROLE: All new signups are 'user' only
    $role = 'user';

    $checkEmail = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

    if (mysqli_num_rows($checkEmail) > 0) {
        $error = "Email is already registered!";
    } else {
        $sql = "INSERT INTO users (name, email, password, role, attempts, status) 
                VALUES ('$name', '$email', '$pass', '$role', 0, 'active')";

        if (mysqli_query($conn, $sql)) {
            header("Location: login.php?registration=success");
            exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uTool - Register</title>
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

        .error-box {
            background: #fff5f5;
            color: #e53e3e;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fed7d7;
        }

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

    <div class="login-card">
        <form method="POST">
            <h2>Join u<span>Tool</span></h2>
            <p>Create your account to start renting.</p>

            <input type="text" name="name" placeholder="Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="register_btn">Create Account</button>

            <?php if (isset($error)): ?>
                <div style="color: #e53e3e; background: #fff5f5; padding: 10px; border-radius: 8px; margin-top: 15px; border: 1px solid #fed7d7; font-size: 0.85rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
        </form>
        <div class="footer-links">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>

</body>

</html>