<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user);
});

describe('Index Method', function () {
    it('can list folders with pagination using Inertia', function () {
        Folder::factory()->count(25)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->has('folders')
            ->has('folders.data', 20) // Paginated data
            ->has('folders.links')
            ->has('folders.total')
            ->where('filter', null)
            ->where('search', null)
            ->where('max_level', null)
            ->has('max_level_available')
            ->has('counts')
        );
    });

    it('returns correct component and props', function () {
        Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->has('folders.data')
            ->has('folders.links')
            ->has('folders.next_page_url')
            ->has('max_level_available')
            ->has('counts')
        );
    });

    it('filters work correctly for folders', function () {
        Folder::factory()->count(5)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id, 'filter' => 'folders']));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->where('filter', 'folders')
        );
    });

    it('filters work correctly for deleted folders', function () {
        Folder::factory()->count(3)->create(['workspace_id' => $this->workspace->id, 'deleted_at' => now()]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id, 'filter' => 'deleted']));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->where('filter', 'deleted')
        );
    });

    it('search filters by name', function () {
        Folder::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Important Folder']);
        Folder::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Regular Folder']);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id, 'search' => 'Important']));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->where('search', 'Important')
        );
    });

    it('max level filter works', function () {
        $rootFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => null]);
        $childFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => $rootFolder->id]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id, 'max_level' => 0]));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->where('max_level', 0)
        );
    });

    it('returns correct counts', function () {
        Folder::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/index')
            ->has('counts')
        );
    });

    it('JSON API endpoint for folder selection works', function () {
        Folder::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->getJson(route('folders.index', ['workspace_id' => $this->workspace->id]));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'route',
            ],
        ]);
    });

    it('requires authentication', function () {
        auth()->logout();

        $response = $this->get(route('folders.index', ['workspace_id' => $this->workspace->id]));

        $response->assertRedirect(route('login'));
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->get(route('folders.index', ['workspace_id' => $otherWorkspace->id]));

        $response->assertForbidden();
    });
});

describe('Store Method', function () {
    it('can create root folder', function () {
        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Test Root Folder',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('folders.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folder created successfully.');

        $this->assertDatabaseHas('folders', [
            'name' => 'Test Root Folder',
            'description' => 'Test Description',
            'workspace_id' => $this->workspace->id,
            'parent_id' => null,
            'level' => 0,
        ]);
    });

    it('can create nested folder', function () {
        $parentFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parentFolder->id,
            'name' => 'Test Child Folder',
            'description' => 'Test Description',
        ];

        $response = $this->post(route('folders.store'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'name' => 'Test Child Folder',
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parentFolder->id,
            'level' => 1,
        ]);
    });

    it('validates name uniqueness within parent', function () {
        $parent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'name' => 'Existing Folder',
        ]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'name' => 'Existing Folder',
        ];

        $response = $this->post(route('folders.store'), $data);

        $response->assertSessionHasErrors(['name']);
    });

    it('allows duplicate names in different parents', function () {
        $parent1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $parent2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent1->id,
            'name' => 'Same Name',
        ]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent2->id,
            'name' => 'Same Name',
        ];

        $response = $this->post(route('folders.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folder created successfully.');
    });

    it('sets correct depth and path', function () {
        $rootFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => null]);
        $childFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => $rootFolder->id]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'parent_id' => $childFolder->id,
            'name' => 'Grandchild Folder',
        ];

        $response = $this->post(route('folders.store'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'name' => 'Grandchild Folder',
            'level' => 2,
        ]);
    });

    it('requires workspace access with folders permission', function () {
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->post(route('folders.store'), [
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Unauthorized Folder',
        ]);

        $response->assertForbidden();
    });

    it('validates required fields', function () {
        $response = $this->post(route('folders.store'), []);

        $response->assertSessionHasErrors(['workspace_id', 'name']);
    });
});

describe('Show Method', function () {
    it('can view folder with children using Inertia', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        Folder::factory()->count(3)->create(['workspace_id' => $this->workspace->id, 'parent_id' => $folder->id]);

        $response = $this->get(route('folders.show', $folder));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/show')
            ->has('folder', fn ($prop) => $prop
                ->where('id', $folder->id)
                ->where('name', $folder->name)
                ->has('children_count')
                ->has('placements_count')
                ->etc()
            )
        );
    });

    it('returns correct component', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('folders.show', $folder));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/show')
        );
    });

    it('includes children count and placements count', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        Folder::factory()->count(2)->create(['workspace_id' => $this->workspace->id, 'parent_id' => $folder->id]);

        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder->files()->attach($file->id);

        $response = $this->get(route('folders.show', $folder));

        $response->assertInertia(fn ($page) => $page
            ->component('folders/show')
            ->has('folder.children_count')
            ->has('folder.placements_count')
        );
    });

    it('lazy loads children when requested', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        Folder::factory()->count(3)->create(['workspace_id' => $this->workspace->id, 'parent_id' => $folder->id]);

        $response = $this->get(route('folders.show', $folder).'?children=true');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        $folder = Folder::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->get(route('folders.show', $folder));

        $response->assertForbidden();
    });

    it('returns 404 for deleted folder', function () {
        $folder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'deleted_at' => now(),
        ]);

        $response = $this->get(route('folders.show', $folder));

        $response->assertNotFound();
    });
});

