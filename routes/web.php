<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Open while the UI runs on mock data; goes back behind auth once real data arrives.
Route::inertia('dashboard', 'dashboard')->name('dashboard');
Route::inertia('leads', 'leads/index')->name('leads.index');
Route::inertia('scrape', 'scrape/index')->name('scrape.index');

require __DIR__.'/settings.php';
