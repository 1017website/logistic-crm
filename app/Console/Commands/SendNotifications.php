<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendNotifications extends Command
{
    protected $signature   = 'crm:notify';
    protected $description = 'Send CRM notifications: overdue activities, follow up reminders, target warnings';

    public function handle(): void
    {
        $this->checkOverdue();
        $this->checkFollowUpReminders();
        $this->checkTargetWarnings();

        $this->info('[' . now()->format('Y-m-d H:i') . '] CRM notifications processed.');
    }

    // 1. Activity Overdue — activity yang sudah lewat & belum Done
    private function checkOverdue(): void
    {
        $overdues = Activity::with(['lead', 'customer', 'user'])
            ->where('activity_at', '<', now())
            ->where('status', '!=', 'Done')
            ->where('status', '!=', 'Overdue')
            ->get();

        foreach ($overdues as $act) {
            // Update status jadi Overdue
            $act->update(['status' => 'Overdue']);

            $who  = $act->lead?->company_name ?? $act->customer?->company_name ?? '-';
            $url  = $act->lead_id ? route('leads.show', $act->lead_id) : route('tasks.index');

            // Notif ke Sales PIC
            if ($act->user_id) {
                Notification::send(
                    $act->user_id,
                    'overdue',
                    'Activity Overdue',
                    $act->subject . ' — ' . $who . ' sudah melewati batas waktu',
                    $url
                );
            }

            // Broadcast ke Manager & Admin
            Notification::broadcast(
                'overdue',
                'Activity Overdue',
                $act->subject . ' (' . ($act->user?->name ?? '-') . ') melewati batas waktu',
                $url
            );
        }

        $this->line("  Overdue: {$overdues->count()} activities processed");
    }

    // 2. Follow Up Reminder — H-1 sebelum jadwal follow up
    private function checkFollowUpReminders(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $leads = Lead::with(['user'])
            ->whereDate('next_follow_up', $tomorrow)
            ->whereNotIn('pipeline_stage', ['Won', 'Lost'])
            ->get();

        foreach ($leads as $lead) {
            if (!$lead->user_id) continue;

            Notification::send(
                $lead->user_id,
                'followup',
                'Reminder Follow Up: ' . $lead->company_name,
                'Jadwal follow up ' . $lead->company_name . ' besok ' . Carbon::parse($lead->next_follow_up)->format('d M Y'),
                route('leads.show', $lead)
            );
        }

        // Juga cek activities dengan next_follow_up besok
        $activities = Activity::with(['lead', 'customer', 'user'])
            ->whereDate('next_follow_up', $tomorrow)
            ->get();

        foreach ($activities as $act) {
            if (!$act->user_id) continue;
            $who = $act->lead?->company_name ?? $act->customer?->company_name ?? '-';
            Notification::send(
                $act->user_id,
                'followup',
                'Reminder: Follow Up ' . $who,
                'Jadwal follow up ' . $who . ' besok ' . $tomorrow,
                $act->lead_id ? route('leads.show', $act->lead_id) : route('tasks.index')
            );
        }

        $total = $leads->count() + $activities->count();
        $this->line("  Follow Up Reminders: {$total} sent");
    }

    // 3. Target Warning — sales yang progress < 50%
    private function checkTargetWarnings(): void
    {
        $salesUsers = User::whereIn('role', ['Sales Executive', 'Sales Manager'])
            ->where('status', 'Active')
            ->get();

        $warned = 0;
        foreach ($salesUsers as $user) {
            $target   = $user->target ?? 0;
            if ($target <= 0) continue;

            $achieved = Lead::where('user_id', $user->id)
                ->where('pipeline_stage', 'Won')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->sum('potensi_revenue');

            $pct = ($achieved / $target) * 100;

            // Kirim warning kalau di bawah 50% dan sudah melewati tanggal 15
            if ($pct < 50 && now()->day >= 15) {
                Notification::send(
                    $user->id,
                    'target_warning',
                    'Target Warning',
                    'Progress target ' . now()->format('M Y') . ' baru ' . round($pct, 1) . '% dari ' . idrm($target),
                    route('reports.index', ['report_type' => 'performance'])
                );

                // Broadcast ke Manager & Admin
                Notification::broadcast(
                    'target_warning',
                    'Target Warning: ' . $user->name,
                    $user->name . ' baru mencapai ' . round($pct, 1) . '% target bulan ini',
                    route('reports.index', ['report_type' => 'performance'])
                );

                $warned++;
            }
        }

        $this->line("  Target Warnings: {$warned} sent");
    }
}
