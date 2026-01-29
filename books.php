<?php
/* ============================================================
   books.php – BOOK CRUD (University Library)
   - Uses: config.php (PDO)
   - Tables: book, borrowing
   - Safe delete: prevents deleting books linked to borrowing
   - Friendly errors: duplicate ISBN / duplicate BookID / validation
   - Uses external CSS: style.css
   ============================================================ */

require "config.php";

$action = $_GET['action'] ?? 'list';

/* -------------------------
   Redirect helper with message
--------------------------*/
function redirect_msg(string $msg): void {
  header("Location: books.php?msg=" . urlencode($msg));
  exit;
}

/* -------------------------
   Basic sanitize helpers
--------------------------*/
function s(?string $v): ?string {
  $v = isset($v) ? trim($v) : null;
  return ($v === '') ? null : $v;
}
function i($v): ?int {
  if ($v === null) return null;
  $v = trim((string)$v);
  return ($v === '') ? null : (int)$v;
}

/* ============================================================
   ACTION: DELETE (SAFE)
   - If book used in borrowing => block delete
============================================================ */
if ($action === 'delete' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  // check FK usage
  $chk = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE BID = ?");
  $chk->execute([$id]);
  $used = (int)$chk->fetchColumn();

  if ($used > 0) {
    redirect_msg("Cannot delete: this book is linked to borrowing records.");
  }

  try {
    $del = $pdo->prepare("DELETE FROM book WHERE BookID = ?");
    $del->execute([$id]);
    redirect_msg("Book deleted successfully.");
  } catch (PDOException $e) {
    redirect_msg("Delete failed: " . $e->getMessage());
  }
}

/* ============================================================
   ACTION: ADD
============================================================ */
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $BookID          = i($_POST['BookID'] ?? null);
  $Title           = s($_POST['Title'] ?? null);
  $Author          = s($_POST['Author'] ?? null);
  $Publisher       = s($_POST['Publisher'] ?? null);
  $Language        = s($_POST['Language'] ?? null);
  $Edition         = s($_POST['Edition'] ?? null);
  $ISBN            = s($_POST['ISBN'] ?? null);
  $CopiesTotal     = i($_POST['CopiesTotal'] ?? null);
  $CopiesAvailable = i($_POST['CopiesAvailable'] ?? null);
  $Category        = s($_POST['Category'] ?? null);
  $ShelfLocation   = s($_POST['ShelfLocation'] ?? null);
  $Section         = s($_POST['Section'] ?? null);
  $RowNumber       = i($_POST['RowNumber'] ?? null);

  // Basic validation
  if (!$BookID || !$Title || !$ISBN || $CopiesTotal === null || $CopiesAvailable === null) {
    redirect_msg("Add failed: Please fill BookID, Title, ISBN, CopiesTotal, CopiesAvailable.");
  }
  if ($CopiesTotal < 0 || $CopiesAvailable < 0) {
    redirect_msg("Add failed: Copies cannot be negative.");
  }
  if ($CopiesAvailable > $CopiesTotal) {
    redirect_msg("Add failed: CopiesAvailable must be <= CopiesTotal.");
  }

  try {
    $sql = "INSERT INTO book
      (BookID, Title, Author, Publisher, Language, Edition, ISBN,
       CopiesTotal, CopiesAvailable, Category, ShelfLocation, Section, RowNumber)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $BookID, $Title, $Author, $Publisher, $Language, $Edition, $ISBN,
      $CopiesTotal, $CopiesAvailable, $Category, $ShelfLocation, $Section, $RowNumber
    ]);

    redirect_msg("Book added successfully.");
  } catch (PDOException $e) {
    // 1062 Duplicate entry (UNIQUE)
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'ISBN') !== false) {
        redirect_msg("Add failed: ISBN already exists. Use a unique ISBN.");
      }
      if (stripos($msg, 'PRIMARY') !== false || stripos($msg, 'BookID') !== false) {
        redirect_msg("Add failed: BookID already exists. Use a different BookID.");
      }
      redirect_msg("Add failed: Duplicate value (unique constraint).");
    }
    redirect_msg("Add failed: " . $e->getMessage());
  }
}

