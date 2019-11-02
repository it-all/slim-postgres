I'm shutting this down. It was a great exercise in understanding php, pg, slim & more, but it didn't end up being a necessary component for any system. i have improved it somewhat off-repo and am using that in an ecommerce project, but it's expected lifetime is short. I recommend looking at the following for better projects with similar aims. You'll have to leave PHP, which you should, and so should i.  

https://activeadmin.info/  
https://github.com/smpallen99/ex_admin

# Slim-Postgres  
Slim-Postgres is a <a target="_blank" href="https://www.php.net">PHP</a> framework based on <a target="_blank" href="https://www.slimframework.com/">Slim Micro-Framework</a> and <a target="_blank" href="https://www.postgresql.org/">PostgreSQL</a>. It has a built-in administrative interface and other tools to allow rapid web app development.  
    
INSTALLATION  
composer create-project it-all/slim-postgres your-app-name 1.*  
Set write permissions on /storage ie chmod -R 777 storage/  
<a href="#createdb">Create your PostgreSQL database</a> and <a href="#restoredb">restore</a> /storage/dumps/pg_schema.dump and /storage/dumps/pg_data.dump to it  
Create a website with /public as the home directory  
Copy/rename .env.example to .env then edit .env  
Navigate to your site to see the default homepage. If there are no errors then you've succesfully connected to the database.  
Edit then run cliScripts/insertTopAdministrator.php  
Preempt 'object not found error' with composer dump-autoload -o  
Navigate to your site /private (the admin directory defined in settings['adminPath']) and login!  
  
FEATURES  
<a target="_blank" href="https://www.php.net">PHP</a> 7.1+  
Built on <a target="_blank" href="https://slimframework.com">Slim framework</a>, a front-controller micro-framework for PHP  
<a target="_blank" href="https://postgresql.org">PostgreSQL Database</a> Integration  
<a target="_blank" href="#admin">Administrative User Interface and Navigation</a>  
<a href="#authe">Authentication</a> (Log In/Out)  
<a href="#autho">Authorization</a> (Role Based Access Control for Resource and Functionality Access)   
<a href="#se">Built-in Database Logging/Reporting of system events, login attempts, and errors</a>  
<a href="#eh">Error Handling</a>  
<a href="emailing">Emailing</a> with <a target="_blank" href="https://github.com/PHPMailer/PHPMailer">PHPMailer</a>    
<a href="#csrf">CSRF Checking</a>  
<a href="https://github.com/slimphp/PHP-View">Slim's PHP-View Templates</a>  
HTML Form Generation using <a target="_blank" href="https://github.com/it-all/FormFormer">FormFormer</a>   
Data Validation with <a target="_blank" href="https://github.com/vlucas/valitron">Valitron</a> (NOTE: If you are comparing floating-point numbers with min/max validators, you should install the PHP <a target="_blank" href="http://php.net/manual/en/book.bc.php">BCMath extension</a> for greater accuracy and reliability. The extension is not required for Valitron to work, but Valitron will use it if available, and it is highly recommended.)  
<a href="#xss">XSS Prevention</a>  
<a href="https://github.com/vlucas/phpdotenv">PHP dotenv</a> for configuring server environments  
<a href="#errlog">PHP Error Logging with Stack Trace</a> for debugging  
  
CODING NEW FUNCTIONALITY  
*work in progress*  
Create a new directory under domain and create a Model/View/Controller (or whatever code structure you desire) as necessary. You can model these files after existing functionality such as SlimPostgres/Administrators/Roles (single database table model) or SlimPostgres/Administrators (joined database tables).  
Add and configure your new route to the system by:  
- Adding a new route name constant in config/constants.php  
- Adding the new route in config/routes.php  
- For new administrative resources, add AuthenticationMiddleware to the route (see existing examples in the routes file)  
- For new administrative resources, if authorization is required at a resource or functionality level, add them to the resources / permissions section in config/constants.php, then add AuthorizationMiddleware to the route (see existing examples in the routes file)   
- For new administrative resources, you can add a link in the administrative navigation menu by editing SlimPostgres/AdminNavigation.php, or config/settings.php ['adminNav']. 

<a id="admin">Administrative Interface and Navigation</a>  
Upon browsing to the administrative directory set in $config['adminPath'] authenticating, the appropriate resource is loaded based on $config['slim']['authentication']['administratorHomeRoutes'], if found for the administrator, otherwise from ROUTE_ADMIN_HOME_DEFAULT in constants.php.  
  
The following administrative functionalities are already coded:  
- View System Events
- View Login Attempts
- View, Create, Edit, Delete Administrators (with role assignment)  
- View, Create, Edit, Delete Roles  
- View, Create, Edit, Delete Permissions (with role assignment)  
- Logout  
These options are found in the navigation menu at top left. Once other options are coded, they can be added to the menu by uncommenting/adding to $config['slim']['adminNav'].  

<a id="authe">Authentication</a>  
Administrative resources can require authentication (login) to access. See config/routes.php admin home for adding Authentication Middleware to a route.  

<a id="autho">Authorization</a>  
Administrative resources and functionality can be protected against unauthorized use based on role based administrative permissions. Permissions to access system resources/functionality can be inserted through the administrative interface, then assigned to one or more roles. Administrators are also assigned roles, which then grants them the permissions assigned to those roles. Authorization checks are done by a simple AuthorizationService::isAuthorized(string $resource): bool call, where the $resource string must match the permission title that has been inserted. Checks are also performed in AdminNavigation to determine whether or not to display navigation options. Authorization failures result in alerts being written to the SystemEvents table and the user redirected to the admin homepage with a red alert message displayed. 
  
