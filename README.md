# University-library-system-
University Library Management System

Project Overview This project implements a University Library Management System using MySQL and PHP. The system is designed to manage books, library members, staff, and borrowing activities. It allows tracking book inventory, borrowing and returning processes, overdue status, and fines, with a web interface that supports basic CRUD operations.

Project Features

Manage Books (Add, Edit, Delete with data integrity constraints)
Manage Members (Students, Teachers, External users)
Manage Staff (Shifts and employment status)
Manage Borrowing transactions (Borrow, Return, Overdue tracking)
View borrowing summary linking members with borrowed books
Execute predefined SQL queries for reporting and analysis
Technologies Used

PHP (PDO)
MySQL
HTML & CSS
XAMPP (Apache and MySQL)
phpMyAdmin
Database Design The database follows Third Normal Form (3NF) principles. It includes the following main tables:

book
member
staff
borrowing
Primary keys and foreign keys are used to enforce relationships and maintain data integrity. The ER Diagram and Relational Schema are provided as separate files.

Project Structure library/

config.php
index.php
books.php
members.php
staff.php
borrowing.php
member_books.php
style.css
university_library.sql
sql_queries.sql
README.md
Setup Instructions

Install XAMPP.
Start Apache and MySQL services.
Open phpMyAdmin.
Import the file university_library.sql.
Make sure the database name is universitylibrary.
If needed, update database credentials in config.php.
Running the Project

Place the project folder inside C:\xampp\htdocs\
Open a browser and go to: http://localhost/library/index.php
Use the main page to navigate through the system modules.
SQL Queries The file sql_queries.sql contains at least 10 SQL queries, including:

SELECT queries
JOIN queries
Aggregate functions (COUNT, SUM)
UPDATE and DELETE queries
Queries to identify overdue borrowings
All queries were tested using the provided sample data and return valid results.

Notes

Foreign key constraints are enforced to maintain data consistency.
Books that are referenced in borrowing records cannot be deleted.
Sample data (minimum 30 records per table) is included.
Author Database Systems Project University Library Management System
