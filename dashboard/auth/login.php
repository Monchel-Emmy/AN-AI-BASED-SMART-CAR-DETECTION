<?php
session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /accident/web/dashboard/');
    exit;
}

require_once __DIR__ . '/../../api/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];

            // Update last login
            getDB()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                   ->execute([$user['id']]);

            header('Location: /accident/web/dashboard/');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Accident Detection</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f1117;
            --surface: #1a1d27;
            --border:  #2e3250;
            --primary: #4f6ef7;
            --text:    #e2e8f0;
            --muted:   #8892b0;
            --error:   #ef4444;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon { font-size: 48px; display: block; margin-bottom: 10px; }

        .brand h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        .brand p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }

        label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input {
            background: #0f1117;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 14px;
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
            width: 100%;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 4px;
        }

        .btn:hover { opacity: 0.9; }

        .error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: var(--error);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="brand">
        <span class="brand-icon">🚨</span>
        <h1>AccidentWatch</h1>
        <p>Sign in to access the dashboard</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password">
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</div>

</body>
</html>
