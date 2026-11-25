OLIVERA HOME DEVELOPERS – INTEGRATED WEBSITE

WHAT THIS PACKAGE DOES
- Uses the designed public-facing front end as index.php.
- Keeps the existing PHP/MySQL CRM and admin panel in the same project.
- Uses the backend PDO connection to mywebsite_db; the front-end db.php is intentionally not copied.
- Public contact, callback, site-visit request and partnership forms create CRM leads.
- Customer enquiry forms retain the existing two-step customer/property enquiry flow and its lead creation.
- The public gallery reads only photos marked Public in the existing gallery_photos table and uses the secure image endpoint.
- The header/footer links adapt to both /mywebsite/ on XAMPP and a domain-root deployment.

INSTALLATION (KEEP A BACKUP FIRST)
1. Make a full backup of your existing C:\xampp\htdocs\mywebsite folder.
2. Extract this package to a NEW folder first, for example C:\xampp\htdocs\mywebsite_integrated.
3. Copy your existing database settings into config/database.php if they differ. This project expects the existing mywebsite_db database.
4. Keep your existing database tables and records. Do not import or create a second database from the front-end ZIP.
5. Confirm the existing gallery uploads folder and uploads/.htaccess are present. Keep private gallery photos out of public static folders.
6. Open http://localhost/mywebsite_integrated/ after Apache and MySQL are running. Admin remains at /admin/login.php.
7. Once the integrated copy is reviewed, you can move it into your original project folder. Do not merge over your live working folder without a backup.

IMPORTANT SOURCE-ARCHIVE GAPS
- The supplied front-end ZIP did not include its referenced dedicated CSS files for individual location/project pages. Compatible shared fallback CSS files are included so these references no longer 404; they may not reproduce the original missing page-specific styling exactly.
- Several referenced background videos were absent from the front-end ZIP. The homepage and Our Projects hero use image/gradient fallbacks; other video sections have local poster-image fallbacks so they do not appear as broken media.
- blog.php and project-details.php were referenced by the front-end but were not supplied. The dead Blog link was removed/replaced; project links use the supplied dedicated location pages.

DATA HANDLING
- Public callback/site-visit/partnership forms create unassigned leads with priority Medium and status New.
- A public site-visit request is a request only; an employee must confirm and schedule it in the CRM.
- When a normal customer enquiry supplies an email, the customer record is created or updated while any existing password hash is preserved.
- Partnership enquiries are recorded as CRM leads and do not create customer login accounts.

Before using on a live domain, update config/database.php and config/mail.php with hosting credentials, enable HTTPS, and review privacy/consent wording.
