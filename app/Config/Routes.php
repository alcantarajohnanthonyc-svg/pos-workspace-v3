<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/// ============================================================
/// HOME / LANDING
/// ============================================================
$routes->get('/', 'Home::index');

/// ============================================================
/// AUTH MODULE  (login, Google SSO, session, logout)
/// ============================================================
$routes->group('auth', static function ($routes) {
    /// Login page + form
    $routes->get('login', 'Auth::login');                       // auth/login
    $routes->post('loginProcess', 'Auth::loginProcess');        // auth/loginProcess

    /// Google Workspace SSO
    $routes->get('google', 'Auth::google');                     // auth/google
    $routes->get('googleCallback', 'Auth::googleCallback');     // auth/googleCallback

    /// Logout
    $routes->get('logout', 'Auth::logout');                     // auth/logout

    /// Heartbeat (every 30 s from layouts/main.php): is this session still the one that owns the account?
    $routes->get('check_token', 'Auth::check_token');           // auth/check_token

    /// Live online / offline status for the account list (polled by accounts list page)
    $routes->get('live_status', 'Auth::live_status');           // auth/live_status?ids=1,2,3
});

/// Google OAuth redirect URI registered in Google Console (keep this path)
$routes->get('login/google/callback', 'Auth::googleCallback');

/// Single-session handling (shown when the account is already logged in elsewhere)
$routes->post('login/session/replace', 'Auth::sessionReplace');
$routes->post('login/session/cancel', 'Auth::sessionCancel');

/// ============================================================
/// DASHBOARD MODULE
/// ============================================================
$routes->get('dashboard', 'Dashboard::index');

/// ============================================================
/// ACCOUNT MANAGEMENT MODULE
/// ============================================================
$routes->group('accounts', static function ($routes) {
    /// List + configure pages (GET)
    $routes->get('/', 'AccountManagement::index');                          // accounts
    $routes->get('configure/(:num)', 'AccountManagement::configure/$1');    // accounts/configure/2

    /// Add Account modal (POST)
    $routes->post('create', 'AccountManagement::createAccount');            // accounts/create

    /// Configure page actions (POST, each one is a modal / form on the page)
    $routes->post('(:num)/profile', 'AccountManagement::updateProfile/$1');     // Edit profile modal
    $routes->post('(:num)/password', 'AccountManagement::changePassword/$1');   // Change password modal
    $routes->post('(:num)/status', 'AccountManagement::setStatus/$1');          // Change account status modal
    $routes->post('(:num)/modules', 'AccountManagement::saveModuleAccess/$1');  // Module Access tab (save)
    $routes->post('(:num)/scope', 'AccountManagement::saveBranchScope/$1');     // Save user company/branch scope
});

/// ============================================================
/// MODULE SETTINGS MODULE  (rbac_modules + their permissions)
/// ============================================================
$routes->group('modules', static function ($routes) {
    /// Pages (GET)
    $routes->get('/', 'ModuleSettings::index');                             // modules
    $routes->get('configure/(:num)', 'ModuleSettings::configure/$1');       // modules/configure/3

    /// Actions (POST)
    $routes->post('create', 'ModuleSettings::create');                            // Add Module
    $routes->post('(:num)/details', 'ModuleSettings::updateDetails/$1');          // Edit details modal
    $routes->post('(:num)/scope', 'ModuleSettings::saveScope/$1');                // Company/Branch scope settings
    $routes->post('(:num)/status', 'ModuleSettings::setStatus/$1');                // Enable / disable (all users)
    $routes->post('(:num)/permissions', 'ModuleSettings::addPermissions/$1');     // Add Permission
    $routes->post('(:num)/permissions/(:num)/remove', 'ModuleSettings::removePermission/$1/$2');
    $routes->post('(:num)/admins', 'ModuleSettings::saveAdmins/$1');              // Save administrators + approval settings
    $routes->post('(:num)/notifications', 'ModuleNotifications::save/$1');        // Notifications tab (In-App / Email per event)
    $routes->post('(:num)/statuses', 'ModuleStatusesController::save/$1');        // Statuses tab (name, colour, order, active)
});

$routes->group('roles', static function ($routes) {
    $routes->get('/', 'RoleManagement::index');                     // roles
    $routes->get('create', 'RoleManagement::configure');            // roles/create
    $routes->get('configure/(:num)', 'RoleManagement::configure/$1'); // roles/configure/2
    $routes->post('save', 'RoleManagement::save');                  // roles/save (new)
    $routes->post('save/(:num)', 'RoleManagement::save/$1');          // roles/save/2 (update)
});

// Company Routes
$routes->group('company', static function ($routes) {
    $routes->get('', 'CompanyController::index');
    $routes->post('store', 'CompanyController::store');
    $routes->post('update/(:num)', 'CompanyController::update/$1');
    $routes->post('delete/(:num)', 'CompanyController::delete/$1');
});

// Branch Routes
$routes->group('branch', static function ($routes) {
    $routes->get('', 'BranchController::index');
    $routes->post('store', 'BranchController::store');
    $routes->post('update/(:num)', 'BranchController::update/$1');
    $routes->post('delete/(:num)', 'BranchController::delete/$1');
});

// Store Details
$routes->group('store-details', static function ($routes) {
    $routes->get('/', 'StoreDetails::index');                          // store-details
    $routes->get('export', 'StoreDetails::export');                    // store-details/export (?template=1 = import template)
    $routes->post('create', 'StoreDetails::create');                   // Add Store
    $routes->post('import/check', 'StoreDetails::importCheck');        // Import step 1: check the lines (the import dialog calls this URL)
    $routes->post('import/commit', 'StoreDetails::importCommit');      // Import step 2: save the valid lines
    $routes->post('import/submit', 'StoreDetails::importSubmit');      // Import when "Requires approval" is ON: send ONE request
    $routes->post('bulk/update', 'StoreDetails::bulkUpdate');          // checkboxes: change status / ownership of many stores
    $routes->post('bulk/delete', 'StoreDetails::bulkDelete');          // checkboxes: delete many stores
    $routes->post('(:num)/update', 'StoreDetails::update/$1');         // Edit Store
    $routes->post('(:num)/delete', 'StoreDetails::delete/$1');         // Delete Store
});

// Approval Queue
$routes->group('approval-queue', static function ($routes) {
    $routes->get('/', 'ApprovalQueue::index');                         // approval-queue
    $routes->get('details/(:num)', 'ApprovalQueue::details/$1');       // side drawer (HTML fragment)
    $routes->get('show/(:num)', 'ApprovalQueue::show/$1');             // View Full Details page
    $routes->post('approve/(:num)', 'ApprovalQueue::approve/$1');      // Approve (JSON)
    $routes->post('reject/(:num)', 'ApprovalQueue::reject/$1');        // Reject (JSON, note required)
    $routes->post('bulk-approve', 'ApprovalQueue::bulkApprove');       // Approve selected (JSON)
});

// Notifications (the bell in the header)
$routes->group('notifications', static function ($routes) {
    $routes->get('latest', 'Notifications::latest');              // JSON: latest unread + count (silent)
    $routes->get('open/(:num)', 'Notifications::open/$1');        // mark as read, then go to its page
    $routes->post('read-all', 'Notifications::readAll');          // Mark all as read
});
