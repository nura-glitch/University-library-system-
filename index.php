<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>University Library System</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-top:18px; }
    .card { background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:14px; padding:18px; box-shadow:0 6px 20px rgba(0,0,0,.05); }
    .card h3 { margin:0 0 8px; }
    .card p { margin:0 0 12px; color:#444; font-size:14px; }
  </style>
</head>

<body>
  <div class="container">
    <h2>University Library Management System</h2>
    <p class="small">Quick navigation for your project demo.</p>

    <div class="grid">
      <div class="card">
        <h3>Books</h3>
        <p>Manage book inventory (Add/Edit/Delete) + safe delete.</p>
        <a class="btn btn-primary" href="books.php">Open Books</a>
      </div>

      <div class="card">
        <h3>Members</h3>
        <p>Manage members. Total borrowed is calculated from borrowing records.</p>
        <a class="btn btn-primary" href="members.php">Open Members</a>
      </div>

      <div class="card">
        <h3>Staff</h3>
        <p>Manage library staff and shifts/status.</p>
        <a class="btn btn-primary" href="staff.php">Open Staff</a>
      </div>

      <div class="card">
        <h3>Borrowing</h3>
        <p>Borrow/Return books + overdue filter + auto inventory updates.</p>
        <a class="btn btn-primary" href="borrowing.php">Open Borrowing</a>
        <a class="btn btn-danger" style="margin-left:8px" href="borrowing.php?action=overdue">Overdue</a>
      </div>
    </div>
  </div>
</body>
</html>
