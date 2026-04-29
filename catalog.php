<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;
$currentUserId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_catalog'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $userId = getCurrentUserId();

        if ($name === '' || $unitId <= 0) {
            $error = 'Nazwa i jednostka są wymagane.';
        } else {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM catalog WHERE created_by = :user_id');
            $countStmt->execute(['user_id' => $userId]);
            $count = (int) $countStmt->fetchColumn();

            if ($count >= 5) {
                $error = 'Osiągnięto limit 5 pozycji katalogu na użytkownika.';
            } else {
                $insert = $pdo->prepare('INSERT INTO catalog (name, description, unit_id, created_by) VALUES (:name, :description, :unit_id, :created_by)');
                $insert->execute([
                    'name' => $name,
                    'description' => $description,
                    'unit_id' => $unitId,
                    'created_by' => $userId,
                ]);
                flashMessage('info', 'Pozycja katalogu została dodana.');
                redirect('catalog.php');
            }
        }
    }

    if (isset($_POST['update_catalog'])) {
        $catalogId = (int) ($_POST['catalog_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $userId = getCurrentUserId();

        $authStmt = $pdo->prepare('SELECT created_by FROM catalog WHERE id = :id LIMIT 1');
        $authStmt->execute(['id' => $catalogId]);
        $catalogRow = $authStmt->fetch();

        if (!$catalogRow || $catalogRow['created_by'] !== $userId) {
            $error = 'Nie masz uprawnień do edycji tej pozycji.';
        } elseif ($name === '' || $unitId <= 0) {
            $error = 'Nazwa i jednostka są wymagane.';
        } else {
            $update = $pdo->prepare('UPDATE catalog SET name = :name, description = :description, unit_id = :unit_id WHERE id = :id');
            $update->execute([
                'name' => $name,
                'description' => $description,
                'unit_id' => $unitId,
                'id' => $catalogId,
            ]);
            flashMessage('info', 'Pozycja katalogu została zaktualizowana.');
            redirect('catalog.php');
        }
    }

    if (isset($_POST['delete_catalog'])) {
        $catalogId = (int) ($_POST['catalog_id'] ?? 0);
        $userId = getCurrentUserId();

        $authStmt = $pdo->prepare('SELECT created_by FROM catalog WHERE id = :id LIMIT 1');
        $authStmt->execute(['id' => $catalogId]);
        $catalogRow = $authStmt->fetch();

        if (!$catalogRow || $catalogRow['created_by'] !== $userId) {
            $error = 'Nie masz uprawnień do usunięcia tej pozycji.';
        } else {
            $delete = $pdo->prepare('DELETE FROM catalog WHERE id = :id');
            $delete->execute(['id' => $catalogId]);
            flashMessage('info', 'Pozycja katalogu została usunięta.');
            redirect('catalog.php');
        }
    }
}

$editCatalog = null;
if (isset($_GET['edit'])) {
    $catalogId = (int) $_GET['edit'];
    if ($catalogId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM catalog WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $catalogId]);
        $editCatalog = $stmt->fetch();
    }
}

$unitsStmt = $pdo->query('SELECT id, name, symbol FROM units ORDER BY name ASC');
$units = $unitsStmt->fetchAll();

