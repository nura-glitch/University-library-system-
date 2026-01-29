<?php
/* ============================================================
   member_books.php – Member ↔ Book Borrowing Summary
   - Uses: config.php (PDO)
   - Tables: borrowing, member, book, staff
   - Shows: member info + borrowed book + dates + status + fine + staff
   - Filters: member, status, overdue-only, search (name/email/title/isbn)
   - Export: CSV (same filters)
   ============================================================ */

require "config.php";

function esc(?string $v): string {
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$mid          = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
$status       = $_GET['status'] ?? '';
$only_overdue = isset($_GET['overdue']) && $_GET['overdue'] === '1';
$q            = trim($_GET['q'] ?? '');
$export       = isset($_GET['export']) && $_GET['export'] === '1';

$where = [];
$params = [];

// Filter by member
if ($mid > 0) {
  $where[] = "m.MemID = ?";
  $params[] = $mid;
}

// Filter by status
$allowedStatus = ['Borrowed', 'Returned', 'Overdue'];
if ($status !== '' && in_array($status, $allowedStatus, true)) {
  $where[] = "b.Status = ?";
  $params[] = $status;
}

// Overdue-only
if ($only_overdue) {
  $where[] = "((b.ReturnDate IS NULL AND b.DueDate < CURDATE()) OR b.Status='Overdue')";
}

// Search (member name/email, book title/ISBN)
if ($q !== '') {
  $where[] = "(m.FullName LIKE ? OR m.Email LIKE ? OR bk.Title LIKE ? OR bk.ISBN LIKE ?)";
  $like = "%" . $q . "%";
  array_push($params, $like, $like, $like, $like);
}

$whereSQL = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* Dropdown members */
$members = $pdo->query("SELECT MemID, FullName, Email FROM member ORDER BY FullName")->fetchAll();

/* Main query */
$sql = "
  SELECT
    b.BorrowID,
    b.BorrowDate, b.DueDate, b.ReturnDate, b.Status, b.FineAmount,
    m.MemID, m.FullName AS MemberName, m.Email AS MemberEmail, m.Phone AS MemberPhone, m.Role, m.Department, m.JoinDate,
    bk.BookID, bk.Title AS BookTitle, bk.ISBN, bk.Author, bk.Publisher, bk.Category, bk.Section, bk.ShelfLocation, bk.RowNumber,
    s.StaffID, s.FullName AS StaffName, s.Email AS StaffEmail, s.Shift
  FROM borrowing b
  JOIN member m ON m.MemID = b.MID
  JOIN book bk  ON bk.BookID = b.BID
  JOIN staff s  ON s.StaffID = b.SID
  $whereSQL
  ORDER BY b.BorrowDate DESC, b.BorrowID DESC
";

/* ============================================================
   CSV Export (same filters/search)
============================================================ */
if ($export) {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=member_books_summary.csv');

  $out = fopen('php://output', 'w');

  // Header row
  fputcsv($out, [
    'BorrowID','BorrowDate','DueDate','ReturnDate','Status','FineAmount',
    'MemID','MemberName','MemberEmail','MemberPhone','Role','Department','JoinDate',
    'BookID','BookTitle','ISBN','Author','Publisher','Category','Section','ShelfLocation','RowNumber',
    'StaffID','StaffName','StaffEmail','Shift'
  ]);

  foreach ($rows as $r) {
    fputcsv($out, [
      $r['BorrowID'], $r['BorrowDate'], $r['DueDate'], $r['ReturnDate'], $r['Status'], $r['FineAmount'],
      $r['MemID'], $r['MemberName'], $r['MemberEmail'], $r['MemberPhone'], $r['Role'], $r['Department'], $r['JoinDate'],
      $r['BookID'], $r['BookTitle'], $r['ISBN'], $r['Author'], $r['Publisher'], $r['Category'], $r['Section'], $r['ShelfLocation'], $r['RowNumber'],
      $r['StaffID'], $r['StaffName'], $r['StaffEmail'], $r['Shift']
    ]);
  }

  fclose($out);
  exit;
}

/* Normal page render */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Build Export URL (keep current filters) */
$exportParams = $_GET;
$exportParams['export'] = '1';
$exportUrl = 'member_books.php?' . http_build_query($exportParams);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Member Borrowed Books Summary</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .filters { display:flex; gap:12px; flex-wrap:wrap; margin: 12px 0 18px; align-items:center; }
    .filters select, .filters input[type="text"] {
      padding:8px; border-radius:10px; border:1px solid rgba(0,0,0,.15);
      min-width: 220px;
    }
    .filters label { display:flex; align-items:center; gap:6px; }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid rgba(0,0,0,.12); display:inline-block; }
    .b-borrowed { background:#e8f2ff; }
    .b-returned  { background:#e9ffe8; }
    .b-overdue   { background:#ffe8e8; }
    .meta { font-size:12px; color:#555; }
    .nowrap { white-space:nowrap; }
    .actions-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
  </style>
</head>
<body>
<div class="container">
  <h2>Borrowing Summary (Member ↔ Book)</h2>
  <p class="small">Search + filters + export CSV.</p>

  <form class="filters" method="get" action="member_books.php">
    <select name="mid">
      <option value="0">All Members</option>
      <?php foreach ($members as $m): ?>
        <option value="<?= (int)$m['MemID'] ?>" <?= $mid === (int)$m['MemID'] ? 'selected' : '' ?>>
          <?= esc($m['FullName']) ?> (<?= esc($m['Email']) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <select name="status">
      <option value="">All Status</option>
      <?php foreach (['Borrowed','Returned','Overdue'] as $st): ?>
        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>

    <label>
      <input type="checkbox" name="overdue" value="1" <?= $only_overdue ? 'checked' : '' ?>>
      Overdue only
    </label>

    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Search name/email/title/ISBN">

    <button class="btn btn-primary" type="submit">Apply</button>
    <a class="btn btn-secondary" href="member_books.php">Reset</a>
    <a class="btn btn-primary" href="<?= esc($exportUrl) ?>">Export CSV</a>
  </form>

  <table>
    <tr>
      <th class="nowrap">BorrowID</th>
      <th>Member</th>
      <th>Book</th>
      <th class="nowrap">Borrow Date</th>
      <th class="nowrap">Due Date</th>
      <th class="nowrap">Return Date</th>
      <th>Status</th>
      <th class="nowrap">Fine</th>
      <th>Handled By</th>
    </tr>

    <?php foreach ($rows as $r): ?>
      <?php
        $badgeClass = $r['Status'] === 'Returned' ? 'b-returned' : ($r['Status'] === 'Overdue' ? 'b-overdue' : 'b-borrowed');
      ?>
      <tr>
        <td class="nowrap"><?= (int)$r['BorrowID'] ?></td>

        <td>
          <strong><?= esc($r['MemberName']) ?></strong><br>
          <span class="meta"><?= esc($r['MemberEmail']) ?></span><br>
          <span class="meta"><?= esc($r['Role']) ?><?= $r['Department'] ? " • " . esc($r['Department']) : "" ?></span>
        </td>

        <td>
          <strong><?= esc($r['BookTitle']) ?></strong><br>
          <span class="meta">ISBN: <?= esc($r['ISBN']) ?></span><br>
          <span class="meta">
            <?= $r['Author'] ? "Author: " . esc($r['Author']) . " • " : "" ?>
            <?= $r['Category'] ? "Category: " . esc($r['Category']) : "" ?>
          </span><br>
          <span class="meta">
            <?= $r['Section'] ? "Section: " . esc($r['Section']) . " • " : "" ?>
            <?= $r['ShelfLocation'] ? "Shelf: " . esc($r['ShelfLocation']) : "" ?>
            <?= $r['RowNumber'] !== null ? " • Row: " . (int)$r['RowNumber'] : "" ?>
          </span>
        </td>

        <td class="nowrap"><?= esc($r['BorrowDate']) ?></td>
        <td class="nowrap"><?= esc($r['DueDate']) ?></td>
        <td class="nowrap"><?= esc($r['ReturnDate']) ?></td>

        <td><span class="badge <?= $badgeClass ?>"><?= esc($r['Status']) ?></span></td>

        <td class="nowrap"><?= number_format((float)$r['FineAmount'], 2) ?></td>

        <td>
          <strong><?= esc($r['StaffName']) ?></strong><br>
          <span class="meta"><?= esc($r['StaffEmail']) ?></span><br>
          <span class="meta"><?= esc($r['Shift']) ?></span>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if (count($rows) === 0): ?>
    <div class="msg">No records found with the selected filters/search.</div>
  <?php endif; ?>

  <div class="actions-row">
    <a class="btn btn-secondary" href="index.php">Back to Home</a>
    <a class="btn btn-primary" href="borrowing.php">Manage Borrowing</a>
  </div>
</div>
</body>
</html>
