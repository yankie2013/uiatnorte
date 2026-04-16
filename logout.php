<?php
require __DIR__ . '/auth.php';

use App\Support\Auth;

Auth::logout();
header('Location: login.php');
exit;
