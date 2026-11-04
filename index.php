<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uTool - P2P Equipment Rental</title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #fbc02d;
            --primary-hover: #f9a825;
            --dark: #2d3436;
            --light-grey: #f8f9fa;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light-grey);
            color: var(--dark);
            line-height: 1.6;
            /* Removed overflow:hidden to allow viewing all tools */
        }

        /* --- Display Section adjusted to top --- */
        .display {
            padding: 40px 20px 20px;
            /* Reduced top padding significantly */
            text-align: center;
            background: white;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        }

        .display h1 {
            font-size: 3.5rem;
            /* Slightly smaller to save space */
            font-weight: 800;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .display h1 span {
            color: var(--primary);
        }

        .display h3 {
            font-weight: 400;
            color: #636e72;
            margin-bottom: 5px;
        }

        .h3 p {
            margin-bottom: 5px;
        }

        .group {
            display: flex;
            flex-direction: column;
            /* This puts login below register */
            align-items: center;
            gap: 12px;
            /* Space between button and login */
            margin-top: 20px;
        }

        .register {
            font-weight: 600;
            text-decoration: none;
            color: var(--dark);
            background-color: var(--primary);
            padding: 12px 35px;
            border-radius: 50px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(251, 192, 45, 0.3);
            transition: 0.3s;
        }

        .register:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .login {
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .login:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* --- Tools Grid adjusted position --- */
        .tools-container {
            max-width: 1100px;
            margin: 20px auto;
            /* Changed from negative to positive margin */
            padding: 0 20px;
            /* display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px; */
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            /* This centers the items in the last row */
            gap: 15px;
            flex-grow: 1;
            align-content: center;
        }

        .tool-card {
            background: white;
            padding: 20px 15px;
            width: 180px;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.02);
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .tool-card img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .tool-card .caption {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }

        footer {
            /* padding: 20px; */
            margin-top: 50px;
            color: #b2bec3;
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .display h1 {
                font-size: 2.5rem;
            }

            .tools-container {
                grid-template-columns: repeat(2, 1fr);
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

    <section id="home">
        <div class="display">
            <h1>u<span>Tool</span></h1>
            <h3>P2P Equipment Rental</h3>
            <p>Your buddy for borrowing and renting tools safely!</p>

            <div class="group">
                <a href="register.php" class="register">Get Started</a>
                <a href="login.php" class="login">Already have an account? Log in</a>
            </div>
        </div>

        <div class="tools-container">
            <div class="tool-card">
                <img src="hand.png" alt="Hand Tools">
                <div class="caption">Hand Tools</div>
            </div>

            <div class="tool-card">
                <img src="power.png" alt="Power Tools">
                <div class="caption">Power Tools</div>
            </div>

            <div class="tool-card">
                <img src="construction.png" alt="Construction">
                <div class="caption">Construction</div>
            </div>

            <div class="tool-card">
                <img src="garden.png" alt="Gardening">
                <div class="caption">Gardening</div>
            </div>

            <div class="tool-card">
                <img src="electrical.png" alt="Electrical">
                <div class="caption">Electrical</div>
            </div>

            <div class="tool-card adjst">
                <img src="automotive.png" alt="Automotive">
                <div class="caption">Automotive</div>
            </div>

            <div class="tool-card adjst">
                <img src="workshop.png" alt="Workshop">
                <div class="caption">Workshop</div>
            </div>

            <div class="tool-card adjst">
                <img src="cleaning.png" alt="Cleaning">
                <div class="caption">Cleaning</div>
            </div>
        </div>

        <footer>© 2026 uTool. Built for the community.</footer>
    </section>

</body>

</html>