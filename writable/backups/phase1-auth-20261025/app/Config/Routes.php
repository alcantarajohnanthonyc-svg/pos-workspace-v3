
<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('login', 'Login::index');
$routes->get('login/google', 'Login::google');
$routes->get('login/google/callback', 'Login::googleCallback');
$routes->post('login/credentials', 'Login::credentials', ['filter' => 'csrf']);
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);
$routes->post('logout', 'Login::logout', ['filter' => ['auth', 'csrf']]);
