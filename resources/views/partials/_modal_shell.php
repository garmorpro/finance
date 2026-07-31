<?php

declare(strict_types=1);

/** @var string $csrfToken */

use App\Support\View;

/**
 * Shared popup shell (Settings → Categories' "Manage"/"New category",
 * Settings → Tags' "Manage", the sidebar's quick-add): a centered,
 * backdropped dialog wired up by public/assets/js/modals.js
 * (hidden/flex toggling, focus trap, Escape/backdrop-click to close).
 *
 * Included via a plain `require`, not View::partial() — View::partial()
 * renders in an isolated scope via extract(), which would lose these as
 * ordinary local closures the including view can call directly. A bare
 * require shares the including file's scope, so $csrfToken just needs
 * to already be set before this runs.
 */
$modalStart = function (string $id, string $title) use ($csrfToken): void {
    ?>
    <div id="<?= View::e($id) ?>" class="modal-overlay hidden fixed inset-0 z-30 items-center justify-center bg-stone-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="<?= View::e($id) ?>-title">
        <div class="card w-full max-w-sm max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h2 id="<?= View::e($id) ?>-title" class="text-lg font-semibold text-stone-900 dark:text-white"><?= View::e($title) ?></h2>
                <button type="button" data-modal-close="<?= View::e($id) ?>" class="text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200 flex-shrink-0" aria-label="Close">&times;</button>
            </div>
    <?php
};
$modalEnd = function (): void {
    ?>
        </div>
    </div>
    <?php
};
