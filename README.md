Dynamic Personal Portfolio
A modern, database-driven personal portfolio website built with PHP, MySQL, and JavaScript. This project showcases skills, experience, education, and certifications, managed through a simple admin panel. It also includes features like visitor logging with geolocation and an interactive AI chatbot powered by the Gemini API to answer visitor questions.

✨ Key Features
🌐 Frontend (index.php, data_fetcher.php)
Dynamic Content: All portfolio sections (Profile, Experience, Education, Skills, Certifications, Languages, Resume) are loaded dynamically from a MySQL database [cite: Portfolio/data_fetcher.php, Portfolio/index.php].
Single Page Application (SPA) Feel: While not a true SPA, it uses PHP to conditionally load content into one main file, simulating a multi-page site without full page reloads for navigation [cite: Portfolio/index.php].
Configurable Sections: Admin can toggle the visibility of different portfolio sections via the admin panel [cite: Portfolio/admin/admin.php, Portfolio/data_fetcher.php].
Resume Download: Provides a direct download link for the latest uploaded resume [cite: Portfolio/index.php].
Certificate Viewer: Displays certifications and allows users to view them in a modal popup [cite: Portfolio/index.php].
Visitor Logging: Logs visitor IP addresses and approximate geolocation (city, region, country) once per day for basic analytics [cite: Portfolio/data_fetcher.php].
AI Chatbot: Includes an interactive chatbot that answers user questions based on portfolio content fetched dynamically from the database, powered by the Gemini API via a secure backend endpoint [cite: Portfolio/js/ai.js, Portfolio/api/chat.php].
Responsive Design: Styled with a modern, dark-themed CSS to adapt to various screen sizes [cite: Portfolio/css/style.css].
Animated Background: Uses particles.js for an interactive, animated background effect [cite: Portfolio/js/particles-config.js].
🔒 Admin Panel (admin/admin.php)
Secure Login: A simple but effective authentication system protects the admin panel [cite: Portfolio/admin/admin.php].
CSRF Protection: Uses tokens to prevent Cross-Site Request Forgery on all form submissions [cite: Portfolio/admin/admin.php].
Full Content Management (CRUD): Allows the admin to Create, Read, Update, and Delete content for all portfolio sections.
Drag-and-Drop Reordering: Admins can easily reorder certifications using a drag-and-drop interface [cite: Portfolio/admin/admin.php].
File Uploads: Securely handles uploads for the resume (PDF) and certificate images/PDFs [cite: Portfolio/admin/admin.php].
Visitor Log Viewer: Displays the 50 most recent visitor logs directly in the dashboard [cite: Portfolio/admin/admin.php].
Dashboard Overview: Provides at-a-glance statistics like total certifications, years of experience, latest visitor location, and resume status [cite: Portfolio/admin/admin.php].
Modern UI: Built with Tailwind CSS and Lucide icons for a clean, futuristic control center interface [cite: Portfolio/admin/admin.php].
💻 Technology Stack
Backend: PHP
Database: MySQL/MariaDB
Database Connection: MySQLi
Frontend: HTML5, CSS3, JavaScript (ES6+)
Styling: Custom CSS for the frontend, Tailwind CSS for the admin panel.
JavaScript Libraries: Particles.js, SortableJS, Lucide Icons.
AI: Google Gemini API (via PHP cURL).
Server: Apache (implied by the use of .htaccess).
License: Apache License 2.0 [cite: Portfolio/LICENSE].
🚀 Setup and Installation
Clone the Repository:
git clone https://github.com/sc257534/Portfolio.git
cd Portfolio

Database Setup:
Create a new MySQL database (e.g., portfolio).
Import the portfolio.sql file into your database. This will create all the necessary tables [cite: Portfolio/portfolio.sql].
Update your database credentials (server, username, password, database name) in admin/db_config.php [cite: Portfolio/admin/db_config.php].
AI Chatbot API Key:
Obtain an API key for the Google Gemini API.
Crucially, replace the placeholder 'Your Gemini Api Key' in api/chat.php with your actual, secret API key [cite: Portfolio/api/chat.php]. Do not commit your real API key to a public repository.
Admin Credentials:
The default admin credentials are hardcoded in admin/admin.php (username: admin, password: Admin123) [cite: Portfolio/admin/admin.php]. It is highly recommended to change these for security.
Configure Web Server:
Ensure your web server (e.g., Apache) is running with PHP and MySQL support.
Point your server's document root to the Portfolio directory.
Ensure the server has write permissions for the resume/ and certificates/ directories to allow file uploads from the admin panel.
Run the Application:
Access the main site (e.g., http://localhost/) in your browser.
Access the admin panel via /admin/admin.php (e.g., http://localhost/admin/admin.php).
📁 File Structure
.
├── LICENSE
├── portfolio.sql
├── README.md
│
├── admin/
│   ├── admin.php
│   ├── db_config.php
│   └── logout.php
│
├── api/
│   └── chat.php
│
├── certificates/
│   └── certificate.jpg.bmp
│
├── css/
│   └── style.css
│
├── images/
│   ├── logo.png
│   ├── pdf-icon.png.png
│   └── profile.jpg.bmp
│
├── js/
│   ├── ai.js
│   ├── particles-config.js
│   ├── presentation.js
│   └── script.js
│
├── data_fetcher.php
├── index.php
└── logout.php
