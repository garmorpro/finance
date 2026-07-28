<?php

/** @var string $title */
/** @var string $widgetKey */
/** @var bool $isWide */

use App\Support\View;

?>
<div class="tile-header">
    <span class="label-text mb-0"><?= View::e($title) ?></span>
    <div class="flex items-center gap-1">
        <button
            type="button"
            class="tile-resize-btn"
            data-widget-resize="<?= View::e($widgetKey) ?>"
            data-wide="<?= $isWide ? '1' : '0' ?>"
            title="<?= $isWide ? 'Shrink to column width' : 'Expand to full width' ?>"
            aria-label="Toggle tile width"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18-5h-3a2 2 0 0 0-2 2v3M3 16v3a2 2 0 0 0 2 2h3m11-5v3a2 2 0 0 1-2 2h-3"/></svg>
        </button>
    </div>
</div>
