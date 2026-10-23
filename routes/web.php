<?php

use App\Livewire\Archived;
use App\Livewire\Auth\Login;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Livewire\Tasks;
use App\Livewire\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Single-user app: there is intentionally no registration route.
Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/today');

    Route::get('/today', Today::class)->name('today');
    Route::get('/projects', ProjectsIndex::class)->name('projects.index');
    Route::get('/projects/{project}', ProjectShow::class)->name('projects.show');
    Route::get('/archived', Archived::class)->name('archived');
    Route::get('/tasks', Tasks::class)->name('tasks');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

// Note: no delete routes exist for projects or timeline entries.
