# Job Admin Website

This is a simple admin website to manage job data (input, edit, delete) using PHP and MySQL. Designed to run on XAMPP.

## Setup Instructions

1. **Start XAMPP**
   - Launch XAMPP and start the Apache and MySQL modules.

2. **Database Setup**
   - Open phpMyAdmin (usually at http://localhost/phpmyadmin).
   - Create a new database named `job_admin`.
   - Run the following SQL to create the `jobs` table:

     ```sql
     CREATE TABLE `jobs` (
       `id` INT AUTO_INCREMENT PRIMARY KEY,
       `title` VARCHAR(255) NOT NULL,
       `description` TEXT NOT NULL,
       `location` VARCHAR(255) NOT NULL,
       `salary` DECIMAL(10,2) NOT NULL,
       `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     );
     ```

3. **Project Files**
   - Place all project files (`index.html`, `style.css`, `script.js`, `db.php`, `jobs.php`) in a folder inside `htdocs` (e.g., `htdocs/PASKER`).

4. **Access the Website**
   - Open your browser and go to `http://localhost/PASKER/index.html`.

## Files
- `index.html`: Admin dashboard UI
- `style.css`: Basic styling
- `script.js`: Frontend logic (AJAX)
- `db.php`: Database connection
- `jobs.php`: Backend API for CRUD operations

---

You can now manage job postings from the admin dashboard! 