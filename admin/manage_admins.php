<?php
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!$auth->isLoggedIn() || !$auth->isAdminUser()) {
    header('Location: ../login.php');
    exit();
}

// Traitement de l'ajout d'un administrateur
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
        $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
            $error_message = "Tous les champs sont obligatoires.";
        } else {
            try {
                // Vérifier si l'email existe déjà
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() > 0) {
                    $error_message = "Cet email est déjà utilisé.";
                } else {
                    // Créer le nouvel administrateur
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$firstname, $lastname, $email, $hashed_password, 1]);
                    
                    $success_message = "Administrateur ajouté avec succès.";
                }
            } catch (PDOException $e) {
                $error_message = "Erreur lors de l'ajout de l'administrateur : " . $e->getMessage();
            }
        }
    }
    
    // Traitement de la suppression d'un administrateur
    if (isset($_POST['delete_admin'])) {
        $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
        
        if ($admin_id) {
            try {
                // Empêcher la suppression de son propre compte
                if ($admin_id == $_SESSION['user_id']) {
                    $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 1");
                    $stmt->execute([$admin_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = "Administrateur supprimé avec succès.";
                    } else {
                        $error_message = "Administrateur non trouvé ou vous n'avez pas les droits pour le supprimer.";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la suppression de l'administrateur : " . $e->getMessage();
            }
        }
    }
}

// Récupérer tous les administrateurs
try {
    $stmt = $pdo->query("SELECT id, firstname, lastname, email, created_at FROM users WHERE is_admin = 1 ORDER BY created_at DESC");
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des administrateurs : " . $e->getMessage();
    $admins = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Administrateurs - SolarTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- NavBar -->
    <nav class="bg-gradient-to-r from-blue-600 to-blue-400 shadow-xl rounded-b-xl transition-all duration-500 animate-fade-in-down mb-4">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-extrabold text-white tracking-wide drop-shadow-lg flex items-center gap-2">
                        <i class="fas fa-user-shield"></i> Gestion des administrateurs
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-home mr-2"></i>Tableau de bord
                    </a>
                    <a href="categories.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-tags mr-2"></i>Catégories
                    </a>
                    <a href="products.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-box mr-2"></i>Produits
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white font-semibold px-4 py-2 rounded hover:bg-red-700 transition flex items-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="bg-white shadow rounded-b-xl mb-6">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4
                        bg-gradient-to-r from-blue-50 via-white to-green-50 border border-blue-100 rounded-2xl shadow-lg p-6
                        transition-all duration-300 hover:shadow-2xl animate-fade-in-up">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <h2 class="text-3xl font-extrabold bg-gradient-to-r from-blue-600 via-green-500 to-blue-400 bg-clip-text text-transparent tracking-widest uppercase drop-shadow-lg animate-fade-in-up">
                        Gestion des administrateurs
                    </h2>
                    <span class="text-gray-500 text-base ml-2">Ajoutez ou supprimez des administrateurs pour votre site</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto pb-16">
        <?php if (!empty($success_message)): ?>
        <div class="animate-fade-in bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="animate-fade-in bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout d'administrateur -->
        <div class="bg-gradient-to-br from-blue-50 via-white to-green-50 border border-blue-100 shadow-2xl rounded-2xl p-10 mb-10 transition-all duration-300 animate-fade-in-up">
            <h3 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                <i class="fas fa-user-plus text-blue-500"></i> Ajouter un administrateur
            </h3>
            <form method="POST" action="manage_admins.php" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="firstname" class="block text-base font-semibold text-gray-700 mb-1">
                            Prénom <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="firstname" name="firstname" required
                               class="w-full px-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200">
                    </div>
                    <div>
                        <label for="lastname" class="block text-base font-semibold text-gray-700 mb-1">
                            Nom <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="lastname" name="lastname" required
                               class="w-full px-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200">
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-base font-semibold text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200">
                </div>
                <div>
                    <label for="password" class="block text-base font-semibold text-gray-700 mb-1">
                        Mot de passe <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200">
                </div>
                <div class="pt-2 flex justify-end">
                    <button type="submit" name="add_admin"
                            class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-green-500 hover:from-blue-600 hover:to-green-600 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-green-400">
                        <i class="fas fa-user-plus text-xl"></i>Ajouter l'administrateur
                    </button>
                </div>
            </form>
        </div>

        <!-- Liste des administrateurs -->
        <div class="bg-gradient-to-br from-blue-50 via-white to-green-50 border border-blue-100 shadow-2xl rounded-2xl p-10 transition-all duration-300 animate-fade-in-up">
            <h3 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                <i class="fas fa-users-cog text-blue-500"></i> Liste des administrateurs
            </h3>
            <?php if (empty($admins)): ?>
            <div class="bg-gray-50 p-4 rounded-lg text-center">
                <p class="text-gray-500">Aucun administrateur trouvé.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-gradient-to-r from-green-500 to-green-700">
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nom</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Date de création</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr class="hover:bg-green-50 transition-all duration-300 hover:scale-[1.01]">
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 font-medium">
                                <?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?>
                                <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Vous
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                <?php echo htmlspecialchars($admin['email']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-green-700 font-semibold">
                                <?php echo date('d/m/Y H:i', strtotime($admin['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="manage_admins.php" class="inline">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="delete_admin"
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')"
                                            class="text-red-600 hover:text-red-900 font-semibold transition-transform duration-200 hover:scale-110">
                                        <i class="fas fa-trash-alt mr-1"></i>Supprimer
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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