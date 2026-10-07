<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$user = getCurrentUser();
$forced = !empty($_SESSION['must_change_password']);
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($current, $row['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'The new password must be at least 8 characters long.';
    } elseif ($new !== $confirm) {
        $error = 'The new passwords do not match.';
    } elseif (password_verify($new, $row['password'])) {
        $error = 'Please choose a password different from your current one.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->bind_param("si", $hash, $user['id']);
        $stmt->execute();
        $_SESSION['must_change_password'] = false;
        session_regenerate_id(true);
        if ($forced) {
            header('Location: ' . homePage());
            exit();
        }
        $message = 'Your password has been changed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo e(companyName()); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .pw-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 40px; width: 100%; max-width: 460px; }
        .pw-card img { max-height: 70px; display: block; margin: 0 auto 15px; border-radius: 8px; }
        .pw-card h1 { text-align: center; color: #1a365d; font-size: 1.6rem; margin-bottom: 5px; }
        .pw-card p.sub { text-align: center; color: #718096; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="pw-card">
        <img src="<?php echo e(companyLogo()); ?>" alt="<?php echo e(companyName()); ?> Logo" onerror="this.style.display='none'">
        <h1>Change Password</h1>
        <p class="sub">
            <?php echo $forced ? 'Welcome, ' . e($user['full_name']) . '! Please choose your own password before continuing.' : 'Update the password for ' . e($user['username']) . '.'; ?>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><?php echo $forced ? 'Temporary password (from your email)' : 'Current password'; ?></label>
                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label>New password (min. 8 characters)</label>
                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-key"></i> Save Password</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            <?php if ($forced): ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a>
            <?php else: ?>
                <a href="<?php echo homePage(); ?>"><i class="fas fa-arrow-left"></i> Back</a>
            <?php endif; ?>
        </p>
    </div>
    <?php echo customerLogoutFooter(); ?>
    <?php echo idleLogoutScript(); ?>
</body>
</html>
