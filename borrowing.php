<?php
/* ============================================================
   borrowing.php – BORROWING (University Library)
   - Uses: config.php (PDO)
   - Tables: borrowing, member, staff, book
   - Features:
     * List borrowings with joins
     * Add borrowing (decrease CopiesAvailable)
     * Return borrowing (increase CopiesAvailable + update status + fine)
     * Overdue filter
   - Uses external CSS: style.css
   ============================================================ */

require "config.php";

$action = $_GET['action'] ?? 'list';

function redirect_msg(string $msg): void {
  header("Location: borrowing.php?msg=" . urlencode($msg));
  exit;
}

function s(?string $v): ?string {
  $v = isset($v) ? trim($v) : null;
  return ($v === '') ? null : $v;
}

function i($v): ?int {
  if (!isset($v)) return null;
  $v = trim((string)$v);
  return ($v === '') ? null : (int)$v;
}

/* ============================================================
   ACTION: ADD BORROWING (Transaction)
   - Insert borrowing
   - Decrease book.CopiesAvailable by 1
============================================================ */
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $BorrowID   = i($_POST['BorrowID'] ?? null);
  $BorrowDate = s($_POST['BorrowDate'] ?? null);
  $DueDate    = s($_POST['DueDate'] ?? null);
  $MID        = i($_POST['MID'] ?? null);
  $SID        = i($_POST['SID'] ?? null);
  $BID        = i($_POST['BID'] ?? null);

  if (!$BorrowID || !$BorrowDate || !$DueDate || !$MID || !$SID || !$BID) {
    redirect_msg("Add failed: Please fill BorrowID, BorrowDate, DueDate, MID, SID, BID.");
  }

  if (strtotime($DueDate) < strtotime($BorrowDate)) {
    redirect_msg("Add failed: DueDate must be after or equal to BorrowDate.");
  }

  try {
    $pdo->beginTransaction();

    // Check availability
    $chk = $pdo->prepare("SELECT CopiesAvailable FROM book WHERE BookID = ? FOR UPDATE");
    $chk->execute([$BID]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $pdo->rollBack();
      redirect_msg("Add failed: Book not found.");
    }

    if ((int)$row['CopiesAvailable'] <= 0) {
      $pdo->rollBack();
      redirect_msg("Add failed: No available copies for this book.");
    }

    // Insert borrowing (FineAmount default 0, Status default 'Borrowed')
    $ins = $pdo->prepare("
      INSERT INTO borrowing
        (BorrowID, BorrowDate, DueDate, ReturnDate, FineAmount, Status, MID, SID, BID)
      VALUES
        (?, ?, ?, NULL, 0.00, 'Borrowed', ?, ?, ?)
    ");
    $ins->execute([$BorrowID, $BorrowDate, $DueDate, $MID, $SID, $BID]);

    // Decrease available copies
    $upd = $pdo->prepare("UPDATE book SET CopiesAvailable = CopiesAvailable - 1 WHERE BookID = ?");
    $upd->execute([$BID]);

    $pdo->commit();
    redirect_msg("Borrowing added successfully.");
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // 1062 Duplicate entry
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      redirect_msg("Add failed: BorrowID already exists. Use a different BorrowID.");
    }

    redirect_msg("Add failed: " . $e->getMessage());
  }
}

