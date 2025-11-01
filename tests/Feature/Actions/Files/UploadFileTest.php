<?php

use App\Actions\Files\UploadFile;
use App\Models\File;
use App\Models\User;
use App\Models\Version;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new UploadFile;
    Storage::fake('local');
});

it('uploads local file successfully', function () {
    $uploadedFile = UploadedFile::fake()->create('test.pdf', 100);

    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Test File',
        'description' => 'Test Description',
        'file' => $uploadedFile,
        'disk' => 'local',
    ];

    $file = $this->action->handle($data, $this->user);

    expect($file)->toBeInstanceOf(File::class);
    expect($file->name)->toBe('Test File');
    expect($file->description)->toBe('Test Description');
    expect($file->workspace_id)->toBe($this->workspace->id);
    expect($file->type)->toBe('application/pdf');
    expect($file->extension)->toBe('pdf');
});

it('uploads external file successfully', function () {
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

    $file = $this->action->handle($data, $this->user);

    expect($file)->toBeInstanceOf(File::class);
    expect($file->name)->toBe('External File');
    expect($file->workspace_id)->toBe($this->workspace->id);
    expect($file->version->disk)->toBe('external');
    expect($file->version->path)->toBe('https://example.com/test-file.pdf');
});

it('creates version record for uploaded file', function () {
    $uploadedFile = UploadedFile::fake()->create('test.pdf', 100);

    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Test File',
        'file' => $uploadedFile,
        'disk' => 'local',
    ];

    $file = $this->action->handle($data, $this->user);

    expect($file->version)->toBeInstanceOf(Version::class);
    expect($file->version->number)->toBe(1);
    expect($file->version->disk)->toBe('local');
    expect($file->version->size)->toBe($uploadedFile->getSize());
});

it('handles empty description', function () {
    $uploadedFile = UploadedFile::fake()->create('test.pdf', 100);

    $data = [
        'workspace_id' => $this->workspace->id,
        'name' => 'Test File',
        'file' => $uploadedFile,
        'disk' => 'local',
    ];

    $file = $this->action->handle($data, $this->user);

    expect($file->description)->toBeNull();
});
