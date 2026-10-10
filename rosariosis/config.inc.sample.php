<?php
/**
 * The base configurations of RosarioSIS
 *
 * You can find more information in the INSTALL.md file
 *
 * @package RosarioSIS
 */

/**
 * Database Settings
 *
 * You can get this info from your web host
 */

// Database type: postgresql or mysql.
$DatabaseType = 'postgresql';

// Database server hostname: use localhost if on same server.
$DatabaseServer = 'localhost';

// Database username.
$DatabaseUsername = 'username_here';

// Database password.
$DatabasePassword = 'password_here';

// Database name.
$DatabaseName = 'database_name_here';


/**
 * Full path to wkhtmltopdf binary file
 *
 * An empty string means wkhtmltopdf will not be called unless
 * WKHTMLTOPDF_PATH is provided as an environment variable.
 * Without either setting, reports are rendered in HTML instead of PDF
 *
 * @link http://wkhtmltopdf.org
 *
 * @example /usr/local/bin/wkhtmltopdf
 * @example C:/Progra~1/wkhtmltopdf/bin/wkhtmltopdf.exe
 */
$wkhtmltopdfPath = '';


/**
 * Default school year
 *
 * Do NOT change during installation
 * Change after rollover
 * Should match the database to be able to login
 *
 * @see School > Rollover program
 */
$DefaultSyear = '2026';


/**
 * Email address to receive notifications
 * - new administrator account
 * - new student / user account
 * - new registration
 *
 * Leave empty to not receive email notifications
 */
$RosarioNotifyAddress = '';


/**
 * Email address to receive errors
 * - PHP fatal error
 * - database SQL error
 * - hacking attempts
 * - CSP violation reports
 *
 * Leave empty to not receive errors
 */
$RosarioErrorsAddress = '';

/**
 * Abugida SIS SMTP email settings.
 *
 * Gmail requires an App Password when 2-Step Verification is enabled.
 * Never commit a real App Password to Git.
 */
$AbugidaMailHost = 'smtp.gmail.com';
$AbugidaMailPort = 587;
$AbugidaMailUsername = 'robelalemu2111@gmail.com';
$AbugidaMailPassword = '';
$AbugidaMailFrom = 'robelalemu2111@gmail.com';
$AbugidaMailFromName = 'Abugida SIS';
$AbugidaMailEncryption = 'tls';


/**
 * Locales
 *
 * Add other languages you want to support here
 *
 * @see locale/ folder
 *
 * For American, French and Spanish:
 *
 * @example [ 'en_US.utf8', 'fr_FR.utf8', 'es_ES.utf8' ];
 */
$RosarioLocales = [ 'en_US.utf8' ];
