<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;
$editMeasurement = null;
$measurementId = null;

$catalogStmt = $pdo->query('SELECT id, name FROM catalog ORDER BY name ASC');
$catalogItems = $catalogStmt->fetchAll();

if (isset($_GET['edit'])) {
    $measurementId = (int) $_GET['edit'];
    if ($measurementId > 0) {
        $stmt = $pdo->prepare(
            'SELECT m.id, m.catalog_id, m.value, m.measured_at, m.notes, c.name AS catalog_name, u.symbol AS unit_symbol
             FROM measurements m
             JOIN catalog c ON m.catalog_id = c.id
             LEFT JOIN units u ON c.unit_id = u.id
             WHERE m.id = :id AND m.user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['id' => $measurementId, 'user_id' => getCurrentUserId()]);
        $editMeasurement = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_measurement'])) {
        $deleteId = (int) ($_POST['measurement_id'] ?? 0);

        if ($deleteId > 0) {
            $stmt = $pdo->prepare('DELETE FROM measurements WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $deleteId, 'user_id' => getCurrentUserId()]);
            flashMessage('info', 'Pomiar został usunięty.');
        }

        redirect('measurements.php');
    }

    $catalogId = (int) ($_POST['catalog_id'] ?? 0);
    $value = trim($_POST['value'] ?? '');
    $measuredAt = trim($_POST['measured_at'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $measurementId = (int) ($_POST['measurement_id'] ?? 0);

    if ($catalogId <= 0 || $value === '' || $measuredAt === '') {
        $error = 'Wypełnij wszystkie wymagane pola.';
    } elseif (!is_numeric($value)) {
        $error = 'Wartość pomiaru musi być liczbą.';
    } else {
        if (isset($_POST['update_measurement']) && $measurementId > 0) {
            $stmt = $pdo->prepare('UPDATE measurements SET catalog_id = :catalog_id, value = :value, measured_at = :measured_at, notes = :notes WHERE id = :id AND user_id = :user_id');
            $stmt->execute([
                'catalog_id' => $catalogId,
                'value' => $value,
                'measured_at' => $measuredAt,
                'notes' => $notes,
                'id' => $measurementId,
                'user_id' => getCurrentUserId(),
            ]);
            flashMessage('info', 'Pomiar został zaktualizowany.');
            redirect('measurements.php');
        }

        if (isset($_POST['add_measurement'])) {
            $stmt = $pdo->prepare('INSERT INTO measurements (user_id, catalog_id, value, measured_at, notes) VALUES (:user_id, :catalog_id, :value, :measured_at, :notes)');
            $stmt->execute([
                'user_id' => getCurrentUserId(),
                'catalog_id' => $catalogId,
                'value' => $value,
                'measured_at' => $measuredAt,
                'notes' => $notes,
            ]);
            flashMessage('info', 'Pomiar został zapisany.');
            redirect('measurements.php');
        }
    }
}

$formCatalogId = $_POST['catalog_id'] ?? ($editMeasurement['catalog_id'] ?? '');
$formValue = $_POST['value'] ?? ($editMeasurement['value'] ?? '');
$formMeasuredAt = $_POST['measured_at'] ?? (isset($editMeasurement['measured_at']) ? date('Y-m-d\TH:i', strtotime($editMeasurement['measured_at'])) : date('Y-m-d\TH:i'));
$formNotes = $_POST['notes'] ?? ($editMeasurement['notes'] ?? '');

$measurementsStmt = $pdo->prepare(
    'SELECT m.id, m.value, m.measured_at, m.notes, c.name AS catalog_name, u.symbol AS unit_symbol
     FROM measurements m
     JOIN catalog c ON m.catalog_id = c.id
     LEFT JOIN units u ON c.unit_id = u.id
     WHERE m.user_id = :user_id
     ORDER BY m.measured_at DESC'
);
$measurementsStmt->execute(['user_id' => getCurrentUserId()]);
$measurements = $measurementsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="measurements-page">
    <h1>Pomiary</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="measurement-form">
        <h2><?php echo $editMeasurement ? 'Edytuj pomiar' : 'Dodaj pomiar'; ?></h2>
        <form method="post" action="measurements.php<?php echo $editMeasurement ? '?edit=' . (int) $editMeasurement['id'] : ''; ?>">
            <?php if ($editMeasurement): ?>
                <input type="hidden" name="measurement_id" value="<?php echo (int) $editMeasurement['id']; ?>">
            <?php endif; ?>

            <label for="catalog_id">Parametr</label>
            <?php if ($editMeasurement): ?>
                <input type="hidden" name="catalog_id" value="<?php echo (int) $editMeasurement['catalog_id']; ?>">
                <p class="readonly-field"><?php echo sanitize($editMeasurement['catalog_name'] . ' (' . $editMeasurement['unit_symbol'] . ')'); ?></p>
            <?php else: ?>
                <select id="catalog_id" name="catalog_id" required>
                    <option value="">Wybierz parametr</option>
                    <?php foreach ($catalogItems as $item): ?>
                        <option value="<?php echo (int) $item['id']; ?>" <?php echo $formCatalogId == $item['id'] ? 'selected' : ''; ?>><?php echo sanitize($item['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="value">Wartość</label>
            <input type="text" id="value" name="value" value="<?php echo sanitize($formValue); ?>" required>

            <label for="measured_at">Data i godzina</label>
            <input type="datetime-local" id="measured_at" name="measured_at" value="<?php echo sanitize($formMeasuredAt); ?>" required>

            <label for="notes">Notatki</label>
            <textarea id="notes" name="notes"><?php echo sanitize($formNotes); ?></textarea>

            <?php if ($editMeasurement): ?>
                <button type="submit" name="update_measurement">Zapisz zmiany</button>
                <a href="measurements.php" class="button-secondary">Anuluj</a>
            <?php else: ?>
                <button type="submit" name="add_measurement">Zapisz pomiar</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="measurement-list">
        <h2>Twoje pomiary</h2>
        <?php if (empty($measurements)): ?>
            <p>Brak zapisanych pomiarów.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Parametr</th>
                        <th>Wartość</th>
                        <th>Data</th>
                        <th>Notatki</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($measurements as $measurement): ?>
                        <tr>
                            <td><?php echo sanitize($measurement['catalog_name']); ?></td>
                            <td><?php echo sanitize($measurement['value']) . ' ' . sanitize($measurement['unit_symbol']); ?></td>
                            <td><?php echo sanitize($measurement['measured_at']); ?></td>
                            <td><?php echo sanitize($measurement['notes']); ?></td>
                            <td>
                                <a href="measurements.php?edit=<?php echo (int) $measurement['id']; ?>">Edytuj</a>
                                <form method="post" action="measurements.php" style="display:inline;" onsubmit="return confirm('Usuń ten pomiar?');">
                                    <input type="hidden" name="measurement_id" value="<?php echo (int) $measurement['id']; ?>">
                                    <button type="submit" name="delete_measurement">Usuń</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
