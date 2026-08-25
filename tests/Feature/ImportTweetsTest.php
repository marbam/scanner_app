<?php

use App\Models\Tweet;
use Illuminate\Support\Facades\Storage;

function writeTweetsExport(array $entries): string
{
    $path = Storage::disk('local')->path('tweets-export-test.js');

    file_put_contents($path, 'window.YTD.tweets.part0 = '.json_encode($entries));

    return $path;
}

test('imports tweets from a twitter export file', function () {
    $path = writeTweetsExport([
        [
            'tweet' => [
                'id_str' => '1594361246438281216',
                'created_at' => 'Sun Nov 20 16:05:17 +0000 2022',
                'full_text' => 'Hello world',
                'extended_entities' => [
                    'media' => [
                        ['type' => 'photo', 'media_url_https' => 'https://pbs.twimg.com/media/example.jpg'],
                    ],
                ],
            ],
        ],
        [
            'tweet' => [
                'id_str' => '1503314806081794051',
                'created_at' => 'Mon Mar 14 10:19:14 +0000 2022',
                'full_text' => 'Another tweet',
            ],
        ],
    ]);

    $this->artisan('app:import-tweets', ['path' => $path])->assertSuccessful();

    expect(Tweet::count())->toBe(2);

    $tweet = Tweet::where('tweet_id', '1594361246438281216')->firstOrFail();

    expect($tweet->body)->toBe('Hello world');
    expect($tweet->attachments[0]['url'])->toBe('https://pbs.twimg.com/media/example.jpg');
    expect($tweet->posted_at->toDateTimeString())->toBe('2022-11-20 16:05:17');
});

test('re-running the import does not create duplicates', function () {
    $path = writeTweetsExport([
        ['tweet' => ['id_str' => '1', 'created_at' => 'Sun Nov 20 16:05:17 +0000 2022', 'full_text' => 'Original']],
    ]);

    $this->artisan('app:import-tweets', ['path' => $path])->assertSuccessful();
    $this->artisan('app:import-tweets', ['path' => $path])->assertSuccessful();

    expect(Tweet::count())->toBe(1);
});

test('decodes html entities in tweet text', function () {
    $path = writeTweetsExport([
        ['tweet' => ['id_str' => '1', 'created_at' => 'Sun Nov 20 16:05:17 +0000 2022', 'full_text' => '... &lt;cont&gt; good loot &amp; stuff']],
    ]);

    $this->artisan('app:import-tweets', ['path' => $path])->assertSuccessful();

    expect(Tweet::firstOrFail()->body)->toBe('... <cont> good loot & stuff');
});

test('fails gracefully when the file does not exist', function () {
    $this->artisan('app:import-tweets', ['path' => 'missing-file.js'])->assertFailed();

    expect(Tweet::count())->toBe(0);
});
