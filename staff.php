<?php
/* ============================================================
   staff.php – STAFF CRUD (University Library)
   - Uses: config.php (PDO)
   - Table: staff
   - Prevent delete if staff linked to borrowing
   - Friendly errors: duplicate Email / duplicate StaffID
   - Uses external CSS: style.css
   ============================================================ */

require "config.php";

$action = $_GET['action'] ?? 'list';

function redirect_msg(string $msg): void {
  header("Location: staff.php?msg=" . urlencode($msg));
  exit;
}

function s(?string $v): ?string {
  $v = isset($v) ? trim($v) : null;
  return ($v === '') ? null : $v;
}

/* -------------------------
   DELETE STAFF (SAFE)
--------------------------*/
if ($action === 'delete' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  // Block delete if staff has borrowing records
  $chk = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE SID = ?");
  $chk->execute([$id]);
  $used = (int)$chk->fetchColumn();

  if ($used > 0) {
    redirect_msg("Cannot delete: this staff member is linked to borrowing records.");
  }

  try {
    $stmt = $pdo->prepare("DELETE FROM staff WHERE StaffID = ?");
    $stmt->execute([$id]);
    redirect_msg("Staff deleted successfully.");
  } catch (PDOException $e) {
    redirect_msg("Delete failed: " . $e->getMessage());
  }
}

/* -------------------------
   ADD STAFF
--------------------------*/
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $StaffID  = (int)($_POST['StaffID'] ?? 0);
  $FullName = s($_POST['FullName'] ?? null);
  $Email    = s($_POST['Email'] ?? null);
  $Phone    = s($_POST['Phone'] ?? null);
  $HireDate = s($_POST['HireDate'] ?? null);
  $Shift    = s($_POST['Shift'] ?? null);
  $Status   = s($_POST['Status'] ?? null);

  if (!$StaffID || !$FullName || !$Email || !$HireDate || !$Shift || !$Status) {
    redirect_msg("Add failed: Please fill StaffID, FullName, Email, HireDate, Shift, Status.");
  }

  try {
    $sql = "INSERT INTO staff
      (StaffID, FullName, Email, Phone, HireDate, Shift, Status)
      VALUES (?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $StaffID, $FullName, $Email, $Phone, $HireDate, $Shift, $Status
    ]);

    redirect_msg("Staff added successfully.");
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'Email') !== false) {
        redirect_msg("Add failed: Email already exists. Use a unique email.");
      }
      if (stripos($msg, 'PRIMARY') !== false || stripos($msg, 'StaffID') !== false) {
        redirect_msg("Add failed: StaffID already exists. Use a different StaffID.");
      }
      redirect_msg("Add failed: Duplicate value (unique constraint).");
    }
    redirect_msg("Add failed: " . $e->getMessage());
  }
}

