<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;
success:
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_unit'])) {
        $name = trim($_POST['name'] ?? '');
        $symbol = trim($_POST['symbol'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $symbol === '') {
            $error = 'Nazwa i symbol są wymagane.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO units (name, symbol, description, created_by) VALUES (:name, :symbol, :description, :created_by)');
            $stmt->execute([
                'name' => $name,
                'symbol' => $symbol,
                'description' => $description,
                'created_by' => getCurrentUserId(),
            ]);
            flashMessage('info', 'Jednostka została dodana.');
            redirect('units.php');
        }
    }

    if (isset($_POST['update_unit'])) {
        $id = (int) ($_POST['unit_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $symbol = trim($_POST['symbol'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($id <= 0 || $name === '' || $symbol === '') {
            $error = 'Nazwa i symbol są wymagane.';
        } else {
            $stmt = $pdo->prepare('UPDATE units SET name = :name, symbol = :symbol, description = :description WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'symbol' => $symbol,
                'description' => $description,
                'id' => $id,
            ]);
            flashMessage('info', 'Jednostka została zaktualizowana.');
            redirect('units.php');
        }
    }

    if (isset($_POST['delete_unit'])) {
        $id = (int) ($_POST['unit_id'] ?? 0);

        if ($id <= 0) {
            $error = 'Nieprawidłowy identyfikator jednostki.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM catalog WHERE unit_id = :id');
            $stmt->execute(['id' => $id]);
            $count = (int) $stmt->fetchColumn();

            if ($count > 0) {
                $error = 'Nie można usunąć jednostki użytej w katalogu.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM units WHERE id = :id');
                $stmt->execute(['id' => $id]);
                flashMessage('info', 'Jednostka została usunięta.');
                redirect('units.php');
            }
        }
    }
}

$editUnit = null;
if (isset($_GET['edit'])) {
    $unitId = (int) $_GET['edit'];
    if ($unitId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $unitId]);
        $editUnit = $stmt->fetch();
    }
}

$unitsStmt = $pdo->query('SELECT u.id, u.name, u.symbol, u.description, u.created_at FROM units u ORDER BY u.name ASC');
$units = $unitsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="units-page">
    <h1>Jednostki</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="unit-form">
        <h2><?php echo $editUnit ? 'Edytuj jednostkę' : 'Dodaj nową jednostkę'; ?></h2>
        <form method="post" action="units.php<?php echo $editUnit ? '?edit=' . (int) $editUnit['id'] : ''; ?>">
            <?php if ($editUnit): ?>
                <input type="hidden" name="unit_id" value="<?php echo (int) $editUnit['id']; ?>">
            <?php endif; ?>

            <label for="name">Nazwa</label>
            <input type="text" id="name" name="name" value="<?php echo sanitize($editUnit['name'] ?? ''); ?>" required>

            <label for="symbol">Symbol</label>
            <input type="text" id="symbol" name="symbol" value="<?php echo sanitize($editUnit['symbol'] ?? ''); ?>" required>

            <label for="description">Opis</label>
            <textarea id="description" name="description"><?php echo sanitize($editUnit['description'] ?? ''); ?></textarea>

            <?php if ($editUnit): ?>
                <button type="submit" name="update_unit">Zapisz zmiany</button>
                <a href="units.php" class="button-secondary">Anuluj</a>
            <?php else: ?>
                <button type="submit" name="add_unit">Dodaj jednostkę</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="unit-list">
        <h2>Lista jednostek</h2>
        <?php if (empty($units)): ?>
            <p>Brak jednostek w bazie.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Symbol</th>
                        <th>Opis</th>
                        <th>Utworzono</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><?php echo sanitize($unit['name']); ?></td>
                            <td><?php echo sanitize($unit['symbol']); ?></td>
                            <td><?php echo sanitize($unit['description']); ?></td>
                            <td><?php echo sanitize($unit['created_at']); ?></td>
                            <td>
                                <a href="units.php?edit=<?php echo (int) $unit['id']; ?>" class="icon-button" title="Edytuj" aria-label="Edytuj">
                                    <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M2 14.5V18h3.5l9.8-9.8-3.5-3.5L2 14.5Z"/><path d="M17.7 4.8a1 1 0 0 0 0-1.4l-1-1a1 1 0 0 0-1.4 0l-1.8 1.8 2.5 2.5 1.8-1.8Z"/></svg>
                                </a>
                                <form method="post" action="units.php" style="display:inline;" onsubmit="return confirm('Usuń tę jednostkę?');">
                                    <input type="hidden" name="unit_id" value="<?php echo (int) $unit['id']; ?>">
                                    <button type="submit" name="delete_unit" class="icon-button delete" title="Usuń" aria-label="Usuń">
                                        <svg viewBox="0 0 875 1000" aria-hidden="true" focusable="false"><path d="M0 281.296l0 -68.355q1.953 -37.107 29.295 -62.496t64.449 -25.389l93.744 0l0 -31.248q0 -39.06 27.342 -66.402t66.402 -27.342l312.48 0q39.06 0 66.402 27.342t27.342 66.402l0 31.248l93.744 0q37.107 0 64.449 25.389t29.295 62.496l0 68.355q0 25.389 -18.553 43.943t-43.943 18.553l0 531.216q0 52.731 -36.13 88.862t-88.862 36.13l-499.968 0q-52.731 0 -88.862 -36.13t-36.13 -88.862l0 -531.216q-25.389 0 -43.943 -18.553t-18.553 -43.943zm62.496 0l749.952 0l0 -62.496q0 -13.671 -8.789 -22.46t-22.46 -8.789l-687.456 0q-13.671 0 -22.46 8.789t-8.789 22.46l0 62.496zm62.496 593.712q0 25.389 18.553 43.943t43.943 18.553l499.968 0q25.389 0 43.943 -18.553t18.553 -43.943l0 -531.216l-624.96 0l0 531.216zm62.496 -31.248l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224zm31.248 -718.704l374.976 0l0 -31.248q0 -13.671 -8.789 -22.46t-22.46 -8.789l-312.48 0q-13.671 0 -22.46 8.789t-8.789 22.46l0 31.248zm124.992 718.704l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224zm156.24 0l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224z" fill="currentColor"/></svg>
                                    </button>
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
