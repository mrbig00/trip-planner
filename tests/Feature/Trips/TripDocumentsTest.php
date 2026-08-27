<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;
use App\Models\Location;
use App\Models\TripDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('documents.disk'));
});

test('trip creator can upload a document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    $file = UploadedFile::fake()->create('ticket.pdf', 500, 'application/pdf');

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Flight Ticket')
        ->set('newDocumentDescription', 'Outbound flight')
        ->set('newDocument', $file)
        ->call('addDocument')
        ->assertSet('showAddDocumentModal', false)
        ->assertDispatched('analytics-event', name: 'document_uploaded');

    $document = TripDocument::where('trip_id', $trip->id)->first();
    expect($document)->not->toBeNull();
    expect($document->title)->toBe('Flight Ticket');
    expect($document->description)->toBe('Outbound flight');
    expect($document->user_id)->toBe($owner->id);
    expect($document->original_filename)->toBe('ticket.pdf');
    Storage::disk(config('documents.disk'))->assertExists($document->path);
});

test('participant can upload a document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    $file = UploadedFile::fake()->create('itinerary.pdf', 200, 'application/pdf');

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Itinerary')
        ->set('newDocument', $file)
        ->call('addDocument');

    expect(TripDocument::where('trip_id', $trip->id)->where('user_id', $participant->id)->exists())->toBeTrue();
});

test('unrelated user cannot upload a document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    $file = UploadedFile::fake()->create('ticket.pdf', 200, 'application/pdf');

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Flight Ticket')
        ->set('newDocument', $file)
        ->call('addDocument')
        ->assertForbidden();

    expect(TripDocument::count())->toBe(0);
});

test('addDocument validates title, file presence, size, and mime type', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', '')
        ->set('newDocument', UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'))
        ->call('addDocument')
        ->assertHasErrors(['newDocumentTitle']);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Missing File')
        ->call('addDocument')
        ->assertHasErrors(['newDocument']);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Too Big')
        ->set('newDocument', UploadedFile::fake()->create('big.pdf', config('documents.max_upload_kb') + 100, 'application/pdf'))
        ->call('addDocument')
        ->assertHasErrors(['newDocument']);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Bad Type')
        ->set('newDocument', UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream'))
        ->call('addDocument')
        ->assertHasErrors(['newDocument']);

    expect(TripDocument::count())->toBe(0);
});

test('document owner can download their document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $document = TripDocument::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'disk' => config('documents.disk'),
    ]);
    Storage::disk(config('documents.disk'))->put($document->path, 'file contents');
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('downloadDocument', $document->id)
        ->assertFileDownloaded($document->original_filename);
});

test('unrelated user cannot download a document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('downloadDocument', $document->id)
        ->assertForbidden();
});

test('uploader can edit their document title and description', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'title' => 'Old Title']);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditDocumentModal', $document->id)
        ->assertSet('editingDocument.title', 'Old Title')
        ->set('editingDocument.title', 'New Title')
        ->call('updateDocument', $document->id)
        ->assertSet('showEditDocumentModal', false)
        ->assertDispatched('analytics-event', name: 'document_updated');

    expect($document->fresh()->title)->toBe('New Title');
});

test('trip creator can edit any document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $participant->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditDocumentModal', $document->id)
        ->set('editingDocument.title', 'Renamed by Creator')
        ->call('updateDocument', $document->id);

    expect($document->fresh()->title)->toBe('Renamed by Creator');
});

test('unrelated participant cannot edit another member document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $uploader = User::factory()->create();
    $otherParticipant = User::factory()->create();
    $trip->participants()->attach([$uploader->id, $otherParticipant->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $uploader->id, 'title' => 'Original']);
    $this->actingAs($otherParticipant);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditDocumentModal', $document->id)
        ->assertForbidden();

    expect($document->fresh()->title)->toBe('Original');
});

test('uploader can delete their document and its file', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    Storage::disk(config('documents.disk'))->put($document->path, 'file contents');
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteDocument', $document->id)
        ->assertDispatched('analytics-event', name: 'document_deleted');

    expect(TripDocument::find($document->id))->toBeNull();
    Storage::disk(config('documents.disk'))->assertMissing($document->path);
});

test('trip creator can delete any document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $participant->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteDocument', $document->id);

    expect(TripDocument::find($document->id))->toBeNull();
});

test('unrelated participant cannot delete another member document', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $uploader = User::factory()->create();
    $otherParticipant = User::factory()->create();
    $trip->participants()->attach([$uploader->id, $otherParticipant->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $uploader->id]);
    $this->actingAs($otherParticipant);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteDocument', $document->id)
        ->assertForbidden();

    expect(TripDocument::find($document->id))->not->toBeNull();
});

test('the documents section is hidden from a trip page visitor who is not a member', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'title' => 'Secret Passport Scan']);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('Secret Passport Scan');
});

test('add document modal opens and closes, resetting the form', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Draft')
        ->call('openAddDocumentModal')
        ->assertSet('showAddDocumentModal', true)
        ->assertSet('newDocumentTitle', '')
        ->call('closeAddDocumentModal')
        ->assertSet('showAddDocumentModal', false);
});

test('deleting a trip deletes its documents and their stored files', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    Storage::disk(config('documents.disk'))->put($document->path, 'file contents');
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])->call('delete');

    expect(TripDocument::find($document->id))->toBeNull();
    Storage::disk(config('documents.disk'))->assertMissing($document->path);
});

test('a participant removed from the trip can no longer manage a document they uploaded', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $formerParticipant = User::factory()->create();
    $trip->participants()->attach($formerParticipant->id);
    $document = TripDocument::factory()->create(['trip_id' => $trip->id, 'user_id' => $formerParticipant->id, 'title' => 'Original']);

    // The uploader is removed from the trip after uploading.
    $trip->participants()->detach($formerParticipant->id);
    $this->actingAs($formerParticipant);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteDocument', $document->id)
        ->assertForbidden();

    expect(TripDocument::find($document->id))->not->toBeNull();
});

test('addDocument fails loudly instead of silently truncating uploads when the infra upload ceiling is misconfigured', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    config(['documents.infra_max_upload_kb' => 1]);

    Volt::test('trips.show', ['trip' => $trip])
        ->set('newDocumentTitle', 'Ticket')
        ->set('newDocument', UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'))
        ->call('addDocument');
})->throws(RuntimeException::class);

test('refreshing the trip after an unrelated action keeps documents.uploader eager-loaded', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    TripDocument::factory()->count(3)->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    // Any unrelated action that triggers refreshTrip() internally — a bare
    // Model::refresh() (instead of Show::refreshTrip()'s fresh() call) would
    // drop the nested documents.uploader eager-load back to lazy, which
    // this asserts against directly rather than by counting queries.
    $component = Volt::test('trips.show', ['trip' => $trip])
        ->call('toggleVote', $location->id);

    $refreshedTrip = $component->get('trip');

    expect($refreshedTrip->documents)->toHaveCount(3);
    foreach ($refreshedTrip->documents as $document) {
        expect($document->relationLoaded('uploader'))->toBeTrue();
    }
});