/* -------------------------
   UPDATE STAFF
--------------------------*/
if ($action === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id       = (int)$_GET['id'];
  $FullName = s($_POST['FullName'] ?? null);
  $Email    = s($_POST['Email'] ?? null);
  $Phone    = s($_POST['Phone'] ?? null);
  $HireDate = s($_POST['HireDate'] ?? null);
  $Shift    = s($_POST['Shift'] ?? null);
  $Status   = s($_POST['Status'] ?? null);

  if (!$FullName || !$Email || !$HireDate || !$Shift || !$Status) {
    redirect_msg("Update failed: Please fill FullName, Email, HireDate, Shift, Status.");
  }

  try {
    $sql = "UPDATE staff SET
      FullName=?, Email=?, Phone=?, HireDate=?, Shift=?, Status=?
      WHERE StaffID=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $FullName, $Email, $Phone, $HireDate, $Shift, $Status, $id
    ]);

    redirect_msg("Staff updated successfully.");
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'Email') !== false) {
        redirect_msg("Update failed: Email already exists for another staff member. Use a unique email.");
      }
      redirect_msg("Update failed: Duplicate value (unique constraint).");
    }
    redirect_msg("Update failed: " . $e->getMessage());
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="container">

  <?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>
    <?php
      $staffs = $pdo->query("SELECT StaffID, FullName, Email, Phone, HireDate, Shift, Status
                             FROM staff
                             ORDER BY StaffID DESC")->fetchAll();
    ?>
    <h2>Staff</h2>

    <a href="staff.php?action=add" class="btn btn-primary add-btn">➕ Add Staff</a>
    <div class="clearfix"></div>

    <table>
      <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Hire Date</th>
        <th>Shift</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>

      <?php foreach ($staffs as $srow): ?>
        <tr>
          <td><?= (int)$srow['StaffID'] ?></td>
          <td><?= htmlspecialchars($srow['FullName']) ?></td>
          <td><?= htmlspecialchars($srow['Email']) ?></td>
          <td><?= htmlspecialchars($srow['Phone'] ?? '') ?></td>
          <td><?= htmlspecialchars($srow['HireDate']) ?></td>
          <td><?= htmlspecialchars($srow['Shift']) ?></td>
          <td><?= htmlspecialchars($srow['Status']) ?></td>
          <td>
            <div class="table-actions">
              <a class="btn btn-secondary" href="staff.php?action=edit&id=<?= (int)$srow['StaffID'] ?>">Edit</a>
              <a class="btn btn-danger"
                 href="staff.php?action=delete&id=<?= (int)$srow['StaffID'] ?>"
                 onclick="return confirm('Delete staff member?');">Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($action === 'add'): ?>

    <h2>Add Staff</h2>
    <p class="small">Email must be unique. If staff has borrowing records, deletion is blocked.</p>

    <form method="post" action="staff.php?action=add">
      <label>StaffID</label>
      <input name="StaffID" placeholder="Random ID (e.g., 2101)" required>

      <label>Full Name</label>
      <input name="FullName" placeholder="Full name" required>

      <label>Email</label>
      <input name="Email" placeholder="Unique email" required>

      <label>Phone</label>
      <input name="Phone" placeholder="05xxxxxxxx">

      <label>Hire Date</label>
      <input name="HireDate" type="date" required>

      <label>Shift</label>
      <input name="Shift" placeholder="Morning / Evening / Night" required>

      <label>Status</label>
      <input name="Status" placeholder="Active / Inactive" required>

      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="staff.php">Cancel</a>
    </form>

  <?php elseif ($action === 'edit' && isset($_GET['id'])): ?>

    <?php
      $stmt = $pdo->prepare("SELECT * FROM staff WHERE StaffID=?");
      $stmt->execute([(int)$_GET['id']]);
      $staff = $stmt->fetch();

      if (!$staff) {
        echo "<div class='msg'>Staff member not found.</div>";
        echo "<a class='btn btn-secondary' href='staff.php'>Back</a>";
        exit;
      }
    ?>

    <h2>Edit Staff</h2>
    <p class="small">Email must be unique.</p>

    <form method="post" action="staff.php?action=edit&id=<?= (int)$staff['StaffID'] ?>">
      <label>Full Name</label>
      <input name="FullName" value="<?= htmlspecialchars($staff['FullName']) ?>" required>

      <label>Email</label>
      <input name="Email" value="<?= htmlspecialchars($staff['Email']) ?>" required>

      <label>Phone</label>
      <input name="Phone" value="<?= htmlspecialchars($staff['Phone'] ?? '') ?>">

      <label>Hire Date</label>
      <input name="HireDate" type="date" value="<?= htmlspecialchars($staff['HireDate']) ?>" required>

      <label>Shift</label>
      <input name="Shift" value="<?= htmlspecialchars($staff['Shift']) ?>" required>

      <label>Status</label>
      <input name="Status" value="<?= htmlspecialchars($staff['Status']) ?>" required>

      <button class="btn btn-primary" type="submit">Update</button>
      <a class="btn btn-secondary" href="staff.php">Cancel</a>
    </form>

  <?php else: ?>
    <div class="msg">Invalid action.</div>
    <a class="btn btn-secondary" href="staff.php">Back</a>
  <?php endif; ?>

</div>
</body>
</html>
