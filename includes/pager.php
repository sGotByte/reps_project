<?php
declare(strict_types=1);

/**
 * แถบแบ่งหน้าฝั่ง server (หน้าที่ข้อมูลเยอะและแบ่งด้วย LIMIT/OFFSET)
 * หน้าตาเดียวกับ assets/js/table-pager.js (คลาส reps-pager ใน base.css)
 */

/** อ่านจำนวนแถวต่อหน้าจาก ?per= (รับเฉพาะค่าที่อนุญาต) */
function pager_per_page(array $options, int $default): int
{
    $per = filter_input(INPUT_GET, 'per', FILTER_VALIDATE_INT);
    return in_array($per, $options, true) ? (int)$per : $default;
}

/**
 * @param callable(int $page): string $urlFor  สร้าง URL ของหน้าที่ระบุ (ต้องรวมตัวกรองเดิมและ per ไว้แล้ว)
 * @param array<string,string> $keep           พารามิเตอร์ที่ต้องส่งต่อเมื่อเปลี่ยนจำนวนแถว (เช่น status, q)
 */
function pager_render(string $action, int $total, int $page, int $perPage, array $options, callable $urlFor, array $keep, string $label = 'แบ่งหน้า'): void
{
    if ($total <= min($options)) {
        return;
    }
    $pages = max(1, (int)ceil($total / $perPage));
    $start = ($page - 1) * $perPage + 1;
    $end = min($total, $page * $perPage);
    $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $list = array_unique(array_filter([1, 2, $page - 1, $page, $page + 1, $pages - 1, $pages], static fn($p) => $p >= 1 && $p <= $pages));
    sort($list);
    ?>
    <nav class="reps-pager" aria-label="<?= $e($label) ?>">
        <span class="reps-pager__info">แสดง <?= number_format($start) ?>–<?= number_format($end) ?> จาก <?= number_format($total) ?></span>
        <form method="get" action="<?= $e($action) ?>" class="reps-pager__size">
            <?php foreach ($keep as $k => $v): if ($v === '' || $v === null) continue; ?>
                <input type="hidden" name="<?= $e($k) ?>" value="<?= $e($v) ?>">
            <?php endforeach; ?>
            <label for="per-<?= $e(md5($action)) ?>">แถวต่อหน้า</label>
            <select id="per-<?= $e(md5($action)) ?>" name="per" data-customized="true" onchange="this.form.submit()">
                <?php foreach ($options as $opt): ?>
                    <option value="<?= (int)$opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= (int)$opt ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">ใช้</button></noscript>
        </form>
        <div class="reps-pager__pages">
            <?php if ($page > 1): ?>
                <a class="reps-pager__btn" href="<?= $e($urlFor($page - 1)) ?>" aria-label="หน้าก่อนหน้า">‹</a>
            <?php else: ?>
                <span class="reps-pager__btn" aria-disabled="true">‹</span>
            <?php endif; ?>
            <?php $prev = 0; foreach ($list as $p): ?>
                <?php if ($p - $prev > 1): ?><span class="reps-pager__gap">…</span><?php endif; ?>
                <?php if ($p === $page): ?>
                    <span class="reps-pager__btn is-current" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                    <a class="reps-pager__btn" href="<?= $e($urlFor($p)) ?>" aria-label="หน้า <?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php $prev = $p; endforeach; ?>
            <?php if ($page < $pages): ?>
                <a class="reps-pager__btn" href="<?= $e($urlFor($page + 1)) ?>" aria-label="หน้าถัดไป">›</a>
            <?php else: ?>
                <span class="reps-pager__btn" aria-disabled="true">›</span>
            <?php endif; ?>
        </div>
    </nav>
    <?php
}
