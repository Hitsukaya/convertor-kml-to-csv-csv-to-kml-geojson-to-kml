<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\KmlUploader;
use App\Livewire\MultiKmlUploader;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/split-geojson-strata', function () {
    return view('split-geojson-strata');
})->name('split-geojson-strata');
