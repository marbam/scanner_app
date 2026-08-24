<?php

use App\Models\FacebookPost;
use Illuminate\Support\Facades\Storage;

function writeFacebookExport(array $entries): string
{
    $path = Storage::disk('local')->path('facebook-export-test.json');

    file_put_contents($path, json_encode($entries));

    return $path;
}

test('imports posts from a facebook export file', function () {
    $path = writeFacebookExport([
        [
            'timestamp' => 1181563377,
            'title' => 'Martin Bampton shared a link.',
            'data' => [
                ['post' => 'Hello world'],
                ['update_timestamp' => 1181563377],
            ],
            'attachments' => [
                ['data' => [['external_context' => ['url' => 'https://example.com']]]],
            ],
        ],
        [
            'timestamp' => 1181563400,
            'title' => 'Martin Bampton posted a status update.',
            'data' => [],
        ],
    ]);

    $this->artisan('app:import-facebook-posts', ['path' => $path])->assertSuccessful();

    expect(FacebookPost::count())->toBe(2);

    $post = FacebookPost::where('source_index', 0)->firstOrFail();

    expect($post->title)->toBe('Martin Bampton shared a link.');
    expect($post->body)->toBe('Hello world');
    expect($post->attachments[0]['data'][0]['external_context']['url'])->toBe('https://example.com');
    expect($post->posted_at->timestamp)->toBe(1181563377);
});

test('re-running the import does not create duplicates', function () {
    $path = writeFacebookExport([
        ['timestamp' => 1181563377, 'title' => 'Original title', 'data' => [['post' => 'Hello world']]],
    ]);

    $this->artisan('app:import-facebook-posts', ['path' => $path])->assertSuccessful();
    $this->artisan('app:import-facebook-posts', ['path' => $path])->assertSuccessful();

    expect(FacebookPost::count())->toBe(1);
});

test('fixes mojibake encoding from the facebook export', function () {
    // "café" mis-encoded the way Facebook's export corrupts it.
    $mojibake = mb_convert_encoding('café', 'UTF-8', 'ISO-8859-1');

    $path = writeFacebookExport([
        ['timestamp' => 1181563377, 'title' => null, 'data' => [['post' => $mojibake]]],
    ]);

    $this->artisan('app:import-facebook-posts', ['path' => $path])->assertSuccessful();

    expect(FacebookPost::firstOrFail()->body)->toBe('café');
});

test('fails gracefully when the file does not exist', function () {
    $this->artisan('app:import-facebook-posts', ['path' => 'missing-file.json'])->assertFailed();

    expect(FacebookPost::count())->toBe(0);
});
