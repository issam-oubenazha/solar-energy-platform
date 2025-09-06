<?php
// Démarrer la session
session_start();

// Activer l'affichage des erreurs en développement
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    // Vérifier si l'utilisateur est un administrateur
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header('Location: admin/dashboard.php');
} else {
        header('Location: profile.php');
    }
    exit;
}

// Initialisation des variables
$error = '';
$email = '';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Validation basique
    if (empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        try {
            // Connexion à la base de données
            $pdo = new PDO('mysql:host=localhost;dbname=solar_products;charset=utf8mb4', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier les identifiants
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Authentification réussie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_firstname'] = $user['firstname'];
                $_SESSION['user_lastname'] = $user['lastname'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Redirection selon le type d'utilisateur
                if ($user['is_admin'] == 1) {
                    header("Location: admin/dashboard.php");
                } else {
                header("Location: profile.php");
                }
                exit;
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données. Veuillez réessayer plus tard.";
            error_log("Erreur de connexion: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - RéparationÉnergie</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- NavBar -->
    <nav class="bg-gradient-to-r from-blue-600 to-blue-400 shadow-xl rounded-b-xl transition-all duration-500 animate-fade-in-down mb-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-extrabold text-white tracking-wide drop-shadow-lg flex items-center gap-2">
                        <i class="fas fa-solar-panel"></i> Réparation<span class="text-yellow-200">Énergie</span>
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-home mr-2"></i>Accueil
                    </a>
                    <a href="about.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>À propos
                    </a>
                    <a href="contact.php" class="text-white font-semibold px-3 py-2 rounded hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-envelope mr-2"></i>Contact
                    </a>
                    <a href="register.php" class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-user-plus mr-2"></i>S'inscrire
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Login Form Section -->
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-blue-50 via-white to-green-50 border border-blue-100 shadow-2xl rounded-2xl p-10 transition-all duration-300 animate-fade-in-up">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold bg-gradient-to-r from-blue-600 via-green-500 to-blue-400 bg-clip-text text-transparent tracking-widest uppercase drop-shadow-lg animate-fade-in-up flex items-center gap-2">
                    <i class="fas fa-sign-in-alt"></i> Connexion
                </h2>
                <p class="text-blue-500 mt-2">
                    Connectez-vous pour accéder à votre espace personnel
                </p>
            </div>
            <?php if (!empty($error)): ?>
            <div class="animate-fade-in bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            <form method="POST" action="login.php" class="space-y-8">
                <div>
                    <label for="email" class="block text-base font-semibold text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="email"
                               id="email"
                               name="email"
                               value="<?php echo htmlspecialchars($email); ?>"
                               class="w-full pl-10 pr-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200"
                               placeholder="votre.email@exemple.com"
                               required>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-base font-semibold text-gray-700 mb-1">
                        Mot de passe <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               class="w-full pl-10 pr-4 py-2 rounded-lg border border-blue-200 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-400 focus:outline-none text-lg transition-all duration-200"
                               placeholder="Votre mot de passe"
                               required>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox"
                               id="remember"
                               name="remember"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Se souvenir de moi
                        </label>
                    </div>
                    <div class="text-sm">
                        <a href="reset-password.php" class="text-blue-600 hover:underline">
                            Mot de passe oublié?
                        </a>
                    </div>
                </div>
                <div class="pt-4 flex justify-end">
                    <button type="submit"
                            class="flex items-center gap-2 bg-gradient-to-r from-blue-500 to-green-500 hover:from-blue-600 hover:to-green-600 text-white px-6 py-3 rounded-lg shadow-lg text-lg font-bold w-full transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-green-400">
                        <i class="fas fa-sign-in-alt text-xl"></i>
                        Se connecter
                    </button>
                </div>
                <div class="text-center mt-6">
                    <p class="text-gray-600">
                        Pas encore de compte? <a href="register.php" class="text-blue-600 hover:underline">S'inscrire</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Contact Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        Contactez-nous
                    </h3>
                    <div class="space-y-3">
                        <p class="flex items-center text-gray-600 hover:text-blue-600 transition duration-300">
                            <i class="fas fa-phone text-blue-600 mr-3"></i>
                            <a href="tel:+212651441563">+212 651 441 563</a>
                        </p>
                        <p class="flex items-center text-gray-600 hover:text-blue-600 transition duration-300">
                            <i class="fas fa-phone text-blue-600 mr-3"></i>
                            <a href="tel:+212662611909">+212 662 611 909</a>
                        </p>
                        <p class="flex items-center text-gray-600 hover:text-blue-600 transition duration-300">
                            <i class="fas fa-envelope text-blue-600 mr-3"></i>
                            <a href="mailto:elyamanihamza@gmail.com">elyamanihamza@gmail.com</a>
                        </p>
                    </div>
                </div>

                <!-- Company Info -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-building text-blue-600 mr-2"></i>
                        Notre équipe
                    </h3>
                    <div class="space-y-3">
                        <p class="flex items-center text-gray-600">
                            <i class="fas fa-user text-blue-600 mr-3"></i>
                            Fondateur: Hassan El Yamani
                        </p>
                        <p class="flex items-center text-gray-600">
                            <i class="fas fa-user-tie text-blue-600 mr-3"></i>
                            Chef de projet: Hamza El Yamani
                        </p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-link text-blue-600 mr-2"></i>
                        Liens rapides
                    </h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="about.php" class="text-gray-600 hover:text-blue-600 transition duration-300 flex items-center">
                                <i class="fas fa-chevron-right mr-2"></i>
                                À propos de nous
                            </a>
                        </li>
                        <li>
                            <a href="contact.php" class="text-gray-600 hover:text-blue-600 transition duration-300 flex items-center">
                                <i class="fas fa-chevron-right mr-2"></i>
                                Contact
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-200 mt-8 pt-8 text-center">
                <p class="text-gray-500">
                    © <?php echo date('Y'); ?> Réparation d'énergie solaire et éolienne - Tous droits réservés
                </p>
            </div>
        </div>
    </footer>
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