<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
});

it('can view main posting page with root folders', function () {
    // Create some root folders (level 0)
    $rootFolder1 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $rootFolder2 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 2,
    ]);

    // Create a nested folder (should not appear on main page)
    $nestedFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $rootFolder1->id,
        'order' => 1,
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/index')
        ->has('rootFolders', 2)
        ->has('rootFolders.0', fn ($folder) => $folder
            ->where('id', $rootFolder1->id)
            ->where('name', $rootFolder1->name)
            ->etc()
        )
        ->has('rootFolders.1', fn ($folder) => $folder
            ->where('id', $rootFolder2->id)
            ->where('name', $rootFolder2->name)
            ->etc()
        )
    );
});

it('excludes deleted folders from main page', function () {
    $activeFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $deletedFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 2,
        'deleted_at' => now(),
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/index')
        ->has('rootFolders', 1)
        ->has('rootFolders.0', fn ($folder) => $folder
            ->where('id', $activeFolder->id)
            ->etc()
        )
    );
});

it('orders root folders by order field', function () {
    $folderC = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 3,
        'name' => 'Folder C',
    ]);

    $folderA = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
        'name' => 'Folder A',
    ]);

    $folderB = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 2,
        'name' => 'Folder B',
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/index')
        ->has('rootFolders', 3)
        ->has('rootFolders.0', fn ($folder) => $folder
            ->where('name', 'Folder A')
            ->etc()
        )
        ->has('rootFolders.1', fn ($folder) => $folder
            ->where('name', 'Folder B')
            ->etc()
        )
        ->has('rootFolders.2', fn ($folder) => $folder
            ->where('name', 'Folder C')
            ->etc()
        )
    );
});

it('can view specific folder with descendants', function () {
    $parentFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $childFolder1 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $parentFolder->id,
        'order' => 1,
    ]);

    $childFolder2 = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $parentFolder->id,
        'order' => 2,
    ]);

    $grandchildFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $childFolder1->id,
        'order' => 1,
    ]);

    // Create files in the parent folder
    $file1 = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $file2 = File::factory()->create(['workspace_id' => $this->workspace->id]);

    $parentFolder->files()->attach([$file1->id, $file2->id]);

    $response = $this->get(route('browse', $parentFolder));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/show')
        ->has('folder', fn ($folder) => $folder
            ->where('id', $parentFolder->id)
            ->where('name', $parentFolder->name)
            ->etc()
        )
        ->has('descendants', 3) // childFolder1, childFolder2, grandchildFolder
        ->has('folder.files', 2) // file1, file2
        ->has('rootFolders') // Navigation folders
    );
});

it('includes breadcrumb ancestors', function () {
    $rootFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $childFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $rootFolder->id,
        'order' => 1,
    ]);

    $grandchildFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $childFolder->id,
        'order' => 1,
    ]);

    $response = $this->get(route('browse', $grandchildFolder));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/show')
        ->has('folder', fn ($folder) => $folder
            ->where('id', $grandchildFolder->id)
            ->has('ancestors', 2) // rootFolder, childFolder
            ->etc()
        )
    );
});

it('excludes deleted ancestors from breadcrumbs', function () {
    $rootFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $deletedFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $rootFolder->id,
        'order' => 1,
        'deleted_at' => now(),
    ]);

    $activeFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $deletedFolder->id,
        'order' => 1,
    ]);

    $response = $this->get(route('browse', $activeFolder));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/show')
        ->has('folder', fn ($folder) => $folder
            ->where('id', $activeFolder->id)
            ->has('ancestors', 1) // Only rootFolder, deletedFolder excluded
            ->etc()
        )
    );
});

it('returns 404 for deleted folder', function () {
    $deletedFolder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
        'deleted_at' => now(),
    ]);

    $response = $this->get(route('browse', $deletedFolder));

    $response->assertNotFound();
});

it('does not require authentication for public pages', function () {
    $folder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    // Test main page without authentication
    $response = $this->get(route('home'));
    $response->assertSuccessful();

    // Test folder page without authentication
    $response = $this->get(route('browse', $folder));
    $response->assertSuccessful();
});

it('loads files with version information', function () {
    $folder = Folder::factory()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => null,
        'order' => 1,
    ]);

    $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $folder->files()->attach($file->id);

    $response = $this->get(route('browse', $folder));

    $response->assertInertia(fn ($page) => $page
        ->component('listing/show')
        ->has('folder.files.0', fn ($file) => $file
            ->has('version')
            ->has('version.hash')
            ->has('version.size')
            ->etc()
        )
    );
});
