<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user);
    Storage::fake('local');
});

describe('Index Method', function () {
    it('can list files with pagination using Inertia', function () {
        File::factory()->count(25)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->has('files')
            ->has('files.data', 20) // Paginated data
            ->has('files.links')
            ->has('files.total')
            ->where('filter', null)
            ->has('counts')
        );
    });

    it('returns correct component and props', function () {
        File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->has('files.data')
            ->has('files.links')
            ->has('files.next_page_url')
            ->has('counts')
        );
    });

    it('filters work correctly for files', function () {
        File::factory()->count(5)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id, 'filter' => 'files']));

        $response->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->where('filter', 'files')
        );
    });

    it('filters work correctly for unplaced files', function () {
        File::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id, 'filter' => 'unplaced']));

        $response->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->where('filter', 'unplaced')
        );
    });

    it('filters work correctly for deleted files', function () {
        File::factory()->count(2)->create(['workspace_id' => $this->workspace->id, 'deleted_at' => now()]);

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id, 'filter' => 'deleted']));

        $response->assertInertia(fn ($page) => $page
            ->component('files/index')
            ->where('filter', 'deleted')
        );
    });

    it('requires authentication', function () {
        auth()->logout();

        $response = $this->get(route('files.index', ['workspace_id' => $this->workspace->id]));

        $response->assertRedirect(route('login'));
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->get(route('files.index', ['workspace_id' => $otherWorkspace->id]));

        $response->assertForbidden();
    });
});

