<?php
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
$auth->requireAdmin();

// Traitement de la suppression de produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['delete_product'];
    try {
        // Récupérer l'image du produit avant la suppression
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        // Supprimer le produit
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);

        // Supprimer l'image si elle existe
        if ($product && $product['image_url'] && file_exists('../' . $product['image_url'])) {
            unlink('../' . $product['image_url']);
        }

        // Rediriger pour éviter la resoumission du formulaire
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression du produit : " . $e->getMessage();
    }
}

// Vérification et création des tables si elles n'existent pas
try {
    // Création de la table categories si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Création de la table products si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )");

    // Vérifier si des catégories existent, sinon en créer
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($categoryCount == 0) {
        $pdo->exec("INSERT INTO categories (name, description) VALUES
            ('Panneaux Solaires', 'Panneaux photovoltaïques pour la production d''électricité'),
            ('Batteries', 'Systèmes de stockage d''énergie solaire'),
            ('Onduleurs', 'Convertisseurs pour systèmes solaires'),
            ('Accessoires', 'Câbles, supports et autres accessoires d''installation')
        ");
    }

    // Statistiques avec gestion des erreurs
    try {
        $stats = [
            'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'total_categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
            'total_value' => $pdo->query("SELECT COALESCE(SUM(price * stock), 0) FROM products")->fetchColumn(),
            'low_stock' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 5")->fetchColumn()
        ];
    } catch (PDOException $e) {
        $stats = [
            'total_products' => 0,
            'total_categories' => 0,
            'total_value' => 0,
            'low_stock' => 0
        ];
    }

    // Récupération des produits avec leurs catégories
    try {
        $stmt = $pdo->query("SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            ORDER BY p.created_at DESC");
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $products = [];
    }

    // Récupération des produits par catégorie pour le graphique
    try {
        $products_by_category = $pdo->query("SELECT c.name, COUNT(p.id) as count 
                                           FROM categories c 
                                           LEFT JOIN products p ON c.id = p.category_id 
                                           GROUP BY c.id")->fetchAll();
    } catch (PDOException $e) {
        $products_by_category = [];
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion Produits Solaires</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <nav class="bg-gradient-to-r from-blue-600 to-blue-400 shadow-xl rounded-b-xl transition-all duration-500 animate-fade-in-down">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-extrabold text-white tracking-wide drop-shadow-lg">Tableau de bord</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition">Tableau de bord</a>
                        <a href="categories.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition">Gérer les catégories</a>
                        <a href="add_product.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition">Gérer les produits</a>

                        <a href="manage_admins.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition">Gérer les administrateurs</a>
                        <a href="logout.php" class="bg-red-500 text-white font-semibold px-4 py-2 rounded hover:bg-red-700 transition">Déconnexion</a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-8">
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 transition-all duration-300 animate-fade-in">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Total Produits -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-700 shadow-lg rounded-xl p-6 transition-transform transform hover:scale-105 hover:shadow-2xl duration-300 animate-fade-in-up">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white text-lg font-semibold">Total Produits</div>
                            <div class="text-2xl font-bold text-white"><?php echo $stats['total_products']; ?></div>
                        </div>
                    </div>
                </div>
                <!-- Catégories -->
                <div class="bg-gradient-to-br from-green-500 to-green-700 shadow-lg rounded-xl p-6 transition-transform transform hover:scale-105 hover:shadow-2xl duration-300 animate-fade-in-up delay-100">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white text-lg font-semibold">Catégories</div>
                            <div class="text-2xl font-bold text-white"><?php echo $stats['total_categories']; ?></div>
                        </div>
                    </div>
                </div>
                <!-- Valeur du Stock -->
                <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 shadow-lg rounded-xl p-6 transition-transform transform hover:scale-105 hover:shadow-2xl duration-300 animate-fade-in-up delay-200">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white text-lg font-semibold">Valeur du Stock</div>
                            <div class="text-2xl font-bold text-white"><?php echo number_format($stats['total_value'], 2, ',', ' ') . ' DH'; ?></div>
                        </div>
                    </div>
                </div>
                <!-- Stock Faible -->
                <div class="bg-gradient-to-br from-red-500 to-red-700 shadow-lg rounded-xl p-6 transition-transform transform hover:scale-105 hover:shadow-2xl duration-300 animate-fade-in-up delay-300">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-white text-lg font-semibold">Stock Faible</div>
                            <div class="text-2xl font-bold text-white"><?php echo $stats['low_stock']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Répartition des produits par catégorie (Tableau) -->
            <div class="bg-white shadow rounded-xl p-6 mb-6 transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                <h2 class="text-xl font-bold mb-4">Répartition des produits par catégorie</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full rounded-xl overflow-hidden">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-500 to-blue-700">
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Catégorie</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nombre de produits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products_by_category as $cat): ?>
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 font-medium">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-blue-700 font-semibold">
                                    <?php echo (int)$cat['count']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Liste des produits -->
            <div class="bg-white shadow rounded-xl p-6 transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Liste des Produits</h2>
                    <a href="add_product.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-transform duration-300 hover:scale-105 shadow-md hover:shadow-xl">
                        Ajouter un produit
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full rounded-xl overflow-hidden">
                        <thead>
                            <tr class="bg-gradient-to-r from-green-500 to-green-700">
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Catégorie</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Prix</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Valeur du Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-green-50 transition-all duration-300 hover:scale-[1.01]">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-800 font-medium">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Sans catégorie'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-green-700 font-semibold">
                                    <?php echo number_format($product['price'], 2, ',', ' ') . ' DH'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $product['stock'] < 5 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-green-800 font-semibold">
                                    <?php echo number_format($product['stock'] * $product['price'], 2, ',', ' ') . ' DH'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-4 font-semibold transition-transform duration-200 hover:scale-110">Modifier</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="delete_product" value="<?php echo $product['id']; ?>">
                                        <button type="submit" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')"
                                                class="text-red-600 hover:text-red-900 font-semibold transition-transform duration-200 hover:scale-110">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cartes -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Carte Messages -->
                <a href="messages.php" class="bg-white overflow-hidden shadow rounded-lg hover:bg-blue-50 hover:shadow-xl transition-all duration-300 animate-fade-in-up">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-envelope text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Messages</h3>
                                <p class="text-sm text-gray-500">Gérer les messages des utilisateurs</p>
                            </div>
                            <div class="ml-auto">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Carte Produits -->
                <a href="products.php" class="bg-white overflow-hidden shadow rounded-lg hover:bg-green-50 hover:shadow-xl transition-all duration-300 animate-fade-in-up delay-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <i class="fas fa-box text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Produits</h3>
                                <p class="text-sm text-gray-500">Gérer les produits</p>
                            </div>
                            <div class="ml-auto">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Carte Catégories -->
                <a href="categories.php" class="bg-white overflow-hidden shadow rounded-lg hover:bg-purple-50 hover:shadow-xl transition-all duration-300 animate-fade-in-up delay-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <i class="fas fa-tags text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Catégories</h3>
                                <p class="text-sm text-gray-500">Gérer les catégories</p>
                            </div>
                            <div class="ml-auto">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    <style>
        /* Custom keyframes for fade-in and slide-up */
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
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
    </style>
</body>
</html>