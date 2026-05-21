<?php
session_start();
session_destroy();
header('Location: /accident/web/dashboard/auth/login.php');
exit;
