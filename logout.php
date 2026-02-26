<?php
session_start();
require_once 'config.php';
session_unset();
session_destroy();
redirect(APP_URL . '/login.php?msg=logout');
