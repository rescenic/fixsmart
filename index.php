<?php
// index.php — Entry point, redirect ke login atau dashboard
session_start();
require_once 'config.php';
redirect(isLoggedIn() ? APP_URL . '/dashboard.php' : APP_URL . '/login.php');
