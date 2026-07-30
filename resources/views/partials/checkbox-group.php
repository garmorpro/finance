<?php

/**
 * A scrollable checkbox list, styled to match .field-input, used in place
 * of a native <select multiple> — a native multi-select's "selected" row
 * is unstyleable browser chrome that ignores this app's dark theme
 * outright. Submits the same name="x[]" array shape a multi-select would.
 *
 * @var string $name field name, without the trailing []
 * @var list<array{value: int, label: string, checked: bool}> $options
 */

use App\Support\View;

?>
<div class="border border-stone-300 dark:border-stone-700 rounded-lg bg-white dark:bg-stone-900 max-h-40 overflow-y-auto p-1.5 space-y-0.5">
    <?php foreach ($options as $opt): ?>
        <label class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-stone-50 dark:hover:bg-stone-800 cursor-pointer text-sm text-stone-700 dark:text-stone-300">
            <input type="checkbox" name="<?= View::e($name) ?>[]" value="<?= (int) $opt['value'] ?>" <?= $opt['checked'] ? 'checked' : '' ?> class="rounded border-stone-300 dark:border-stone-700 text-terracotta-600 focus:ring-terracotta-500">
            <?= View::e($opt['label']) ?>
        </label>
    <?php endforeach; ?>
</div>
