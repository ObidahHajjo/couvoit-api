<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Interfaces\UserPersonalDataPurgeServiceInterface;
use Illuminate\Console\Command;

/**
 * Purge stale soft-deleted accounts and their personal data.
 */
class PurgeDeletedAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:purge-deleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge personal data for accounts soft-deleted more than 90 days ago';

    /**
     * Execute the console command.
     */
    public function handle(UserPersonalDataPurgeServiceInterface $purgeService): int
    {
        $count = 0;

        User::onlyTrashed()
            ->whereNull('purged_at')
            ->where('deleted_at', '<=', now()->subDays(90))
            ->with(['person' => fn ($query) => $query->withTrashed()])
            ->chunkById(100, function ($users) use ($purgeService, &$count): void {
                foreach ($users as $user) {
                    $purgeService->purge($user);
                    $count++;
                    $this->info("Purged user ID $user->id");
                }
            });

        $this->info("Done. $count account(s) purged.");

        return self::SUCCESS;
    }
}
