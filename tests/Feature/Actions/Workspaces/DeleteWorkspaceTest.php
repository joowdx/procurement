<?php

use App\Actions\Workspaces\DeleteWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new DeleteWorkspace;
    $this->actingAs($this->user);
});

it('soft deletes group by default', function () {
    $result = $this->action->handle($this->workspace, false);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('workspaces', ['id' => $this->workspace->id]);
});

it('permanently deletes group when forced', function () {
    $result = $this->action->handle($this->workspace, true);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('workspaces', ['id' => $this->workspace->id]);
});

it('sets deleted_by when soft deleting', function () {
    $this->action->handle($this->workspace, false);

    $this->assertDatabaseHas('workspaces', [
        'id' => $this->workspace->id,
        'deleted_by' => $this->user->id,
    ]);
});
