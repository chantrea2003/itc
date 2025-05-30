<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php?page=no_permission");
    exit;
}

include 'connection.php';

if (!isset($_GET['id'])) {
    die('User ID is required.');
}

if ($_GET['id'] == $_SESSION['user_id']) {
    header("Location: dashboard.php?page=user_profile");
}

$user_id = (int)$_GET['id'];

$stmt = $mysqli->prepare("
    SELECT 
        u.username, u.image_url, 
        up.first_name, up.last_name, up.dob, up.gender, up.blood_type, 
        uc.telegram_chat_id, 
        r.name AS role_name,
        up.last_updated
    FROM users u 
    JOIN user_profiles up ON u.id = up.user_id 
    LEFT JOIN user_contacts uc ON u.id = uc.user_id 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die('<h5>User not found.</h5><a href="dashboard.php?page=user_management" class="btn btn-primary mt-4 px-4">Back</a>');
}

$stmt->bind_result($username, $image_url, $first_name, $last_name, $dob, $gender, $blood_type, $telegram_chat_id, $role_name, $last_updated);
$stmt->fetch();
$stmt->close();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container">
    <div class="p-5 border rounded bg-white shadow-sm" style="margin: auto; max-width: 500px;">
        <div class="card-body text-center">
            <img src="<?= $image_url ?: 'uploads/assets/default-user.png' ?>" class="rounded-circle mb-3 border" alt="Profile Image" style="width: 120px; height: 120px; object-fit: cover;">
            <h4 class="card-title mb-1"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></h4>
            <p class="text-muted mb-2">@<?= htmlspecialchars($username) ?></p>
            <span class="badge bg-info text-dark mb-3"><?= htmlspecialchars(ucfirst($role_name)) ?></span>

            <ul class="list-group list-group-flush text-start">
                <li class="list-group-item"><strong>Gender:</strong> <?= htmlspecialchars(ucfirst($gender) ?: 'N/A') ?></li>
                <li class="list-group-item"><strong>Date of Birth:</strong> <?= htmlspecialchars($dob ?: 'N/A') ?></li>
                <li class="list-group-item"><strong>Blood Type:</strong> <?= htmlspecialchars($blood_type ?: 'N/A') ?></li>
                <li class="list-group-item"><strong>Telegram Chat ID:</strong> <?= htmlspecialchars($telegram_chat_id ?: 'N/A') ?></li>
                <li class="list-group-item"><strong>Last Update:</strong> <?= htmlspecialchars($last_updated ?: 'N/A') ?></li>
            </ul>

            <a href="dashboard.php?page=user_management" class="btn btn-primary mt-4 px-4">Back</a>
        </div>
    </div>
</div>