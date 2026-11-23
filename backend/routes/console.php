<?php

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rag:reindex {--organization= : Only re-embed one organization}', function () {
    $query = Document::withoutTenancy();

    if ($org = $this->option('organization')) {
        $query->where('organization_id', $org);
    }

    $count = 0;
    $query->each(function (Document $document) use (&$count) {
        ProcessDocument::dispatch($document->id);
        $count++;
    });

    $this->info("Queued {$count} document(s) for re-embedding.");
})->purpose('Re-chunk and re-embed documents (run after changing the embedding model).');

Artisan::command('usage:prune {--months=13}', function () {
    $deleted = UsageRecord::where('created_at', '<', now()->subMonths((int) $this->option('months')))->delete();
    $this->info("Deleted {$deleted} old usage record(s).");
})->purpose('Delete usage records older than N months.');

Artisan::command('admin:grant {email} {--revoke : Remove platform-admin access instead}', function () {
    $user = User::where('email', strtolower($this->argument('email')))->first();

    if (! $user) {
        $this->error('No user with that email. Register on the website first, then run this again.');

        return 1;
    }

    $user->forceFill(['is_super_admin' => ! $this->option('revoke')])->save();
    $this->info($this->option('revoke') ? "{$user->email} is no longer a platform admin." : "{$user->email} is now a platform admin.");

    return 0;
})->purpose('Give (or with --revoke, remove) platform-admin access for a registered user.');

Schedule::command('usage:prune')->monthly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