/* ============================================================
   ACTION: EDIT (UPDATE)
============================================================ */
if ($action === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id              = (int)$_GET['id'];
  $Title           = s($_POST['Title'] ?? null);
  $Author          = s($_POST['Author'] ?? null);
  $Publisher       = s($_POST['Publisher'] ?? null);
  $Language        = s($_POST['Language'] ?? null);
  $Edition         = s($_POST['Edition'] ?? null);
  $ISBN            = s($_POST['ISBN'] ?? null);
  $CopiesTotal     = i($_POST['CopiesTotal'] ?? null);
  $CopiesAvailable = i($_POST['CopiesAvailable'] ?? null);
  $Category        = s($_POST['Category'] ?? null);
  $ShelfLocation   = s($_POST['ShelfLocation'] ?? null);
  $Section         = s($_POST['Section'] ?? null);
  $RowNumber       = i($_POST['RowNumber'] ?? null);

  if (!$Title || !$ISBN || $CopiesTotal === null || $CopiesAvailable === null) {
    redirect_msg("Update failed: Please fill Title, ISBN, CopiesTotal, CopiesAvailable.");
  }
  if ($CopiesTotal < 0 || $CopiesAvailable < 0) {
    redirect_msg("Update failed: Copies cannot be negative.");
  }
  if ($CopiesAvailable > $CopiesTotal) {
    redirect_msg("Update failed: CopiesAvailable must be <= CopiesTotal.");
  }

  try {
    $sql = "UPDATE book SET
      Title=?, Author=?, Publisher=?, Language=?, Edition=?, ISBN=?,
      CopiesTotal=?, CopiesAvailable=?, Category=?, ShelfLocation=?, Section=?, RowNumber=?
      WHERE BookID=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      $Title, $Author, $Publisher, $Language, $Edition, $ISBN,
      $CopiesTotal, $CopiesAvailable, $Category, $ShelfLocation, $Section, $RowNumber,
      $id
    ]);

    redirect_msg("Book updated successfully.");
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = $e->getMessage();
      if (stripos($msg, 'ISBN') !== false) {
        redirect_msg("Update failed: ISBN already exists for another book. Use a unique ISBN.");
      }
      redirect_msg("Update failed: Duplicate value (unique constraint).");
    }
    redirect_msg("Update failed: " . $e->getMessage());
  }
}

