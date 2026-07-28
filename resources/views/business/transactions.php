<?php

/** @var string $csrfToken */

use App\Support\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Transactions · MyCFO+</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <?php View::partial('partials/sidebar', ['csrfToken' => $csrfToken, 'active' => 'business-transactions']); ?>

        <div class="app-content">
            <main class="page-main-wide">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Business Transactions</h1>
                    <span class="badge-owner">New</span>
                </div>

                <div class="card text-center py-10" style="border-style: dashed;">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-stone-100 dark:bg-stone-800 text-stone-400 dark:text-stone-500 mb-3">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.4rem;height:1.4rem;" aria-hidden="true"><path d="M9 3h6l2 4h4v14H3V7h4l2-4z"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    </div>
                    <p class="text-stone-700 dark:text-stone-300 font-medium mb-1">No business transactions yet</p>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mb-4 max-w-md mx-auto">Once business tracking is set up, business income and expenses will show here &mdash; kept separate from your household transactions, still entered manually, still just yours.</p>
                    <a href="/business/overview" class="btn-secondary">Back to Business Overview</a>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
