<?php

namespace App\Console\Commands;

use App\Models\FacebookPost;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportFacebookPosts extends Command
{
    protected $signature = 'app:import-facebook-posts {path : Path to your_posts__check_ins__photos_and_videos_1.json from a Facebook data export}';

    protected $description = 'Import posts from a Facebook data export JSON file into the facebook_posts table (idempotent, safe to re-run)';

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

        $entries = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($entries)) {
            $this->components->error('Expected the JSON file to contain a top-level array of posts.');

            return self::FAILURE;
        }

        $rows = [];

        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $row = $this->toRow($this->fixEncoding($entry), $index);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $imported = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            FacebookPost::upsert(
                $chunk,
                uniqueBy: ['source_index'],
                update: ['posted_at', 'title', 'body', 'attachments', 'updated_at'],
            );

            $imported += count($chunk);
        }

        $this->components->info("Imported {$imported} posts from {$path}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function toRow(array $entry, int $index): ?array
    {
        if (! isset($entry['timestamp'])) {
            return null;
        }

        $body = null;

        foreach ($entry['data'] ?? [] as $item) {
            if (is_array($item) && isset($item['post'])) {
                $body = $item['post'];
                break;
            }
        }

        return [
            'source_index' => $index,
            'posted_at' => Carbon::createFromTimestamp($entry['timestamp'])->toDateTimeString(),
            'title' => $entry['title'] ?? null,
            'body' => $body,
            'attachments' => isset($entry['attachments']) ? json_encode($entry['attachments']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Facebook's export mis-encodes non-ASCII text: the original UTF-8 bytes get
     * decoded as ISO-8859-1 codepoints and then re-encoded as UTF-8, producing
     * mojibake. Reversing that — decode the stored (mojibake) string as UTF-8,
     * then re-encode the resulting codepoints as ISO-8859-1 bytes — collapses it
     * back to the original UTF-8 byte sequence.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function fixEncoding(array $entry): array
    {
        return array_map($this->fixValueEncoding(...), $entry);
    }

    private function fixValueEncoding(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        }

        if (is_array($value)) {
            return array_map($this->fixValueEncoding(...), $value);
        }

        return $value;
    }
}
