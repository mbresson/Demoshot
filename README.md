
========== README =========

Demoshot is a photo sharing website written in PHP and HTML5 and using Twitter Bootstrap for its UI. It is the outcome of a work asked by my PHP teacher.

========= DEPENDENCIES ====

Demoshot needs at least PHP 5.3 and MySQL in order to run flawlessly.

========== INSTALL ========

To install Demoshot, you need to:

* Copy all the files and folders on your server.
* Fill Demoshot.ini [database] fields with the information needed to connect to your database.
* Import the SQL script res/others/database.sql in your database.

To turn a user into an admin, you need to change his type from 0 to 1 in table "user".
