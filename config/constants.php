<?php
declare(strict_types=1);

// route prefixes
define('ROUTEPREFIX_ADMIN', 'admin');
define('ROUTEPREFIX_SYSTEM_EVENTS', 'systemEvents');
define('ROUTEPREFIX_ADMINISTRATORS', 'administrators');
define('ROUTEPREFIX_ROLES', 'roles');
define('ROUTEPREFIX_PERMISSIONS', 'permissions');

// authorization resources/permissions, must match permission db records
define('SYSTEM_EVENTS_VIEW_RESOURCE', 'View System Events');

define('LOGIN_ATTEMPTS_VIEW_RESOURCE', 'View Login Attempts');

define('ADMINISTRATORS_VIEW_RESOURCE', 'View Administrators');
define('ADMINISTRATORS_INSERT_RESOURCE', 'Insert Administrators');
define('ADMINISTRATORS_UPDATE_RESOURCE', 'Update Administrators');
define('ADMINISTRATORS_DELETE_RESOURCE', 'Delete Administrators');

define('ROLES_VIEW_RESOURCE', 'View Roles');
define('ROLES_INSERT_RESOURCE', 'Insert Roles');
define('ROLES_UPDATE_RESOURCE', 'Update Roles');
define('ROLES_DELETE_RESOURCE', 'Delete Roles');


// define('PERMISSIONS_VIEW_RESOURCE', 'View Permissions');
// define('PERMISSIONS_INSERT_RESOURCE', 'Insert Permissions');
// define('PERMISSIONS_UPDATE_RESOURCE', 'Update Permissions');
// define('PERMISSIONS_DELETE_RESOURCE', 'Delete Permissions');
define('PERMISSIONS_VIEW_RESOURCE', 'Insert Administrators');
define('PERMISSIONS_INSERT_RESOURCE', 'Insert Administrators');
define('PERMISSIONS_UPDATE_RESOURCE', 'Insert Administrators');
define('PERMISSIONS_DELETE_RESOURCE', 'Insert Administrators');


// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');
define('ROUTE_LOGIN', 'authentication.login');
define('ROUTE_LOGIN_POST', 'authentication.post.login');

// admin routes
define('ROUTE_ADMIN_HOME_DEFAULT', ROUTEPREFIX_ADMIN . '.home');
define('ROUTE_LOGOUT', ROUTEPREFIX_ADMIN . 'authentication.logout');

// login attempts
define('ROUTE_LOGIN_ATTEMPTS', ROUTEPREFIX_ADMIN . '.logins.index');
define('ROUTE_LOGIN_ATTEMPTS_RESET', ROUTEPREFIX_ADMIN . '.logins.index.reset');
// system events
define('ROUTE_SYSTEM_EVENTS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_SYSTEM_EVENTS.'.index');
define('ROUTE_SYSTEM_EVENTS_RESET', ROUTEPREFIX_ADMIN . '.' . ROUTEPREFIX_SYSTEM_EVENTS . '.index.reset');
// administrators
define('ROUTE_ADMINISTRATORS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.index');
define('ROUTE_ADMINISTRATORS_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.index.reset');
define('ROUTE_ADMINISTRATORS_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.insert');
define('ROUTE_ADMINISTRATORS_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.post.insert');
define('ROUTE_ADMINISTRATORS_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.update');
define('ROUTE_ADMINISTRATORS_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.put.update');
define('ROUTE_ADMINISTRATORS_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.delete');
// roles
define('ROUTE_ADMINISTRATORS_ROLES', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.index');
define('ROUTE_ADMINISTRATORS_ROLES_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.index.reset');
define('ROUTE_ADMINISTRATORS_ROLES_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.insert');
define('ROUTE_ADMINISTRATORS_ROLES_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.post.insert');
define('ROUTE_ADMINISTRATORS_ROLES_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.update');
define('ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.put.update');
define('ROUTE_ADMINISTRATORS_ROLES_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.delete');
// permissions
define('ROUTE_ADMINISTRATORS_PERMISSIONS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.index');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.index.reset');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.insert');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.post.insert');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.update');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.put.update');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.delete');
// nav / permission options without routes
define('NAV_ADMIN_SYSTEM', 'systemNav');