$catalogStmt = $pdo->query(
    'SELECT c.id, c.name, c.description, u.name AS unit_name, u.symbol AS unit_symbol, c.created_by, c.created_at, usr.email AS creator_email, n.id AS norm_id
     FROM catalog c
     LEFT JOIN units u ON c.unit_id = u.id
     LEFT JOIN users usr ON c.created_by = usr.id
     LEFT JOIN norms n ON c.id = n.catalog_id
     ORDER BY c.name ASC'
);
$catalogItems = $catalogStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="catalog-page">
    <h1>Katalog badań</h1>

    <?php if ($error !== null): ?>
        <div class="error-message"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <div class="catalog-form">
        <h2><?php echo $editCatalog ? 'Edytuj pozycję katalogu' : 'Dodaj nową pozycję'; ?></h2>
        <form method="post" action="catalog.php<?php echo $editCatalog ? '?edit=' . (int) $editCatalog['id'] : ''; ?>">
            <?php if ($editCatalog): ?>
                <input type="hidden" name="catalog_id" value="<?php echo (int) $editCatalog['id']; ?>">
            <?php endif; ?>

            <label for="name">Nazwa</label>
            <input type="text" id="name" name="name" value="<?php echo sanitize($editCatalog['name'] ?? ''); ?>" required>

            <label for="description">Opis</label>
            <textarea id="description" name="description"><?php echo sanitize($editCatalog['description'] ?? ''); ?></textarea>

            <label for="unit_id">Jednostka</label>
            <select id="unit_id" name="unit_id" required>
                <option value="">Wybierz jednostkę</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?php echo (int) $unit['id']; ?>" <?php echo isset($editCatalog['unit_id']) && $editCatalog['unit_id'] == $unit['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($unit['name'] . ' (' . $unit['symbol'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($editCatalog): ?>
                <button type="submit" name="update_catalog">Zapisz zmiany</button>
                <a href="catalog.php" class="button-secondary">Anuluj</a>
            <?php else: ?>
                <button type="submit" name="add_catalog">Dodaj pozycję</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="catalog-list">
        <h2>Lista pozycji</h2>
        <?php if (empty($catalogItems)): ?>
            <p>Brak pozycji w katalogu.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                                <th>Nazwa</th>
                        <th>Opis</th>
                        <th>Jednostka</th>
                        <th>Norma</th>
                        <th>Utworzone przez</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogItems as $item): ?>
                        <tr>
                            <td><?php echo sanitize($item['name']); ?></td>
                            <td><?php echo sanitize($item['description']); ?></td>
                            <td><?php echo sanitize($item['unit_name'] . ' (' . $item['unit_symbol'] . ')'); ?></td>
                            <td><?php echo $item['norm_id'] ? 'Tak' : 'Brak'; ?></td>
                            <td><?php echo sanitize($item['creator_email'] ?? ''); ?></td>
                            <td>
                                <?php if ($item['created_by'] === $currentUserId): ?>
                                    <?php if ($item['norm_id']): ?>
                                        <a href="norms.php?catalog_id=<?php echo (int) $item['id']; ?>" class="icon-button" title="Edytuj normę" aria-label="Edytuj normę">
                                            <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M2 14.5V18h3.5l9.8-9.8-3.5-3.5L2 14.5Z"/><path d="M17.7 4.8a1 1 0 0 0 0-1.4l-1-1a1 1 0 0 0-1.4 0l-1.8 1.8 2.5 2.5 1.8-1.8Z"/></svg>
                                        </a>
                                    <?php else: ?>
                                        <a href="norms.php?catalog_id=<?php echo (int) $item['id']; ?>">Ustaw normę</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($item['created_by'] === $currentUserId): ?>
                                    <a href="catalog.php?edit=<?php echo (int) $item['id']; ?>" class="icon-button" title="Edytuj" aria-label="Edytuj">
                                        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M2 14.5V18h3.5l9.8-9.8-3.5-3.5L2 14.5Z"/><path d="M17.7 4.8a1 1 0 0 0 0-1.4l-1-1a1 1 0 0 0-1.4 0l-1.8 1.8 2.5 2.5 1.8-1.8Z"/></svg>
                                    </a>
                                    <form method="post" action="catalog.php" style="display:inline;" onsubmit="return confirm('Usuń tę pozycję?');">
                                        <input type="hidden" name="catalog_id" value="<?php echo (int) $item['id']; ?>">
                                        <button type="submit" name="delete_catalog" class="icon-button delete" title="Usuń" aria-label="Usuń">
                                            <svg viewBox="0 0 875 1000" aria-hidden="true" focusable="false"><path d="M0 281.296l0 -68.355q1.953 -37.107 29.295 -62.496t64.449 -25.389l93.744 0l0 -31.248q0 -39.06 27.342 -66.402t66.402 -27.342l312.48 0q39.06 0 66.402 27.342t27.342 66.402l0 31.248l93.744 0q37.107 0 64.449 25.389t29.295 62.496l0 68.355q0 25.389 -18.553 43.943t-43.943 18.553l0 531.216q0 52.731 -36.13 88.862t-88.862 36.13l-499.968 0q-52.731 0 -88.862 -36.13t-36.13 -88.862l0 -531.216q-25.389 0 -43.943 -18.553t-18.553 -43.943zm62.496 0l749.952 0l0 -62.496q0 -13.671 -8.789 -22.46t-22.46 -8.789l-687.456 0q-13.671 0 -22.46 8.789t-8.789 22.46l0 62.496zm62.496 593.712q0 25.389 18.553 43.943t43.943 18.553l499.968 0q25.389 0 43.943 -18.553t18.553 -43.943l0 -531.216l-624.96 0l0 531.216zm62.496 -31.248l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224zm31.248 -718.704l374.976 0l0 -31.248q0 -13.671 -8.789 -22.46t-22.46 -8.789l-312.48 0q-13.671 0 -22.46 8.789t-8.789 22.46l0 31.248zm124.992 718.704l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224zm156.24 0l0 -406.224q0 -13.671 8.789 -22.46t22.46 -8.789l62.496 0q13.671 0 22.46 8.789t8.789 22.46l0 406.224q0 13.671 -8.789 22.46t-22.46 8.789l-62.496 0q-13.671 0 -22.46 -8.789t-8.789 -22.46zm31.248 0l62.496 0l0 -406.224l-62.496 0l0 406.224z" fill="currentColor"/></svg>
                                        </button>
                                    </form>
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