/* ============================================================
   VIEW
============================================================ */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Books</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="container">

  <?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
  <?php endif; ?>

  <?php if ($action === 'list'): ?>
    <?php
      $books = $pdo->query("SELECT BookID, Title, ISBN, CopiesTotal, CopiesAvailable, Category, Section
                            FROM book
                            ORDER BY BookID DESC")->fetchAll();
    ?>
    <h2>Books</h2>

    <a href="books.php?action=add" class="btn btn-primary add-btn">➕ Add Book</a>
    <div class="clearfix"></div>

    <table>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>ISBN</th>
        <th>Total</th>
        <th>Available</th>
        <th>Category</th>
        <th>Section</th>
        <th>Actions</th>
      </tr>

      <?php foreach ($books as $b): ?>
        <tr>
          <td><?= (int)$b['BookID'] ?></td>
          <td><?= htmlspecialchars($b['Title']) ?></td>
          <td><?= htmlspecialchars($b['ISBN']) ?></td>
          <td><?= (int)$b['CopiesTotal'] ?></td>
          <td><?= (int)$b['CopiesAvailable'] ?></td>
          <td><?= htmlspecialchars($b['Category'] ?? '') ?></td>
          <td><?= htmlspecialchars($b['Section'] ?? '') ?></td>
          <td>
            <div class="table-actions">
              <a class="btn btn-secondary" href="books.php?action=edit&id=<?= (int)$b['BookID'] ?>">Edit</a>
              <a class="btn btn-danger"
                 href="books.php?action=delete&id=<?= (int)$b['BookID'] ?>"
                 onclick="return confirm('Delete book?');">Delete</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($action === 'add'): ?>

    <h2>Add Book</h2>
    <p class="small">ISBN must be unique. CopiesAvailable must be <= CopiesTotal.</p>

    <form method="post" action="books.php?action=add">
      <label>BookID</label>
      <input name="BookID" placeholder="Random ID (e.g., 9603)" required>

      <label>Title</label>
      <input name="Title" placeholder="Book title" required>

      <label>Author</label>
      <input name="Author" placeholder="Author">

      <label>Publisher</label>
      <input name="Publisher" placeholder="Publisher">

      <label>Language</label>
      <input name="Language" placeholder="English / Arabic ...">

      <label>Edition</label>
      <input name="Edition" placeholder="1st / 2nd ...">

      <label>ISBN</label>
      <input name="ISBN" placeholder="Must be unique" required>

      <label>CopiesTotal</label>
      <input name="CopiesTotal" type="number" min="0" required>

      <label>CopiesAvailable</label>
      <input name="CopiesAvailable" type="number" min="0" required>

      <label>Category</label>
      <input name="Category" placeholder="Databases / AI ...">

      <label>ShelfLocation</label>
      <input name="ShelfLocation" placeholder="A1 / B2 ...">

      <label>Section</label>
      <input name="Section" placeholder="CS / DS / IT ...">

      <label>RowNumber</label>
      <input name="RowNumber" type="number" min="1" placeholder="1">

      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="books.php">Cancel</a>
    </form>

  <?php elseif ($action === 'edit' && isset($_GET['id'])): ?>

    <?php
      $stmt = $pdo->prepare("SELECT * FROM book WHERE BookID=?");
      $stmt->execute([(int)$_GET['id']]);
      $book = $stmt->fetch();

      if (!$book) {
        echo "<div class='msg'>Book not found.</div>";
        echo "<a class='btn btn-secondary' href='books.php'>Back</a>";
        exit;
      }
    ?>

    <h2>Edit Book</h2>
    <p class="small">ISBN must be unique. CopiesAvailable must be <= CopiesTotal.</p>

    <form method="post" action="books.php?action=edit&id=<?= (int)$book['BookID'] ?>">
      <label>Title</label>
      <input name="Title" value="<?= htmlspecialchars($book['Title']) ?>" required>

      <label>Author</label>
      <input name="Author" value="<?= htmlspecialchars($book['Author'] ?? '') ?>">

      <label>Publisher</label>
      <input name="Publisher" value="<?= htmlspecialchars($book['Publisher'] ?? '') ?>">

      <label>Language</label>
      <input name="Language" value="<?= htmlspecialchars($book['Language'] ?? '') ?>">

      <label>Edition</label>
      <input name="Edition" value="<?= htmlspecialchars($book['Edition'] ?? '') ?>">

      <label>ISBN</label>
      <input name="ISBN" value="<?= htmlspecialchars($book['ISBN']) ?>" required>

      <label>CopiesTotal</label>
      <input name="CopiesTotal" type="number" min="0" value="<?= (int)$book['CopiesTotal'] ?>" required>

      <label>CopiesAvailable</label>
      <input name="CopiesAvailable" type="number" min="0" value="<?= (int)$book['CopiesAvailable'] ?>" required>

      <label>Category</label>
      <input name="Category" value="<?= htmlspecialchars($book['Category'] ?? '') ?>">

      <label>ShelfLocation</label>
      <input name="ShelfLocation" value="<?= htmlspecialchars($book['ShelfLocation'] ?? '') ?>">

      <label>Section</label>
      <input name="Section" value="<?= htmlspecialchars($book['Section'] ?? '') ?>">

      <label>RowNumber</label>
      <input name="RowNumber" type="number" min="1" value="<?= htmlspecialchars($book['RowNumber'] ?? '') ?>">

      <button class="btn btn-primary" type="submit">Update</button>
      <a class="btn btn-secondary" href="books.php">Cancel</a>
    </form>

  <?php else: ?>
    <div class="msg">Invalid action.</div>
    <a class="btn btn-secondary" href="books.php">Back</a>
  <?php endif; ?>

</div>
</body>
</html>
