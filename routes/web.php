<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/admin_ui.php';

require __DIR__.'/admin_console.php';

// ORBIT ADMIN UI M4 START
require __DIR__.'/admin_ui_m4.php';
// ORBIT ADMIN UI M4 END

// ORBIT ADMIN UI M5 START
require __DIR__.'/admin_ui_m5.php';
// ORBIT ADMIN UI M5 END