<a id="se">System Event Database Logging</a>  
Certain events such as logging in, logging out, inserting, updating, and deleting certain database records or business entities are automatically logged into the system_events table, which is viewable in the admin interface. You can choose other events to insert as you write your application. For usage examples and help, search "systemEvents->insert" and see SystemEventsTableMapper.php.  
  
PHP errors are also logged to the system_events table by default (this can be turned off in $config['errors']['logToDatabase']). Note that php errors such as Notices and Warnings that occur during a transaction do not get logged to the database, as the transaction never gets commited, but Exceptions will be logged to the database, as they are caught inside the transaction, then the transaction is rolled back then the exception is re-thrown. Please search pg_query("BEGIN"); for examples.   

<a id="eh">Error Handling</a>  

Slim's built in error handling has been disabled, and custom error handling implemented, in order to handle any errors encountered prior to running the Slim application, as well as to be able to email an administrator that an error occured, and to log the error to the system_events database table, which is viewable in list form in the administrative interface.  

Reporting Methods:

1. Database Log  
    If the database and system events services have been set as properties in the ErrorHandler class, all errors are logged to the SystemEvents table. The stack trace is not provided, instead, a reference is made to view the log file for complete details. See <a href="#se">System Event Database Logging</a> for more information.  
    
2. File Log  
    All error details are logged to $config['storage']['logs']['pathPhpErrors'].

3. Echo  
    Live Sites*
    Error details are never echoed, rather, a generic error message is echoed. For fatal errors, this message is set in $config['errors']['fatalMessage'].

    Dev Sites*
    Error details are echoed unless the environmental variable ERRORS_ECHO_DEV is set to 0.  
    
4. Email  
    For security, error details are never emailed.

    Live Sites*
    All errors cause generic error notifications to be emailed to $config['errors']['emailTo'].
    
    Dev Sites*
    Generic error notifications are emailed to $config['errors']['emailTo'] if the environmental variable ERRORS_EMAIL_DEV is set to 1.  
    
* The current site is considered a production (live) site unless the environmental variable IS_LIVE is set to 0.  
  
See ErrorHandler.php for further info.  
  
<a id="emailing">Emailing with phpMailer</a>  
Verify mailer service exists (it may not on dev servers, depending on $config['sendEmailsOnDevServer']).  
// magic method to access mailer inside container  
if ($this->mailer !== null) {  
&nbsp;&nbsp;&nbsp;&nbsp;$this->mailer->send(...)  
}  
                
<a id="csrf">CSRF</a>   
The <a href="https://github.com/slimphp/Slim-Csrf" target="_blank">Slim Framework CSRF</a> protection middleware is used to check CSRF form fields. The CSRF key/value generators are added to the container for form field creation. They are also made available to Twig. A failure is logged to SystemEvents as an error, the user's session is unset, and the user is redirected to the (frontend) homepage with an error message.  
  
<a id="xss">XSS Prevention</a>  
<a href="http://us2.php.net/htmlentities" target="_blank">htmlentities()</a> is used to escape output.  
  
<a id="errLog">PHP Error Log</a>  
PHP Errors with stack trace are logged to the file set in config['storage']['errors']['phpErrorLogPath']  
   
<a id="createDb">Create PostgreSQL Database (One Method)</a>  
* $ psql -U postgres (note you may have to edit your pg_hba.conf file to allow local md5 or trust login https://stackoverflow.com/questions/45632463/peer-authentication-failed-for-user-in-postgresql)  
* postgres=# create role myrolename with login; (creating the role with the same name as the database name allows easy psql access)  
* postgres=# alter role myrolename with encrypted password 'mypassword';  
* postgres=# create database mydbname with owner myrolename;  
  
<a id="restoreDb">Import pg_schema.sql and pg_data.sql (from project root dir)</a>
* pg_restore -U myrolename -O -c --if-exists -n public -d mydbname storage/dumps/pg_schema_initial.dump
* pg_restore -U myrolename -O -n public -d mydbname storage/dumps/pg_data_initial.dump
  
CODE DOCUMENTATION  
Entities  
Entities are like business objects at the core of the system. The current entities are Administrators, Roles, Permissions, and Events. Domain business object, i.e. Orders in an ecommerce system, will go in the domain directory of <a href="https://github.com/it-all/slim-postgres-skeleton">Slim-Postgres-Skeleton</a>.  
  
Database Mappers  
Mappers are where all PostgreSQL database queries should occur. In fact, where all database function calls (pg_*) should occur.  There are two types of mappers: table mappers and entity mappers. 
  
Table Mappers  
Table mappers consist mainly of select, insert, update and delete functions for a single database table. Select functions return an array of record arrays (using pg_fetch_all) or a single record array, or null if no records match the query. Insert functions return the primary key value inserted. Update and delete functions have no return value. All failed queries result in an exception being thrown.  

Entity Mappers  
Entity mappers are like table mappers for more complex entities requiring two or more database tables. For example, every administrator must have one or more assigned roles, so inserting an administrator is done using an entity mapper rather than a table mapper. The entity mapper will be responsible for inserting both the administrator roles and the administrator, using a transaction. It can call table mappers to perform the single table queries. Entity mappers can also return entity objects, i.e. an Administrator or an array of Administrators.  

===========================================================>Thank you.
