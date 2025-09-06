<?php
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
$auth->requireAdmin();

$success_message = '';
$error_message = '';

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    try {
        // Traitement de l'image
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Format d\'image non autorisé. Formats acceptés : JPG, JPEG, PNG, GIF');
            }

            $file_name = uniqid('product_') . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = 'uploads/products/' . $file_name;
            } else {
                throw new Exception('Erreur lors du téléchargement de l\'image');
            }
        }

        // Validation des données
        if (empty($name)) {
            throw new Exception('Le nom du produit est requis');
        }
        if ($price <= 0) {
            throw new Exception('Le prix doit être supérieur à 0');
        }
        if ($stock < 0) {
            throw new Exception('Le stock ne peut pas être négatif');
        }

        // Insertion du produit
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $stock, $category_id, $image_url]);

        $success_message = "Le produit a été ajouté avec succès.";
        
        // Redirection vers la liste des produits après 2 secondes
        header("refresh:2;url=products.php");
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Récupération des catégories pour le menu déroulant
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow rounded-b-xl mb-6">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <a href="products.php"
                           class="flex items-center gap-2 bg-gradient-to-r from-gray-600 to-gray-800 hover:from-gray-700 hover:to-gray-900 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <i class="fas fa-arrow-left text-xl"></i>
                            Retour à la liste
                        </a>
                        <h2 class="text-3xl font-extrabold bg-gradient-to-r from-blue-600 via-green-500 to-blue-400 bg-clip-text text-transparent tracking-widest uppercase drop-shadow-lg animate-fade-in-up">
                            Ajouter un produit
                        </h2>
                    </div>
                </div>
            </div>
        </header>

        <!-- Messages de succès/erreur -->
        <?php if (!empty($success_message)): ?>
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo $success_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8 animate-fade-in">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout -->
        <div class="max-w-3xl mx-auto py-8 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-blue-50 via-white to-green-50 border border-blue-100 shadow-2xl rounded-2xl p-10 transition-all duration-300 animate-fade-in-up">
                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <div>
                        <label for="name" class="block text-base font-semibold text-gray-700 mb-1">Nom du produit</label>
                        <input type="text" name="name" id="name" required
                               class="mt-1 block w-full rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none px-4 py-2 text-lg transition-all duration-200">
                    </div>
                    <div>
                        <label for="description" class="block text-base font-semibold text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="mt-1 block w-full rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none px-4 py-2 text-lg transition-all duration-200"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="price" class="block text-base font-semibold text-gray-700 mb-1">Prix</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-base">€</span>
                                </div>
                                <input type="number" name="price" id="price" required step="0.01" min="0"
                                       class="pl-7 block w-full rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none px-4 py-2 text-lg transition-all duration-200">
                            </div>
                        </div>
                        <div>
                            <label for="stock" class="block text-base font-semibold text-gray-700 mb-1">Stock</label>
                            <input type="number" name="stock" id="stock" required min="0"
                                   class="mt-1 block w-full rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none px-4 py-2 text-lg transition-all duration-200">
                        </div>
                        <div>
                            <label for="category_id" class="block text-base font-semibold text-gray-700 mb-1">Catégorie</label>
                            <select name="category_id" id="category_id"
                                    class="mt-1 block w-full rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none px-4 py-2 text-lg transition-all duration-200">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="image" class="block text-base font-semibold text-gray-700 mb-1">Image du produit</label>
                        <input type="file" name="image" id="image" accept="image/*"
                               class="mt-1 block w-full text-base text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-base file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all duration-200">
                        <p class="mt-1 text-sm text-gray-500">Formats acceptés : JPG, JPEG, PNG, GIF</p>
                    </div>
                    <div class="flex justify-end space-x-4 mt-8">
                        <a href="products.php"
                           class="flex items-center gap-2 bg-gradient-to-r from-gray-600 to-gray-800 hover:from-gray-700 hover:to-gray-900 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <i class="fas fa-arrow-left text-xl"></i>
                            Annuler
                        </a>
                        <button type="submit"
                                class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-green-500 hover:from-blue-600 hover:to-green-600 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-green-400">
                            <i class="fas fa-save text-xl"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <style>
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(30px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        @keyframes fade-in {
            0% { opacity: 0;}
            100% { opacity: 1;}
        }
        .animate-fade-in-up { animation: fade-in-up 0.7s cubic-bezier(.4,0,.2,1) both; }
        .animate-fade-in { animation: fade-in 0.7s cubic-bezier(.4,0,.2,1) both; }
    </style>
</body>
</html>