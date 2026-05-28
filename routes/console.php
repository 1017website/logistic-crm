<?php

use Illuminate\Support\Facades\Schedule;

// ── CRM Notification Scheduler ──
// Dijalankan tiap menit agar reminder task (1 jam & 30 menit sebelum jadwal,
// serta ringkasan jam 06:00) presisi. Anti-dobel dijaga di dalam command
// via penanda unik pada kolom url notifikasi.
Schedule::command('crm:notify')->everyMinute()->withoutOverlapping();
