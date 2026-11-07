<?php

use App\Console\Commands\BackupDatabase;
use App\Notifications\BackupProblem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

beforeEach(function () {
    // PushoverReceiver is built in app code, so it needs config even under Notification::fake().
    config([
        'services.pushover.user_key' => 'test-user-key',
        'services.pushover.token' => 'test-token',
        'backup.backup.password' => 'test-archive-password',
        'backup.backup.tries' => 1,
    ]);

    Storage::fake('r2');
    Notification::fake();

    // RefreshDatabase wraps the default connection in a transaction, and SQLite
    // can't VACUUM inside one, so these tests use a real database file instead.
    $this->databaseFile = tempnam(sys_get_temp_dir(), 'backup-test-');
    $this->originalConnection = config('database.default');
    config([
        'database.connections.backup_test' => [
            'driver' => 'sqlite',
            'database' => $this->databaseFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => 'backup_test',
    ]);

    // The package caches its settings as a scoped Config object, possibly built
    // before the overrides above, so make it rebuild from the current config.
    app()->forgetScopedInstances();

    DB::statement('CREATE TABLE notes (body TEXT)');
    DB::table('notes')->insert(['body' => 'watched Dune at the cinema']);
});

afterEach(function () {
    // Switch back before RefreshDatabase rolls back "the default connection".
    config(['database.default' => $this->originalConnection]);
    DB::purge('backup_test');
    File::delete($this->databaseFile);
});

test('uploads an encrypted zip holding a working copy of the database', function () {
    $this->artisan('app:backup-database')->assertSuccessful();

    $files = Storage::disk('r2')->allFiles('scanner_app');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.zip');

    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('r2')->path($files[0])))->toBeTrue()
        ->and($zip->getNameIndex(0))->toBe('database.sqlite')
        ->and($zip->statIndex(0)['encryption_method'])->toBe(ZipArchive::EM_AES_256)
        ->and($zip->getFromName('database.sqlite'))->toBeFalse(); // unreadable without the password

    $zip->setPassword('test-archive-password');
    $restored = tempnam(sys_get_temp_dir(), 'backup-restored-');
    File::put($restored, (string) $zip->getFromName('database.sqlite'));
    $zip->close();

    $pdo = new PDO('sqlite:'.$restored);
    expect($pdo->query('SELECT body FROM notes')->fetchColumn())->toBe('watched Dune at the cinema');

    $pdo = null;
    File::delete($restored);

    // The unencrypted snapshot is removed afterwards.
    expect(File::exists(BackupDatabase::snapshotDirectory().'/database.sqlite'))->toBeFalse();

    Notification::assertNothingSent();
});

test('a failed upload sends a Pushover alert', function () {
    config(['backup.backup.destination.disks' => ['not-a-disk']]);

    $this->artisan('app:backup-database')->assertFailed();

    Notification::assertSentOnDemand(BackupProblem::class, fn (BackupProblem $n) => $n->problem === 'failed');
    expect(File::exists(BackupDatabase::snapshotDirectory().'/database.sqlite'))->toBeFalse();
});

test('the morning health check alerts when there is no recent backup', function () {
    $this->artisan('backup:monitor');

    Notification::assertSentOnDemand(BackupProblem::class, fn (BackupProblem $n) => $n->problem === 'needs attention');
});

test('the health check stays quiet after a successful backup', function () {
    $this->artisan('app:backup-database')->assertSuccessful();
    $this->artisan('backup:monitor');

    Notification::assertNothingSent();
});

test('unhealthy backup alerts include the reasons', function () {
    event(new UnhealthyBackupWasFound('r2', 'scanner_app', collect([
        ['check' => 'MaximumAgeInDays', 'message' => 'The latest backup is too old.'],
    ])));

    Notification::assertSentOnDemand(BackupProblem::class, fn (BackupProblem $n) => $n->detail === 'The latest backup is too old.');
});
