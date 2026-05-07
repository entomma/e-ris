<?php
session_start();
/** @var PDO $conn */
include('../includes/db_connect.php');

// ✅ Require login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// ✅ Get current admin
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$currentAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Restrict to head_admin only
$allowedRoles = ['head_admin', 'bsit head admin', 'bsba head admin', 'bshm head admin', 'beed head admin'];

if (!$currentAdmin || !in_array(strtolower($currentAdmin['role']), $allowedRoles)) {
    echo "<script>
        alert('Access denied. Only Head Admins can manage admin accounts.');
        window.location.href='dashboard.php';
    </script>";
    exit();
}

// ✅ Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $first = ucwords(strtolower(trim($_POST['first_name'])));
    $last = ucwords(strtolower(trim($_POST['last_name'])));
    $user = trim($_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'admin';

    // Prevent duplicate usernames
    $check = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $check->execute([$user]);

    if ($check->fetchColumn() > 0) {
        $error = "Username already exists!";
    } else {
        $insert = $conn->prepare("
            INSERT INTO admins (first_name, last_name, username, password, role, status, activation, created_at)
            VALUES (?, ?, ?, ?, ?, 'inactive', 'activated', NOW())
        ");
        $insert->execute([$first, $last, $user, $pass, $role]);

        // Log creation
        $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'create_admin', ?)");
        $log->execute([$admin_id, "Created admin account for username: $user"]);

        $success = "Admin account created successfully!";
    }
}

// ✅ Handle Activate / Deactivate toggle
if (isset($_GET['toggle'])) {
    $targetId = $_GET['toggle'];

    $getStatus = $conn->prepare("SELECT username, activation FROM admins WHERE id = ?");
    $getStatus->execute([$targetId]);
    $adminData = $getStatus->fetch(PDO::FETCH_ASSOC);

    if ($adminData) {
        $newActivation = strtolower($adminData['activation']) === 'activated' ? 'deactivated' : 'activated';
        $update = $conn->prepare("UPDATE admins SET activation = ? WHERE id = ?");
        $update->execute([$newActivation, $targetId]);

        // ✅ When deactivating, force status to inactive and log them out
        if ($newActivation === 'deactivated') {
            $updateStatus = $conn->prepare("UPDATE admins SET status = 'inactive' WHERE id = ?");
            $updateStatus->execute([$targetId]);
        }

        // ✅ Log toggle
        $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'toggle_activation', ?)");
        $log->execute([$admin_id, "Set {$adminData['username']} to $newActivation"]);

        header("Location: manage_admins.php");
        exit();
    }
}

// ✅ Fetch all admins
$admins = $conn->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Admins | E-RIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.navbar { background-color: #800000 !important; }
.text-maroon { color: #800000 !important; }
.card { border-radius: 12px; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">E-RIS Admin Panel</a>
    <div class="ms-auto">
      <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="logout.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<div class="container py-5">
  <h3 class="fw-bold text-maroon mb-4">
    <i class="bi bi-people-fill me-2"></i>Manage Admin Accounts
  </h3>

  <!-- ✅ Alerts -->
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php elseif (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <!-- ✅ Add Admin -->
  <div class="card shadow-sm p-4 mb-4">
    <h5 class="fw-bold text-maroon mb-3">
      <i class="bi bi-person-plus-fill me-2"></i>Add New Admin
    </h5>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-3">
          <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
        </div>
        <div class="col-md-3">
          <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
        </div>
        <div class="col-md-3">
          <input type="text" name="username" class="form-control" placeholder="Username" required>
        </div>
        <div class="col-md-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <div class="col-md-12 text-end">
          <button type="submit" name="add_admin" class="btn btn-primary">
            <i class="bi bi-person-add"></i> Create Admin
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- ✅ Admin List -->
  <div class="card shadow-sm p-4">
    <h5 class="fw-bold text-maroon mb-3">
      <i class="bi bi-list-ul me-2"></i>Existing Admins
    </h5>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="text-white" style="background-color:#800000;">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Login Status</th>
            <th>Activation</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a): ?>
          <tr>
            <td><?php echo $a['id']; ?></td>
            <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
            <td><?php echo htmlspecialchars($a['username']); ?></td>
            <td><?php echo ucfirst($a['role']); ?></td>
            <td>
              <span class="badge bg-<?php echo $a['status'] === 'active' ? 'success' : 'secondary'; ?>">
                <?php echo ucfirst($a['status']); ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?php echo $a['activation'] === 'activated' ? 'success' : 'danger'; ?>">
                <?php echo ucfirst($a['activation']); ?>
              </span>
            </td>
            <td><?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?></td>
            <td>
              <?php if ($a['id'] != $admin_id): ?>
                <a href="?toggle=<?php echo $a['id']; ?>" 
                   class="btn btn-sm <?php echo $a['activation'] === 'activated' ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                   <i class="bi bi-toggle-<?php echo $a['activation'] === 'activated' ? 'off' : 'on'; ?>"></i>
                   <?php echo $a['activation'] === 'activated' ? 'Deactivate' : 'Activate'; ?>
                </a>
              <?php else: ?>
                <span class="text-muted">You</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>