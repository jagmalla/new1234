<?php
/**
 * Birth-details entry form (layout v2). Rendered in exactly ONE place per
 * request — standalone when there is no chart yet, or inside the "New / Profile"
 * side-menu section once a chart is shown — so its element IDs never duplicate.
 *
 * Expects $in (input values) and $h (escaper) from the including view.
 * @var array<string,string> $in
 * @var callable $h
 */
?>
<form id="birth-form" method="get" action="/calc" class="l2-card p-4 text-sm">
    <h2 class="font-semibold mb-3 text-gray-700">Chart Calculation Details</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="flex flex-col gap-1"><span class="text-gray-500">Name</span>
            <input name="name" value="<?= $h($in['name']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Gender</span>
            <select name="gender" class="border rounded px-2 py-1 bg-white">
                <?php foreach (['' => '—', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'] as $gv => $gl): ?>
                    <option value="<?= $h($gv) ?>" <?= $in['gender'] === $gv ? 'selected' : '' ?>><?= $h($gl) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Date (DD-MM-YYYY)</span>
            <input name="date" value="<?= $h($in['date']) ?>" placeholder="DD-MM-YYYY or DD MM YYYY" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Time (HH:MM)</span>
            <input name="time" value="<?= $h($in['time']) ?>" placeholder="HH:MM or HH MM" class="border rounded px-2 py-1"></label>

        <label class="flex flex-col gap-1 relative sm:col-span-2 lg:col-span-3"><span class="text-gray-500">Place (search city, state or country — fills lat/lon/timezone)</span>
            <input id="b-place" name="place" value="<?= $h($in['place']) ?>" type="text" autocomplete="off" placeholder="Type a city, e.g. Moga or London…" class="border rounded px-2 py-1">
            <div id="b-place-results" class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border rounded shadow max-h-60 overflow-y-auto hidden"></div></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Ayanamsa</span>
            <select name="ayanamsa" class="border rounded px-2 py-1 bg-white">
                <?php foreach (['lahiri' => 'Lahiri (Chitrapaksha)', 'raman' => 'B.V. Raman', 'kp' => 'KP (Krishnamurti)', 'fagan_bradley' => 'Fagan-Bradley'] as $av => $al): ?>
                    <option value="<?= $h($av) ?>" <?= $in['ayanamsa'] === $av ? 'selected' : '' ?>><?= $h($al) ?></option>
                <?php endforeach; ?>
            </select></label>

        <label class="flex flex-col gap-1"><span class="text-gray-500">Latitude</span>
            <input id="b-lat" name="lat" value="<?= $h($in['latIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Longitude</span>
            <input id="b-lon" name="lon" value="<?= $h($in['lonIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Timezone (east +)</span>
            <input id="b-tz" name="tz" value="<?= $h($in['tzIn']) ?>" class="border rounded px-2 py-1"></label>
        <div class="flex items-end">
            <button class="bg-blue-600 text-white rounded px-4 py-2 font-semibold w-full">Calculate</button>
        </div>
    </div>
    <p class="text-xs text-gray-500 mt-2">Search any city worldwide to fill lat/lon/timezone, or type lat/lon directly (also accept DMS, e.g. 30N48'00). Timezone is the place's offset on the birth date.</p>
</form>