describe('Update Method', function () {
    it('can update folder name', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'name' => 'Updated Folder Name',
        ];

        $response = $this->put(route('folders.update', $folder), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folder updated successfully.');

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Updated Folder Name',
        ]);
    });

    it('can update folder description', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'description' => 'Updated Description',
        ];

        $response = $this->put(route('folders.update', $folder), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'description' => 'Updated Description',
        ]);
    });

    it('can move folder to different parent', function () {
        $oldParent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $newParent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $oldParent->id,
        ]);

        $data = [
            'parent_id' => $newParent->id,
        ];

        $response = $this->put(route('folders.update', $folder), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'parent_id' => $newParent->id,
        ]);
    });

    it('validates name uniqueness within new parent', function () {
        $parent1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $parent2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent2->id,
            'name' => 'Existing Name',
        ]);

        $folder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent1->id,
            'name' => 'Different Name',
        ]);

        $data = [
            'parent_id' => $parent2->id,
            'name' => 'Existing Name',
        ];

        $response = $this->put(route('folders.update', $folder), $data);

        $response->assertSessionHasErrors(['name']);
    });

    it('cannot move folder to its own descendant', function () {
        $parent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $child = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
        ]);

        $data = [
            'parent_id' => $child->id,
        ];

        $response = $this->put(route('folders.update', $parent), $data);

        $response->assertSessionHasErrors(['parent_id']);
    });

    it('requires workspace access with folders permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $folder = Folder::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->put(route('folders.update', $folder), [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    });

    it('updates depth and path correctly', function () {
        $rootFolder = Folder::factory()->create(['workspace_id' => $this->workspace->id, 'parent_id' => null]);
        $childFolder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $rootFolder->id,
        ]);

        $data = [
            'parent_id' => null, // Move to root
        ];

        $response = $this->put(route('folders.update', $childFolder), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'id' => $childFolder->id,
            'parent_id' => null,
            'level' => 0,
        ]);
    });
});

describe('Destroy Method', function () {
    it('can soft delete folder', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->delete(route('folders.destroy', $folder));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folder deleted successfully.');

        $this->assertSoftDeleted('folders', ['id' => $folder->id]);
    });

    it('soft deletes cascade to children', function () {
        $parent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $child = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
        ]);

        $response = $this->delete(route('folders.destroy', $parent));

        $response->assertRedirect();

        $this->assertSoftDeleted('folders', ['id' => $parent->id]);
        $this->assertSoftDeleted('folders', ['id' => $child->id]);
    });

    it('files remain accessible', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder->files()->attach($file->id);

        $response = $this->delete(route('folders.destroy', $folder));

        $response->assertRedirect();

        // File should still exist and be accessible
        $this->assertDatabaseHas('files', ['id' => $file->id, 'deleted_at' => null]);
    });

    it('requires workspace access with folders permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $folder = Folder::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->delete(route('folders.destroy', $folder));

        $response->assertForbidden();
    });
});

describe('Restore Method', function () {
    it('can restore soft-deleted folder', function () {
        $folder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'deleted_at' => now(),
        ]);

        $response = $this->post(route('folders.restore', $folder));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folder restored successfully.');

        $this->assertDatabaseMissing('folders', [
            'id' => $folder->id,
            'deleted_at' => $folder->deleted_at,
        ]);
    });

    it('restores children recursively', function () {
        $parent = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'deleted_at' => now(),
        ]);
        $child = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'deleted_at' => now(),
        ]);

        $response = $this->post(route('folders.restore', $parent));

        $response->assertRedirect();

        $this->assertDatabaseMissing('folders', [
            'id' => $parent->id,
            'deleted_at' => $parent->deleted_at,
        ]);
        $this->assertDatabaseMissing('folders', [
            'id' => $child->id,
            'deleted_at' => $child->deleted_at,
        ]);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        $folder = Folder::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'deleted_at' => now(),
        ]);

        $response = $this->post(route('folders.restore', $folder));

        $response->assertForbidden();
    });
});

describe('Reorder Method', function () {
    it('can reorder folders within same parent', function () {
        $parent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder1 = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'order' => 1,
        ]);
        $folder2 = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'order' => 2,
        ]);

        $data = [
            'folders' => [
                ['id' => $folder2->id, 'order' => 1],
                ['id' => $folder1->id, 'order' => 2],
            ],
        ];

        $response = $this->post(route('folders.reorder'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Folders reordered successfully.');

        $this->assertDatabaseHas('folders', [
            'id' => $folder2->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('folders', [
            'id' => $folder1->id,
            'order' => 2,
        ]);
    });

    it('updates order field correctly', function () {
        $parent = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent->id,
            'order' => 5,
        ]);

        $data = [
            'folders' => [
                ['id' => $folder->id, 'order' => 1],
            ],
        ];

        $response = $this->post(route('folders.reorder'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'order' => 1,
        ]);
    });

    it('validates all folders have same parent', function () {
        $parent1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $parent2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder1 = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent1->id,
        ]);
        $folder2 = Folder::factory()->create([
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parent2->id,
        ]);

        $data = [
            'folders' => [
                ['id' => $folder1->id, 'order' => 1],
                ['id' => $folder2->id, 'order' => 2],
            ],
        ];

        $response = $this->post(route('folders.reorder'), $data);

        $response->assertSessionHasErrors(['folders']);
    });

    it('requires workspace access with folders permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $folder = Folder::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $data = [
            'folders' => [
                ['id' => $folder->id, 'order' => 1],
            ],
        ];

        $response = $this->post(route('folders.reorder'), $data);

        $response->assertForbidden();
    });
});
