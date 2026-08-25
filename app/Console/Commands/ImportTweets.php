<?php

namespace App\Console\Commands;

use App\Models\Tweet;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportTweets extends Command
{
    protected $signature = 'app:import-tweets {path : Path to tweets.js from a Twitter data export}';

    protected $description = 'Import tweets from a Twitter data export tweets.js file into the tweets table (idempotent, safe to re-run)';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->components->error("Unable to read file: {$path}");

            return self::FAILURE;
        }

        // Twitter's export wraps the JSON array in a JS assignment,
        // e.g. `window.YTD.tweets.part0 = [ ... ]` — strip that prefix.
        $json = preg_replace('/^\s*window\.YTD\.tweets\.part\d+\s*=\s*/', '', $contents, limit: 1);

        $entries = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($entries)) {
            $this->components->error('Expected the JSON file to contain a top-level array of tweets.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry['tweet']) || ! is_array($entry['tweet'])) {
                continue;
            }

            $row = $this->toRow($entry['tweet']);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $imported = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            Tweet::upsert(
                $chunk,
                uniqueBy: ['tweet_id'],
                update: ['posted_at', 'body', 'attachments', 'updated_at'],
            );

            $imported += count($chunk);
        }

        $this->components->info("Imported {$imported} tweets from {$path}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $tweet
     * @return array<string, mixed>|null
     */
    private function toRow(array $tweet): ?array
    {
        if (! isset($tweet['id_str'], $tweet['created_at'])) {
            return null;
        }

        $media = $tweet['extended_entities']['media'] ?? $tweet['entities']['media'] ?? [];

        $attachments = array_values(array_map(
            fn (array $item) => [
                'type' => $item['type'] ?? null,
                'url' => $item['media_url_https'] ?? null,
            ],
            array_filter($media, is_array(...))
        ));

        return [
            'tweet_id' => $tweet['id_str'],
            'posted_at' => Carbon::parse($tweet['created_at'])->toDateTimeString(),
            'body' => isset($tweet['full_text']) ? html_entity_decode($tweet['full_text'], ENT_QUOTES) : null,
            'attachments' => $attachments !== [] ? json_encode($attachments) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
