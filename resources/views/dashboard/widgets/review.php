<?php

/** @var array{total: int, before_today: int} $reviewCounts */

?>
<div class="tile-header">
    <span class="label-text mb-0">Needs Review</span>
    <span class="tile-drag-handle" aria-hidden="true">&#8942;&#8942;</span>
</div>
<?php if ($reviewCounts['total'] === 0): ?>
    <div class="tile-placeholder">
        <p class="text-sm">You're all caught up.</p>
    </div>
<?php else: ?>
    <div class="text-3xl font-semibold text-stone-900 dark:text-white"><?= $reviewCounts['total'] ?></div>
    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
        transaction<?= $reviewCounts['total'] === 1 ? '' : 's' ?> to review
        <?php if ($reviewCounts['before_today'] > 0): ?>
            <span class="text-amber-600 dark:text-amber-400 font-medium">&middot; <?= $reviewCounts['before_today'] ?> from before today</span>
        <?php endif; ?>
    </p>
    <a href="/transactions/review" class="inline-block mt-3 text-sm font-medium text-terracotta-600 dark:text-terracotta-400 hover:underline">Review now &rarr;</a>
<?php endif; ?>
