<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/admin_ui.php';

require __DIR__.'/admin_ui_m2.php';
