<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;

$selectedCatalogId = (int) ($_GET['catalog_id'] ?? $_POST['catalog_id'] ?? 0);
$selectedCatalog = null;
$currentUserId = getCurrentUserId();

if ($selectedCatalogId > 0) {
    $stmt = $pdo->prepare(
        'SELECT c.id, c.name, c.created_by, u.symbol AS unit_symbol, n.id AS norm_id, n.min_value, n.max_value, n.description
         FROM catalog c
         LEFT JOIN units u ON c.unit_id = u.id
         LEFT JOIN norms n ON c.id = n.catalog_id
         WHERE c.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $selectedCatalogId]);
    $selectedCatalog = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catalogId = (int) ($_POST['catalog_id'] ?? 0);
    $minValue = trim($_POST['min_value'] ?? '');
    $maxValue = trim($_POST['max_value'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($catalogId <= 0) {
        $error = 'Wybierz parametr katalogu.';
    } elseif ($minValue !== '' && !is_numeric($minValue)) {
        $error = 'Minimalna wartość musi być liczbą lub pusta.';
    } elseif ($maxValue !== '' && !is_numeric($maxValue)) {
        $error = 'Maksymalna wartość musi być liczbą lub pusta.';
    } else {
        $ownerStmt = $pdo->prepare('SELECT created_by FROM catalog WHERE id = :id LIMIT 1');
        $ownerStmt->execute(['id' => $catalogId]);
        $owner = $ownerStmt->fetch();

        if (!$owner || $owner['created_by'] !== $currentUserId) {
            $error = 'Tylko autor parametru może ustawić normę.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO norms (catalog_id, min_value, max_value, description)
                 VALUES (:catalog_id, :min_value, :max_value, :description)
                 ON DUPLICATE KEY UPDATE min_value = :min_value2, max_value = :max_value2, description = :description2'
            );

            $stmt->execute([
                'catalog_id' => $catalogId,
                'min_value' => $minValue !== '' ? $minValue : null,
                'max_value' => $maxValue !== '' ? $maxValue : null,
                'description' => $description !== '' ? $description : null,
                'min_value2' => $minValue !== '' ? $minValue : null,
                'max_value2' => $maxValue !== '' ? $maxValue : null,
                'description2' => $description !== '' ? $description : null,
            ]);

            flashMessage('info', 'Norma została zapisana.');
            redirect('norms.php?catalog_id=' . $catalogId);
        }
    }
}

$catalogStmt = $pdo->prepare('SELECT id, name FROM catalog WHERE created_by = :created_by ORDER BY name ASC');
$catalogStmt->execute(['created_by' => $currentUserId]);
$catalogItems = $catalogStmt->fetchAll();

$normsStmt = $pdo->query(
    'SELECT c.id AS catalog_id, c.name AS catalog_name, c.created_by, u.symbol AS unit_symbol, n.min_value, n.max_value, n.description
     FROM catalog c
     LEFT JOIN units u ON c.unit_id = u.id
     LEFT JOIN norms n ON c.id = n.catalog_id
     ORDER BY c.name ASC'
);
$norms = $normsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="norms-page">
    <h1>Normy parametrów</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="norm-form">
        <h2><?php echo $selectedCatalog ? 'Edytuj normę' : 'Ustaw normę'; ?></h2>
        <form method="post" action="norms.php<?php echo $selectedCatalog ? '?catalog_id=' . (int) $selectedCatalog['id'] : ''; ?>">
            <?php if ($selectedCatalog): ?>
                <input type="hidden" name="catalog_id" value="<?php echo (int) $selectedCatalog['id']; ?>">
                <p class="readonly-field"><?php echo sanitize($selectedCatalog['name'] . ' (' . $selectedCatalog['unit_symbol'] . ')'); ?></p>
                <?php if ($selectedCatalog['created_by'] !== $currentUserId): ?>
                    <div class="error-message">Tylko autor parametru może ustawić lub edytować normę.</div>
                <?php endif; ?>
            <?php else: ?>
                <label for="catalog_id">Parametr</label>
                <select id="catalog_id" name="catalog_id" required>
                    <option value="">Wybierz parametr</option>
                    <?php foreach ($catalogItems as $item): ?>
                        <option value="<?php echo (int) $item['id']; ?>" <?php echo $selectedCatalogId === (int) $item['id'] ? 'selected' : ''; ?>><?php echo sanitize($item['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if (!$selectedCatalog || $selectedCatalog['created_by'] === $currentUserId): ?>
                <label for="min_value">Minimalna wartość</label>
                <input type="text" id="min_value" name="min_value" value="<?php echo sanitize($_POST['min_value'] ?? ($selectedCatalog['min_value'] ?? '')); ?>">

                <label for="max_value">Maksymalna wartość</label>
                <input type="text" id="max_value" name="max_value" value="<?php echo sanitize($_POST['max_value'] ?? ($selectedCatalog['max_value'] ?? '')); ?>">

                <label for="description">Opis normy</label>
                <textarea id="description" name="description"><?php echo sanitize($_POST['description'] ?? ($selectedCatalog['description'] ?? '')); ?></textarea>

                <button type="submit">Zapisz normę</button>
            <?php else: ?>
                <p>Nie masz uprawnień do edycji norm tego parametru.</p>
            <?php endif; ?>
        </form>
    </div>

    <div class="norms-list">
        <h2>Wszystkie pozycje katalogu</h2>
        <?php if (empty($norms)): ?>
            <p>Brak pozycji w katalogu.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Parametr</th>
                        <th>Norma</th>
                        <th>Opis</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($norms as $item): ?>
                        <tr>
                            <td><?php echo sanitize($item['catalog_name']); ?></td>
                            <td>
                                <?php if ($item['min_value'] !== null || $item['max_value'] !== null): ?>
                                    <?php echo sanitize($item['min_value'] ?? '-'); ?>
                                    –
                                    <?php echo sanitize($item['max_value'] ?? '-'); ?>
                                    <?php echo sanitize($item['unit_symbol'] ?? ''); ?>
                                <?php else: ?>
                                    <span>Brak normy</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($item['description'] ?? ''); ?></td>
                            <td>
                                <?php if ($item['created_by'] === $currentUserId): ?>
                                    <?php if ($item['min_value'] !== null || $item['max_value'] !== null): ?>
                                        <a href="norms.php?catalog_id=<?php echo (int) $item['catalog_id']; ?>" class="icon-button" title="Edytuj" aria-label="Edytuj">
                                            <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M2 14.5V18h3.5l9.8-9.8-3.5-3.5L2 14.5Z"/><path d="M17.7 4.8a1 1 0 0 0 0-1.4l-1-1a1 1 0 0 0-1.4 0l-1.8 1.8 2.5 2.5 1.8-1.8Z"/></svg>
                                        </a>
                                    <?php else: ?>
                                        <a href="norms.php?catalog_id=<?php echo (int) $item['catalog_id']; ?>">Ustaw</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>Brak uprawnień</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php';