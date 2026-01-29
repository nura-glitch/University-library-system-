<?php
/* ============================================================
   members.php – MEMBER CRUD (University Library)
   - Uses: config.php (PDO)
   - Table: member
   - Borrowing count is computed from borrowing table (no TotalBorrowedBook column needed)
   - Prevent delete if member linked to borrowing
   - Friendly errors: duplicate Email / duplicate MemID
   - Uses external CSS: style.css
   ============================================================ */

require "config.php";

$action = $_GET['action'] ?? 'list';

function redirect_msg(string $msg): void {
  header("Location: members.php?msg=" . urlencode($msg));
  exit;
}

function s(?string $v): ?string {
  $v = isset($v) ? trim($v) : null;
  return ($v === '') ? null : $v;
}

/* -------------------------
   DELETE MEMBER (SAFE)
--------------------------*/
if ($action === 'delete' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  // Block delete if member has borrowing records
  $chk = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE MID = ?");
  $chk->execute([$id]);
  $used = (int)$chk->fetchColumn();

  if ($used > 0) {
    redirect_msg("Cannot delete: this member is linked to borrowing records.");
  }

  try {
    $stmt = $pdo->prepare("DELETE FROM member WHERE MemID = ?");
    $stmt->execute([$id]);
    redirect_msg("Member deleted successfully.");
  } catch (PDOException $e) {
    redirect_msg("Delete failed: " . $e->getMessage());
  }
}

/* -------------------------
   ADD MEMBER
--------------------------*/
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $MemID      = (int)($_POST['MemID'] ?? 0);
  $FullName   = s($_POST['FullName'] ?? null);
  $Role       = s($_POST['Role'] ?? null);
  $Email      = s($_POST['Email'] ?? null);
  $Phone      = s($_POST['Phone'] ?? null);
  $JoinDate   = s($_POST['JoinDate'] ?? null);
  $Department = s($_POST['Department'] ?? null);

  if (!$MemID || !$FullName || !$Role || !$Email || !$JoinDate) {
    redirect_msg("Add failed: Please fill MemID, FullName, Role, Email, JoinDate.");
  }

  try {
    $sql = "INSERT INTO member
      (MemID, FullName, Role, Email, Phone, JoinDate, Department)
      VALUES (?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $MemID, $FullName, $Role, $Email, $Phone, $JoinDate, $Department
    ]);

    redirect_msg("Member added successfully.");
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'Email') !== false) {
        redirect_msg("Add failed: Email already exists. Use a unique email.");
      }
      if (stripos($msg, 'PRIMARY') !== false || stripos($msg, 'MemID') !== false) {
        redirect_msg("Add failed: MemID already exists. Use a different MemID.");
      }
      redirect_msg("Add failed: Duplicate value (unique constraint).");
    }
    redirect_msg("Add failed: " . $e->getMessage());
  }
}

