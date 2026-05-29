<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = get_db();

// Fetch all equipment ordered by muscle group (type) and name
$stmt = $pdo->query("SELECT id, type, name, status, notes FROM equipment ORDER BY type, name");
$all_equipment = $stmt->fetchAll();

// Group and aggregate by muscle group and clean equipment name
$aggregated_equipment = [];
foreach ($all_equipment as $eq) {
    $group = trim($eq['type']) ?: 'General';
    
    // Clean name: remove trailing "#1", " #2", " 1", " 2", etc.
    $clean_name = preg_replace('/(\s*#\s*\d+|\s+\d+)$/i', '', $eq['name']);
    
    if (!isset($aggregated_equipment[$group][$clean_name])) {
        $aggregated_equipment[$group][$clean_name] = [
            'total' => 0,
            'available' => 0,
            'items' => []
        ];
    }
    
    $aggregated_equipment[$group][$clean_name]['total']++;
    
    if ($eq['status'] === 'available' || $eq['status'] === 'in_use') {
        $aggregated_equipment[$group][$clean_name]['available']++;
    }
    
    $aggregated_equipment[$group][$clean_name]['items'][] = [
        'id' => (int)$eq['id'],
        'name' => $eq['name'],
        'status' => $eq['status'],
        'notes' => $eq['notes']
    ];
}

$muscle_groups = array_keys($aggregated_equipment);
sort($muscle_groups);

$page_title = 'Equipment — Fitness Down';
$page_description = 'Check real-time availability of treadmills, weights, and machines in our training areas.';
$extra_css = '/css/pages/equipment.css';
$extra_js = '/js/equipment.js';

include __DIR__ . '/includes/header.php';
?>

<main class="equipment-page">
    <section class="page-hero">
        <p class="eyebrow">Real-Time Availability</p>
        <h1 class="page-hero__title">Gym Equipment</h1>
        <p class="page-hero__sub">Check how many machines and weights are currently available in the main training areas.</p>
    </section>

    <!-- Filters -->
    <section class="filters" aria-label="Filter equipment by muscle group">
        <div class="filters__inner">
            <div class="filters__group">
                <span class="filters__label">Muscle Group</span>
                <div class="filters__buttons">
                    <button class="filter-btn filter-btn--active" data-group="all" type="button">All</button>
                    <?php foreach ($muscle_groups as $group): ?>
                        <button class="filter-btn" data-group="<?= e(strtolower($group)) ?>" type="button"><?= e($group) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Equipment List organized by muscle group -->
    <div class="equipment-groups">
        <?php if (empty($all_equipment)): ?>
            <p class="equipment-empty-msg" style="text-align: center; color: var(--text-muted); font-style: italic; margin-top: 3rem;">No equipment registered in the training area yet.</p>
        <?php else: ?>
            <?php foreach ($aggregated_equipment as $group => $items): ?>
                <section class="equipment-group-section" data-group-name="<?= e(strtolower($group)) ?>">
                    <h2 class="equipment-group-title"><?= e($group) ?></h2>
                    <div class="equipment-grid">
                        <?php foreach ($items as $name => $data): 
                            $avail = (int)$data['available'];
                            $total = (int)$data['total'];
                            
                            // Determine status classes and text
                            if ($avail === $total) {
                                $status_text = "All Available";
                                $status_class = "equipment-status--available";
                            } elseif ($avail > 0) {
                                $status_text = "Partial Maintenance";
                                $status_class = "equipment-status--partial";
                            } else {
                                $status_text = "Out of Service";
                                $status_class = "equipment-status--maintenance";
                            }
                        ?>
                            <div class="equipment-card">
                                <div class="equipment-card__header">
                                    <h3 class="equipment-name"><?= e($name) ?></h3>
                                    <span class="equipment-status <?= $status_class ?>"><?= e($status_text) ?></span>
                                </div>
                                
                                <div class="equipment-ratio">
                                    <span class="equipment-ratio__available"><?= $avail ?></span>
                                    <span class="equipment-ratio__total">/ <?= $total ?> available</span>
                                </div>

                                <div class="equipment-units">
                                    <p class="equipment-units-title">Units & Status</p>
                                    <ul class="equipment-unit-list">
                                        <?php foreach ($data['items'] as $item): 
                                            $item_status_class = 'equipment-unit-item--' . $item['status'];
                                        ?>
                                            <li class="equipment-unit-item <?= $item_status_class ?>">
                                                <div class="equipment-unit-info">
                                                    <span class="equipment-unit-name"><?= e($item['name']) ?></span>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <span class="equipment-unit-notes">— <?= e($item['notes']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="equipment-unit-status">
                                                    <span class="status-dot"></span>
                                                    <?= e(ucfirst(str_replace('_', ' ', $item['status']))) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
