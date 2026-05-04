<?php

use Illuminate\Support\Facades\Schedule;

// ── CRM Notification Scheduler ──
// Jalankan setiap jam: cek overdue & follow up reminder
Schedule::command('crm:notify')->hourly();