/* -------------------------
   UPDATE MEMBER
--------------------------*/
if ($action === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id         = (int)$_GET['id'];
  $FullName   = s($_POST['FullName'] ?? null);
  $Role       = s($_POST['Role'] ?? null);
  $Email      = s($_POST['Email'] ?? null);
  $Phone      = s($_POST['Phone'] ?? null);
  $JoinDate   = s($_POST['JoinDate'] ?? null);
  $Department = s($_POST['Department'] ?? null);

  if (!$FullName || !$Role || !$Email || !$JoinDate) {
    redirect_msg("Update failed: Please fill FullName, Role, Email, JoinDate.");
  }

  try {
    $sql = "UPDATE member SET
      FullName=?, Role=?, Email=?, Phone=?, JoinDate=?, Department=?
      WHERE MemID=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $FullName, $Role, $Email, $Phone, $JoinDate, $Department, $id
    ]);

    redirect_msg("Member updated successfully.");
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'Email') !== false) {
        redirect_msg("Update failed: Email already exists for another member. Use a unique email.");
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
  <title>Members</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="container">

  <?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>
    <?php
      // Compute total borrowed per member from borrowing table
      $members = $pdo->query("
        SELECT 
          m.MemID, m.FullName, m.Role, m.Email, m.Phone, m.JoinDate, m.Department,
          COUNT(b.BorrowID) AS TotalBorrowed
        FROM member m
        LEFT JOIN borrowing b ON b.MID = m.MemID
        GROUP BY m.MemID, m.FullName, m.Role, m.Email, m.Phone, m.JoinDate, m.Department
        ORDER BY m.MemID DESC
      ")->fetchAll();
    ?>
    <h2>Members</h2>

    <a href="members.php?action=add" class="btn btn-primary add-btn">➕ Add Member</a>
    <div class="clearfix"></div>

    <table>
      <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Role</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Join Date</th>
        <th>Department</th>
        <th>Total Borrowed</th>
        <th>Actions</th>
      </tr>

      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= (int)$m['MemID'] ?></td>
          <td><?= htmlspecialchars($m['FullName']) ?></td>
          <td><?= htmlspecialchars($m['Role']) ?></td>
          <td><?= htmlspecialchars($m['Email']) ?></td>
          <td><?= htmlspecialchars($m['Phone'] ?? '') ?></td>
          <td><?= htmlspecialchars($m['JoinDate']) ?></td>
          <td><?= htmlspecialchars($m['Department'] ?? '') ?></td>
          <td><?= (int)$m['TotalBorrowed'] ?></td>
          <td>
            <div class="table-actions">
              <a class="btn btn-secondary" href="members.php?action=edit&id=<?= (int)$m['MemID'] ?>">Edit</a>
              <a class="btn btn-danger"
                 href="members.php?action=delete&id=<?= (int)$m['MemID'] ?>"
                 onclick="return confirm('Delete member?');">Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($action === 'add'): ?>

    <h2>Add Member</h2>
    <p class="small">Email must be unique. Total Borrowed is calculated automatically from borrowing records.</p>

    <form method="post" action="members.php?action=add">
      <label>MemID</label>
      <input name="MemID" placeholder="Random ID (e.g., 1201)" required>

      <label>Full Name</label>
      <input name="FullName" placeholder="Full name" required>

      <label>Role</label>
      <input name="Role" placeholder="Student / Teacher / External" required>

      <label>Email</label>
      <input name="Email" placeholder="Unique email" required>

      <label>Phone</label>
      <input name="Phone" placeholder="05xxxxxxxx">

      <label>Join Date</label>
      <input name="JoinDate" type="date" required>

      <label>Department</label>
      <input name="Department" placeholder="Computer Science / DS / IT ...">

      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="members.php">Cancel</a>
    </form>

  <?php elseif ($action === 'edit' && isset($_GET['id'])): ?>

    <?php
      $stmt = $pdo->prepare("SELECT * FROM member WHERE MemID=?");
      $stmt->execute([(int)$_GET['id']]);
      $member = $stmt->fetch();

      if (!$member) {
        echo "<div class='msg'>Member not found.</div>";
        echo "<a class='btn btn-secondary' href='members.php'>Back</a>";
        exit;
      }
    ?>

    <h2>Edit Member</h2>
    <p class="small">Email must be unique.</p>

    <form method="post" action="members.php?action=edit&id=<?= (int)$member['MemID'] ?>">
      <label>Full Name</label>
      <input name="FullName" value="<?= htmlspecialchars($member['FullName']) ?>" required>

      <label>Role</label>
      <input name="Role" value="<?= htmlspecialchars($member['Role']) ?>" required>

      <label>Email</label>
      <input name="Email" value="<?= htmlspecialchars($member['Email']) ?>" required>

      <label>Phone</label>
      <input name="Phone" value="<?= htmlspecialchars($member['Phone'] ?? '') ?>">

      <label>Join Date</label>
      <input name="JoinDate" type="date" value="<?= htmlspecialchars($member['JoinDate']) ?>" required>

      <label>Department</label>
      <input name="Department" value="<?= htmlspecialchars($member['Department'] ?? '') ?>">

      <button class="btn btn-primary" type="submit">Update</button>
      <a class="btn btn-secondary" href="members.php">Cancel</a>
    </form>

  <?php else: ?>
    <div class="msg">Invalid action.</div>
    <a class="btn btn-secondary" href="members.php">Back</a>
  <?php endif; ?>

</div>
</body>
</html>
