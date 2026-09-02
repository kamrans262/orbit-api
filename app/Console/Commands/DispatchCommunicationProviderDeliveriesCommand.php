<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class DispatchCommunicationProviderDeliveriesCommand extends Command
{
    protected $signature = 'orbit:communications:dispatch-provider-deliveries';

    protected $description = 'Dispatch provider-backed communication deliveries that Orbit can execute locally.';

    public function handle(): int
    {
        $processed = 0;
        CommunicationDelivery::query()
            ->where('channel', 'email')
            ->where('status', 'pending_provider')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->each(function (CommunicationDelivery $delivery) use (&$processed): void {
                $campaign = CommunicationCampaign::query()->find($delivery->campaign_id);
                $user = User::query()->find($delivery->user_id);
                if (! $campaign || ! $user || ! $user->email) {
                    $delivery->forceFill(['status' => 'failed', 'failure_code' => 'recipient_unavailable'])->save();

                    return;
                }

                try {
                    Mail::raw($campaign->body, function ($message) use ($campaign, $user): void {
                        $message->to($user->email)->subject($campaign->subject ?: $campaign->title);
                    });
                    $delivery->forceFill(['status' => 'dispatched', 'delivered_at' => now()])->save();
                    $processed++;
                } catch (\Throwable $e) {
                    $delivery->forceFill(['status' => 'failed', 'failure_code' => class_basename($e)])->save();
                }
            });

        $this->info("Dispatched {$processed} email communication delivery(s).");

        return self::SUCCESS;
    }
}
