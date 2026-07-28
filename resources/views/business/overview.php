<?php

/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Overview · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'business-overview']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Business</h1>
                    <span class="badge-owner">New</span>
                </div>

                <div class="card text-center py-10" style="border-style: dashed;">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-stone-100 dark:bg-stone-800 text-stone-400 dark:text-stone-500 mb-3">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.4rem;height:1.4rem;" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                    </div>
                    <p class="text-stone-700 dark:text-stone-300 font-medium mb-1">No business accounts yet</p>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-4 max-w-md mx-auto">When your business gets going, add a business account and MyCFO+ will track its revenue, expenses, and profit separately from your household finances &mdash; still fully manual, still just yours.</p>
                    <button type="button" class="btn-secondary" disabled title="Business tracking isn't built yet">Set up business tracking</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="card tile-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 15l4-5 4 3 5-7"/></svg>
                        <span class="text-sm font-medium">Business Profit</span>
                        <span class="text-xs mt-0.5">Coming soon</span>
                    </div>
                    <div class="card tile-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                        <span class="text-sm font-medium">Revenue by Stream</span>
                        <span class="text-xs mt-0.5">Coming soon</span>
                    </div>
                    <div class="card tile-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.5rem;height:1.5rem;" class="mb-2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z"/></svg>
                        <span class="text-sm font-medium">Business Insights</span>
                        <span class="text-xs mt-0.5">Coming soon</span>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
