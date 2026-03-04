<?php

namespace Webkul\Contact\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Models\Organization;
use Webkul\Lead\Models\Lead;

class PurgeTrash extends Command
{
    protected $signature = 'trash:purge {--days=30 : Days after which trashed records are permanently deleted}';

    protected $description = 'Permanently delete records that have been in trash for more than the specified days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $personsDeleted = Person::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $orgsDeleted = Organization::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $leadsDeleted = Lead::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $total = $personsDeleted + $orgsDeleted + $leadsDeleted;

        $this->info("Purged {$total} records older than {$days} days ({$personsDeleted} contacts, {$orgsDeleted} organizations, {$leadsDeleted} leads).");

        return self::SUCCESS;
    }
}
