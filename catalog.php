<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$pdo = getDB();
$error = null;

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
    'SELECT c.id, c.name, c.description, u.name AS unit_name, u.symbol AS unit_symbol, c.created_by, c.created_at, usr.email AS creator_email
     FROM catalog c
     LEFT JOIN units u ON c.unit_id = u.id
     LEFT JOIN users usr ON c.created_by = usr.id
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
                            <td><?php echo sanitize($item['creator_email'] ?? ''); ?></td>
                            <td>
                                <?php if ($item['created_by'] === getCurrentUserId()): ?>
                                    <a href="catalog.php?edit=<?php echo (int) $item['id']; ?>">Edytuj</a>
                                    <form method="post" action="catalog.php" style="display:inline;" onsubmit="return confirm('Usuń tę pozycję?');">
                                        <input type="hidden" name="catalog_id" value="<?php echo (int) $item['id']; ?>">
                                        <button type="submit" name="delete_catalog">Usuń</button>
                                    </form>
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
