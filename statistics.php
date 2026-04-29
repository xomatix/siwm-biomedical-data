<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;
$stats = null;
$outOfNormCount = 0;
$totalMeasurements = 0;
$normInfo = null;
$measurementList = [];

$catalogStmt = $pdo->query('SELECT id, name FROM catalog ORDER BY name ASC');
$catalogItems = $catalogStmt->fetchAll();

$catalogId = (int) ($_POST['catalog_id'] ?? 0);
$dateFrom = trim($_POST['date_from'] ?? '');
$dateTo = trim($_POST['date_to'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($catalogId <= 0) {
        $error = 'Wybierz parametr.';
    } elseif ($dateFrom === '' || $dateTo === '') {
        $error = 'Wybierz zakres dat.';
    } elseif (!strtotime($dateFrom) || !strtotime($dateTo)) {
        $error = 'Podaj prawidłowe daty.';
    } else {
        $dateFromSql = $dateFrom . ' 00:00:00';
        $dateToSql = $dateTo . ' 23:59:59';

        $statsStmt = $pdo->prepare(
            'SELECT MIN(value) AS min_val, MAX(value) AS max_val, AVG(value) AS avg_val, COUNT(*) AS total_measurements
             FROM measurements
             WHERE user_id = :user_id AND catalog_id = :catalog_id AND measured_at BETWEEN :date_from AND :date_to'
        );
        $statsStmt->execute([
            'user_id' => getCurrentUserId(),
            'catalog_id' => $catalogId,
            'date_from' => $dateFromSql,
            'date_to' => $dateToSql,
        ]);
        $stats = $statsStmt->fetch();

        $normStmt = $pdo->prepare('SELECT min_value, max_value, description FROM norms WHERE catalog_id = :catalog_id LIMIT 1');
        $normStmt->execute(['catalog_id' => $catalogId]);
        $normInfo = $normStmt->fetch();

        $outStmt = $pdo->prepare(
            'SELECT COUNT(*) AS out_of_norm
             FROM measurements m
             LEFT JOIN norms n ON m.catalog_id = n.catalog_id
             WHERE m.user_id = :user_id AND m.catalog_id = :catalog_id
               AND m.measured_at BETWEEN :date_from AND :date_to
               AND (n.min_value IS NOT NULL AND m.value < n.min_value OR n.max_value IS NOT NULL AND m.value > n.max_value)'
        );
        $outStmt->execute([
            'user_id' => getCurrentUserId(),
            'catalog_id' => $catalogId,
            'date_from' => $dateFromSql,
            'date_to' => $dateToSql,
        ]);
        $outNorm = $outStmt->fetch();
        $outOfNormCount = $outNorm ? (int) $outNorm['out_of_norm'] : 0;
        $totalMeasurements = (int) ($stats['total_measurements'] ?? 0);

        $measurementDetailStmt = $pdo->prepare(
            'SELECT m.value, m.measured_at, m.notes, n.min_value, n.max_value
             FROM measurements m
             LEFT JOIN norms n ON m.catalog_id = n.catalog_id
             WHERE m.user_id = :user_id AND m.catalog_id = :catalog_id
               AND m.measured_at BETWEEN :date_from AND :date_to
             ORDER BY m.measured_at DESC'
        );
        $measurementDetailStmt->execute([
            'user_id' => getCurrentUserId(),
            'catalog_id' => $catalogId,
            'date_from' => $dateFromSql,
            'date_to' => $dateToSql,
        ]);
        $measurementList = $measurementDetailStmt->fetchAll();
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="statistics-page">
    <h1>Statystyki</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="post" action="statistics.php" class="stats-form">
        <label for="catalog_id">Parametr</label>
        <select id="catalog_id" name="catalog_id" required>
            <option value="">Wybierz parametr</option>
            <?php foreach ($catalogItems as $item): ?>
                <option value="<?php echo (int) $item['id']; ?>" <?php echo $catalogId === (int) $item['id'] ? 'selected' : ''; ?>><?php echo sanitize($item['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="date_from">Data od</label>
        <input type="date" id="date_from" name="date_from" value="<?php echo sanitize($dateFrom); ?>" required>

        <label for="date_to">Data do</label>
        <input type="date" id="date_to" name="date_to" value="<?php echo sanitize($dateTo); ?>" required>

        <button type="submit">Pokaż statystyki</button>
    </form>

    <?php if ($stats !== null): ?>
        <?php if ($totalMeasurements === 0): ?>
            <div class="info-message">Brak pomiarów w wybranym okresie.</div>
        <?php else: ?>
            <div class="stats-summary">
                <h2>Wyniki</h2>
                <p>Minimalna wartość: <strong><?php echo sanitize(number_format($stats['min_val'], 2)); ?></strong></p>
                <p>Maksymalna wartość: <strong><?php echo sanitize(number_format($stats['max_val'], 2)); ?></strong></p>
                <p>Średnia wartość: <strong><?php echo sanitize(number_format($stats['avg_val'], 2)); ?></strong></p>
                <p>Liczba pomiarów: <strong><?php echo sanitize((string) $totalMeasurements); ?></strong></p>
                <?php if ($normInfo && ($normInfo['min_value'] !== null || $normInfo['max_value'] !== null)): ?>
                    <p>Norma: <strong><?php echo sanitize($normInfo['min_value'] ?? '-'); ?> – <?php echo sanitize($normInfo['max_value'] ?? '-'); ?></strong></p>
                    <p>Przekroczenia: <strong><?php echo sanitize((string) $outOfNormCount); ?></strong>
                        <?php if ($totalMeasurements > 0): ?>
                            (<?php echo sanitize(number_format($outOfNormCount / $totalMeasurements * 100, 1)); ?>%)
                        <?php endif; ?>
                    </p>
                    <?php if ($normInfo['description'] !== null): ?>
                        <p>Opis normy: <?php echo sanitize($normInfo['description']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Brak normy dla tego parametru.</p>
                <?php endif; ?>
            </div>

            <div class="stats-details">
                <h2>Lista pomiarów</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Wartość</th>
                            <th>Data</th>
                            <th>Notatki</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($measurementList as $entry): ?>
                            <?php
                                $outside = false;
                                if ($entry['min_value'] !== null && $entry['value'] < $entry['min_value']) {
                                    $outside = true;
                                }
                                if ($entry['max_value'] !== null && $entry['value'] > $entry['max_value']) {
                                    $outside = true;
                                }
                            ?>
                            <tr class="<?php echo $outside ? 'out-of-norm' : ''; ?>">
                                <td><?php echo sanitize(number_format($entry['value'], 2)); ?></td>
                                <td><?php echo sanitize($entry['measured_at']); ?></td>
                                <td><?php echo sanitize($entry['notes']); ?></td>
                                <td><?php echo $outside ? '<strong>Poz poza normą</strong>' : 'W normie'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
