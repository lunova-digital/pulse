<?php

use App\Livewire\Archived;
use App\Livewire\Auth\Login;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientShow;
use App\Livewire\Daily;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Livewire\Tasks;
use App\Livewire\Team;
use App\Livewire\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// There is intentionally no registration route: the owner adds people on the Team screen.
Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::middleware(['auth', 'active'])->group(function () {
    // Team members land on Daily; client-portal users go straight to their project.
    Route::get('/', function (Request $request) {
        $user = $request->user();

        if ($user->isClient()) {
            $project = $user->projects()->orderBy('projects.id')->first();

            abort_if(! $project, 403, 'No project has been shared with you yet.');

            return redirect()->route('projects.show', $project);
        }

        return redirect()->route('daily');
    });

    // Item access is enforced by ProjectPolicy inside the component.
    Route::get('/projects/{project}', ProjectShow::class)->name('projects.show');

    Route::middleware('can:staff')->group(function () {
        Route::get('/daily', Daily::class)->name('daily');
        Route::get('/today', Today::class)->name('today');
        Route::get('/projects', ProjectsIndex::class)->name('projects.index')->defaults('kind', 'project');
        Route::get('/products', ProjectsIndex::class)->name('products.index')->defaults('kind', 'product');
        Route::get('/archived', Archived::class)->name('archived');
        Route::get('/tasks', Tasks::class)->name('tasks');
    });

    Route::middleware('can:manage-clients')->group(function () {
        Route::get('/clients', ClientsIndex::class)->name('clients.index');
        Route::get('/clients/{client}', ClientShow::class)->name('clients.show');
    });

    Route::get('/team', Team::class)->middleware('can:manage-team')->name('team');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

// Note: no delete routes exist for projects, clients or any history.
