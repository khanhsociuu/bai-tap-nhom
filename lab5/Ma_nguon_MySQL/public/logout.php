<?php
require __DIR__.'/../src/Web.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit;}
verifyCsrf();$_SESSION=[];session_regenerate_id(true);gotoPath('/login.php');
