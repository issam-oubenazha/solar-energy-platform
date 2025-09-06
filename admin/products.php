<?php
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
$auth->requireAdmin();

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Paramètres de filtrage et tri
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

// Validation du tri
$allowed_sort_fields = ['name', 'price', 'stock', 'category_id'];
if (!in_array($sort, $allowed_sort_fields)) {
    $sort = 'name';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'ASC';
}

try {
    // Construction de la requête de base
    $query = "FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE 1=1";
    $params = [];

    // Ajout des conditions de recherche
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if (!empty($category_id)) {
        $query .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    // Compte total des produits
    $count_stmt = $pdo->prepare("SELECT COUNT(*) " . $query);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Récupération des produits
    $query = "SELECT p.*, c.name as category_name " . $query . 
             " ORDER BY p.{$sort} {$order} 
               LIMIT {$items_per_page} OFFSET {$offset}";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Récupération des catégories pour le filtre
    $categories_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $categories_stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des produits : " . $e->getMessage();
}

// Gestion de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['delete_product'];
    try {
        // Récupérer l'image du produit
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        // Supprimer l'image si elle existe
        if ($product && $product['image_url'] && file_exists('../' . $product['image_url'])) {
            unlink('../' . $product['image_url']);
        }

        // Supprimer le produit
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);

        header('Location: products.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression du produit : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- NavBar -->
        <nav class="bg-gradient-to-r from-blue-600 to-blue-400 shadow-xl rounded-b-xl transition-all duration-500 animate-fade-in-down mb-4">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-extrabold text-white tracking-wide drop-shadow-lg">Gestion des produits</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-home mr-2"></i>Tableau de bord
                        </a>
                        <a href="categories.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-tags mr-2"></i>Catégories
                        </a>
                        <a href="add_product.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-plus mr-2"></i>Ajouter produit
                        </a>
                        <a href="manage_admins.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                            <i class="fas fa-user-shield mr-2"></i>Admins
                        </a>
                        <a href="logout.php" class="bg-red-500 text-white font-semibold px-4 py-2 rounded hover:bg-red-700 transition flex items-center">
                            <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                        </a>x
                    </div>
                </div>
            </div>
        </nav>
        <!-- En-tête -->
        <header class="bg-white shadow rounded-b-xl">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4
                            bg-gradient-to-r from-blue-50 via-white to-green-50 border border-blue-100 rounded-2xl shadow-lg p-6
                            transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <a href="dashboard.php"
                           class="flex items-center gap-2 bg-gradient-to-r from-gray-600 to-gray-800 hover:from-gray-700 hover:to-gray-900 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <i class="fas fa-arrow-left text-xl"></i>
                            Retour au tableau de bord
                        </a>
                        <h2 class="text-3xl font-extrabold bg-gradient-to-r from-blue-600 via-green-500 to-blue-400 bg-clip-text text-transparent tracking-widest uppercase drop-shadow-lg animate-fade-in-up">
                            Gestion des produits
                        </h2>
                    </div>
                    <a href="add_product.php"
                       class="flex items-center gap-2 bg-gradient-to-r from-green-500 to-blue-500 hover:from-green-600 hover:to-blue-600 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-green-400">
                        <i class="fas fa-plus text-xl"></i>
                        Ajouter un produit
                    </a>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">Le produit a été supprimé avec succès.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filtres et recherche -->
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-8">
            <form method="GET" class="bg-white shadow rounded-xl p-6 mb-6 transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Rechercher</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="Nom ou description...">
                    </div>

                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Catégorie</label>
                        <select name="category_id" id="category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"
                                        <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700">Trier par</label>
                        <select name="sort" id="sort"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nom</option>
                            <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Prix</option>
                            <option value="stock" <?php echo $sort === 'stock' ? 'selected' : ''; ?>>Stock</option>
                            <option value="category_id" <?php echo $sort === 'category_id' ? 'selected' : ''; ?>>Catégorie</option>
                        </select>
                    </div>

                    <div>
                        <label for="order" class="block text-sm font-medium text-gray-700">Ordre</label>
                        <select name="order" id="order"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Croissant</option>
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Décroissant</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition-transform duration-200 hover:scale-105 shadow">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                </div>
            </form>

            <!-- Liste des produits -->
            <div class="bg-white shadow rounded-xl overflow-hidden transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                <table class="min-w-full rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-gradient-to-r from-green-500 to-green-700">
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Image</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nom</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Catégorie</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Prix</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-green-50 transition-all duration-300 hover:scale-[1.01]">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($product['image_url']); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="h-16 w-16 object-cover rounded shadow">
                                    <?php else: ?>
                                        <div class="h-16 w-16 bg-gray-100 flex items-center justify-center rounded">
                                            <i class="fas fa-image text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($product['category_name'] ?? 'Non catégorisé'); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-green-700 font-semibold"><?php echo number_format($product['price'], 2, ',', ' '); ?> €</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm <?php echo $product['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>"
                                       class="text-blue-600 hover:text-blue-900 mr-4 font-semibold transition-transform duration-200 hover:scale-110">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="delete_product" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 font-semibold transition-transform duration-200 hover:scale-110">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-4 flex justify-center animate-fade-in-up">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $page === $i ? 'text-blue-600 bg-blue-50 font-bold' : 'text-gray-700 hover:bg-blue-100 hover:text-blue-700'; ?> transition-all duration-200">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(30px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        @keyframes fade-in-down {
            0% { opacity: 0; transform: translateY(-30px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        @keyframes fade-in {
            0% { opacity: 0;}
            100% { opacity: 1;}
        }
        .animate-fade-in-up { animation: fade-in-up 0.7s cubic-bezier(.4,0,.2,1) both; }
        .animate-fade-in-down { animation: fade-in-down 0.7s cubic-bezier(.4,0,.2,1) both; }
        .animate-fade-in { animation: fade-in 0.7s cubic-bezier(.4,0,.2,1) both; }
    </style>
</body>
</html>