/* ============================================================
   ACTION: RETURN BOOK (Transaction)
   - Update borrowing ReturnDate + Status + FineAmount
   - Increase book.CopiesAvailable by 1
   Fine policy: 2 SAR per late day (edit if needed)
============================================================ */
if ($action === 'return' && isset($_GET['id'])) {
  $BorrowID = (int)$_GET['id'];

  try {
    $pdo->beginTransaction();

    // Lock borrowing row
    $q = $pdo->prepare("SELECT BorrowID, DueDate, Status, BID FROM borrowing WHERE BorrowID = ? FOR UPDATE");
    $q->execute([$BorrowID]);
    $br = $q->fetch(PDO::FETCH_ASSOC);

    if (!$br) {
      $pdo->rollBack();
      redirect_msg("Return failed: Borrowing record not found.");
    }

    if ($br['Status'] === 'Returned') {
      $pdo->rollBack();
      redirect_msg("Return skipped: This borrowing is already returned.");
    }

    $BID = (int)$br['BID'];

    // Calculate fine (2 per day late)
    $today = date('Y-m-d');
    $due   = $br['DueDate'];

    $lateDays = 0;
    if (strtotime($today) > strtotime($due)) {
      $diff = (strtotime($today) - strtotime($due)) / 86400;
      $lateDays = (int)floor($diff);
    }
    $fine = $lateDays * 2.00;

    $newStatus = ($lateDays > 0) ? 'Overdue' : 'Returned';

    // Update borrowing
    $up = $pdo->prepare("
      UPDATE borrowing
      SET ReturnDate = ?, Status = ?, FineAmount = ?
      WHERE BorrowID = ?
    ");
    $up->execute([$today, $newStatus, $fine, $BorrowID]);

    // Increase available copies back
    $updBook = $pdo->prepare("UPDATE book SET CopiesAvailable = CopiesAvailable + 1 WHERE BookID = ?");
    $updBook->execute([$BID]);

    $pdo->commit();
    redirect_msg("Book returned successfully. Status: $newStatus, Fine: $fine");
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_msg("Return failed: " . $e->getMessage());
  }
}

/* ============================================================
   ACTION: DELETE BORROWING (optional)
   - Only allow delete if status Returned (so copies aren't broken)
============================================================ */
if ($action === 'delete' && isset($_GET['id'])) {
  $BorrowID = (int)$_GET['id'];

  try {
    $stmt = $pdo->prepare("SELECT Status FROM borrowing WHERE BorrowID=?");
    $stmt->execute([$BorrowID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) redirect_msg("Delete failed: Borrowing record not found.");

    if ($row['Status'] !== 'Returned') {
      redirect_msg("Cannot delete: only Returned records can be deleted (to keep inventory consistent).");
    }

    $del = $pdo->prepare("DELETE FROM borrowing WHERE BorrowID=?");
    $del->execute([$BorrowID]);

    redirect_msg("Borrowing deleted successfully.");
  } catch (PDOException $e) {
    redirect_msg("Delete failed: " . $e->getMessage());
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Borrowing</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="container">

  <?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
  <?php endif; ?>

  <?php if ($action === 'list' || $action === 'overdue'): ?>

    <?php
      $where = "";
      if ($action === 'overdue') {
        // Overdue: not returned and due date < today OR status is Overdue
        $where = "WHERE (b.ReturnDate IS NULL AND b.DueDate < CURDATE()) OR b.Status='Overdue'";
      }

      $rows = $pdo->query("
        SELECT
          b.BorrowID, b.BorrowDate, b.DueDate, b.ReturnDate, b.FineAmount, b.Status,
          m.MemID, m.FullName AS MemberName,
          s.StaffID, s.FullName AS StaffName,
          bk.BookID, bk.Title AS BookTitle
        FROM borrowing b
        JOIN member m ON m.MemID = b.MID
        JOIN staff  s ON s.StaffID = b.SID
        JOIN book   bk ON bk.BookID = b.BID
        $where
        ORDER BY b.BorrowID DESC
      ")->fetchAll();
    ?>

    <h2>Borrowing</h2>

    <a href="borrowing.php?action=add" class="btn btn-primary add-btn">➕ Add Borrowing</a>
    <a href="borrowing.php" class="btn btn-secondary add-btn">All</a>
    <a href="borrowing.php?action=overdue" class="btn btn-danger add-btn">Overdue</a>
    <div class="clearfix"></div>

    <table>
      <tr>
        <th>ID</th>
        <th>Member</th>
        <th>Book</th>
        <th>Staff</th>
        <th>Borrow Date</th>
        <th>Due Date</th>
        <th>Return Date</th>
        <th>Status</th>
        <th>Fine</th>
        <th>Actions</th>
      </tr>

      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['BorrowID'] ?></td>
          <td><?= htmlspecialchars($r['MemberName']) ?> (<?= (int)$r['MemID'] ?>)</td>
          <td><?= htmlspecialchars($r['BookTitle']) ?> (<?= (int)$r['BookID'] ?>)</td>
          <td><?= htmlspecialchars($r['StaffName']) ?> (<?= (int)$r['StaffID'] ?>)</td>
          <td><?= htmlspecialchars($r['BorrowDate']) ?></td>
          <td><?= htmlspecialchars($r['DueDate']) ?></td>
          <td><?= htmlspecialchars($r['ReturnDate'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['Status']) ?></td>
          <td><?= htmlspecialchars($r['FineAmount']) ?></td>
          <td>
            <div class="table-actions">
              <?php if ($r['Status'] !== 'Returned'): ?>
                <a class="btn btn-primary" href="borrowing.php?action=return&id=<?= (int)$r['BorrowID'] ?>"
                   onclick="return confirm('Mark as returned?');">Return</a>
              <?php endif; ?>
              <a class="btn btn-danger" href="borrowing.php?action=delete&id=<?= (int)$r['BorrowID'] ?>"
                 onclick="return confirm('Delete borrowing record?');">Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($action === 'add'): ?>

    <?php
      // Dropdown sources
      $members = $pdo->query("SELECT MemID, FullName FROM member ORDER BY FullName")->fetchAll();
      $staffs  = $pdo->query("SELECT StaffID, FullName FROM staff ORDER BY FullName")->fetchAll();

      // Only books with available copies
      $books   = $pdo->query("SELECT BookID, Title, CopiesAvailable FROM book WHERE CopiesAvailable > 0 ORDER BY Title")->fetchAll();

      $today = date('Y-m-d');
    ?>

    <h2>Add Borrowing</h2>
    <p class="small">Only books with available copies are shown. Returning will auto-update inventory and fine.</p>

    <form method="post" action="borrowing.php?action=add">
      <label>BorrowID</label>
      <input name="BorrowID" placeholder="Random ID (e.g., 7001)" required>

      <label>Borrow Date</label>
      <input name="BorrowDate" type="date" value="<?= htmlspecialchars($today) ?>" required>

      <label>Due Date</label>
      <input name="DueDate" type="date" required>

      <label>Member (MID)</label>
      <select name="MID" required>
        <option value="">-- Select Member --</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['MemID'] ?>"><?= htmlspecialchars($m['FullName']) ?> (<?= (int)$m['MemID'] ?>)</option>
        <?php endforeach; ?>
      </select>

      <label>Staff (SID)</label>
      <select name="SID" required>
        <option value="">-- Select Staff --</option>
        <?php foreach ($staffs as $s): ?>
          <option value="<?= (int)$s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?> (<?= (int)$s['StaffID'] ?>)</option>
        <?php endforeach; ?>
      </select>

      <label>Book (BID)</label>
      <select name="BID" required>
        <option value="">-- Select Book --</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int)$b['BookID'] ?>">
            <?= htmlspecialchars($b['Title']) ?> (<?= (int)$b['BookID'] ?>) - Available: <?= (int)$b['CopiesAvailable'] ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="borrowing.php">Cancel</a>
    </form>

  <?php else: ?>
    <div class="msg">Invalid action.</div>
    <a class="btn btn-secondary" href="borrowing.php">Back</a>
  <?php endif; ?>

</div>
</body>
</html>