describe('Store Method', function () {
    it('can upload local file successfully', function () {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Test File',
            'description' => 'Test Description',
            'file' => $file,
            'disk' => 'local',
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File uploaded successfully.');

        $this->assertDatabaseHas('files', [
            'name' => 'Test File',
            'description' => 'Test Description',
            'workspace_id' => $this->workspace->id,
        ]);
    });

    it('can upload external file with URL', function () {
        // Mock HTTP request for external file
        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/*' => \Illuminate\Support\Facades\Http::response('Sample PDF content', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'External File',
            'description' => 'External Description',
            'disk' => 'external',
            'path' => 'https://example.com/test-file.pdf',
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File uploaded successfully.');

        $this->assertDatabaseHas('files', [
            'name' => 'External File',
            'description' => 'External Description',
            'workspace_id' => $this->workspace->id,
        ]);
    });

    it('validates required fields', function () {
        $response = $this->post(route('files.store'), []);

        $response->assertSessionHasErrors(['workspace_id', 'name', 'disk']);
    });

    it('validates file size limits', function () {
        $file = UploadedFile::fake()->create('large.pdf', 102500); // Over 100MB

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Large File',
            'file' => $file,
            'disk' => 'local',
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertSessionHasErrors(['file']);
    });

    it('validates file types', function () {
        $file = UploadedFile::fake()->create('test.exe', 100);

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Executable File',
            'file' => $file,
            'disk' => 'local',
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertSessionHasErrors(['file']);
    });

    it('prevents duplicate hash uploads within workspace', function () {
        $file1 = UploadedFile::fake()->create('test1.pdf', 100);
        $file2 = UploadedFile::fake()->create('test2.pdf', 100);

        // Upload first file
        $this->post(route('files.store'), [
            'workspace_id' => $this->workspace->id,
            'name' => 'First File',
            'file' => $file1,
            'disk' => 'local',
        ]);

        // Try to upload identical file
        $response = $this->post(route('files.store'), [
            'workspace_id' => $this->workspace->id,
            'name' => 'Second File',
            'file' => $file1, // Same file content
            'disk' => 'local',
        ]);

        $response->assertSessionHasErrors(['file']);
    });

    it('allows duplicate hash across different workspaces', function () {
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->users()->attach($this->user->id, [
            'role' => 'member',
            'permissions' => json_encode(['files' => true, 'folders' => true, 'settings' => true]),
            'joined_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        // Upload to first workspace
        $this->post(route('files.store'), [
            'workspace_id' => $this->workspace->id,
            'name' => 'File in Workspace 1',
            'file' => $file,
            'disk' => 'local',
        ]);

        // Upload to second workspace (should be allowed)
        $response = $this->post(route('files.store'), [
            'workspace_id' => $otherWorkspace->id,
            'name' => 'File in Workspace 2',
            'file' => $file,
            'disk' => 'local',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File uploaded successfully.');
    });

    it('requires workspace access with files permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->post(route('files.store'), [
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Unauthorized File',
            'file' => $file,
            'disk' => 'local',
        ]);

        $response->assertForbidden();
    });

    it('can assign file to single folder', function () {
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Test File',
            'file' => $file,
            'disk' => 'local',
            'folder_id' => $folder->id,
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertRedirect();

        $uploadedFile = File::where('name', 'Test File')->first();
        $this->assertTrue($uploadedFile->folders->contains($folder));
    });

    it('can assign file to multiple folders', function () {
        $folder1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $data = [
            'workspace_id' => $this->workspace->id,
            'name' => 'Test File',
            'file' => $file,
            'disk' => 'local',
            'folder_ids' => [$folder1->id, $folder2->id],
        ];

        $response = $this->post(route('files.store'), $data);

        $response->assertRedirect();

        $uploadedFile = File::where('name', 'Test File')->first();
        $this->assertTrue($uploadedFile->folders->contains($folder1));
        $this->assertTrue($uploadedFile->folders->contains($folder2));
    });
});

describe('Show Method', function () {
    it('can view file details with Inertia', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.show', $file));

        $response->assertInertia(fn ($page) => $page
            ->component('files/show')
            ->has('file')
            ->where('file.id', $file->id)
            ->where('file.name', $file->name)
        );
    });

    it('returns correct component', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.show', $file));

        $response->assertInertia(fn ($page) => $page
            ->component('files/show')
        );
    });

    it('includes all relationships', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $file->folders()->attach($folder->id);

        $response = $this->get(route('files.show', $file));

        $response->assertInertia(fn ($page) => $page
            ->component('files/show')
            ->has('file.versions')
            ->has('file.folders')
            ->has('file.tags')
            ->has('file.comments')
        );
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = File::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->get(route('files.show', $file));

        $response->assertForbidden();
    });

    it('returns 404 for non-existent file', function () {
        $response = $this->get(route('files.show', 'non-existent-id'));

        $response->assertNotFound();
    });
});

describe('Update Method', function () {
    it('can update file name', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'name' => 'Updated File Name',
        ];

        $response = $this->put(route('files.update', $file), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File updated successfully.');

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'name' => 'Updated File Name',
        ]);
    });

    it('can update file description', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'name' => $file->name, // Required field
            'description' => 'Updated Description',
        ];

        $response = $this->put(route('files.update', $file), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'description' => 'Updated Description',
        ]);
    });

    it('can update folder assignments', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder1 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
        $folder2 = Folder::factory()->create(['workspace_id' => $this->workspace->id]);

        $data = [
            'folder_ids' => [$folder1->id, $folder2->id],
        ];

        $response = $this->put(route('files.update', $file), $data);

        $response->assertRedirect();

        $file->refresh();
        $this->assertTrue($file->folders->contains($folder1));
        $this->assertTrue($file->folders->contains($folder2));
    });

    it('validates required fields', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->put(route('files.update', $file), [
            'name' => '', // Empty name should fail validation
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    it('requires workspace access with files permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = File::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->put(route('files.update', $file), [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertForbidden();
    });

    it('cannot update locked files', function () {
        $file = File::factory()->create([
            'workspace_id' => $this->workspace->id,
            'locked' => true,
        ]);

        $response = $this->put(route('files.update', $file), [
            'name' => 'Should Not Update',
        ]);

        $response->assertSessionHasErrors(['locked']);
    });
});

describe('Destroy Method', function () {
    it('can soft delete file', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->delete(route('files.destroy', $file));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File deleted successfully.');

        $this->assertSoftDeleted('files', ['id' => $file->id]);
    });

    it('requires workspace access with files permission', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = File::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->delete(route('files.destroy', $file));

        $response->assertForbidden();
    });

    it('cannot delete locked files', function () {
        $file = File::factory()->create([
            'workspace_id' => $this->workspace->id,
            'locked' => true,
        ]);

        $response = $this->delete(route('files.destroy', $file));

        $response->assertSessionHasErrors(['locked']);
    });

    it('soft deletes preserve versions', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->delete(route('files.destroy', $file));

        $response->assertRedirect();

        // Versions should still exist
        $this->assertDatabaseHas('versions', ['file_id' => $file->id]);
    });
});

describe('Restore Method', function () {
    it('can restore soft-deleted file', function () {
        $file = File::factory()->create([
            'workspace_id' => $this->workspace->id,
            'deleted_at' => now(),
        ]);

        $response = $this->post(route('files.restore', $file));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'File restored successfully.');

        $this->assertDatabaseMissing('files', [
            'id' => $file->id,
            'deleted_at' => $file->deleted_at,
        ]);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = File::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'deleted_at' => now(),
        ]);

        $response = $this->post(route('files.restore', $file));

        $response->assertForbidden();
    });

    it('returns 404 for non-deleted files', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->post(route('files.restore', $file));

        $response->assertNotFound();
    });
});

describe('Download Method', function () {
    it('can download local file', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $response = $this->get(route('files.download', $file));

        $response->assertSuccessful();
        $response->assertHeader('Content-Type', 'application/pdf');
    });

    it('can download external file', function () {
        // Mock HTTP request for external file verification
        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/*' => \Illuminate\Support\Facades\Http::response('', 200),
        ]);

        $file = File::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        // Update version to use external disk
        $file->version->update([
            'disk' => 'external',
            'path' => 'https://example.com/test-file.pdf',
        ]);

        $response = $this->get(route('files.download', $file));

        $response->assertRedirect($file->version->path);
    });

    it('requires workspace access', function () {
        $otherWorkspace = Workspace::factory()->create();
        $file = File::factory()->create(['workspace_id' => $otherWorkspace->id]);

        $response = $this->get(route('files.download', $file));

        $response->assertForbidden();
    });

    it('tracks download count', function () {
        $file = File::factory()->create(['workspace_id' => $this->workspace->id]);

        $this->get(route('files.download', $file));

        // Verify download count was incremented
        $this->assertDatabaseHas('versions', [
            'file_id' => $file->id,
            'downloads' => 1,
        ]);
    });
});
