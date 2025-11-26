<?php
/**
 * RavenWeapon Authentication API
 * Handles user login, registration, logout, and session management
 * Integrates with Joomla user system
 */

// Enable CORS with credentials support
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Define Joomla constants
define('_JEXEC', 1);
// Point to the Joomla installation at ravenweaponwebapp
define('JPATH_BASE', 'C:/xampp/htdocs/ravenweaponwebapp');

// Load Joomla
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserHelper;

try {
    // Boot the DI container
    $container = Factory::getContainer();

    // Alias the session service keys (required for web applications)
    $container->alias('session.web', 'session.web.site')
        ->alias('session', 'session.web.site')
        ->alias('JSession', 'session.web.site')
        ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

    // Instantiate the application
    $app = $container->get(\Joomla\CMS\Application\SiteApplication::class);

    // Set the application as global app
    Factory::$application = $app;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load Joomla: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Register new user
 */
function registerUser($data) {
    // Validate required fields
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception('Name, email, and password are required');
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $db = Factory::getDbo();

    // Check if email already exists
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('email') . ' = ' . $db->quote($data['email']));
    $db->setQuery($query);

    if ($db->loadResult() > 0) {
        throw new Exception('Email already registered');
    }

    // Check if username already exists
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('username') . ' = ' . $db->quote($data['email']));
    $db->setQuery($query);

    if ($db->loadResult() > 0) {
        throw new Exception('Username already taken');
    }

    // Hash password
    $hashedPassword = UserHelper::hashPassword($data['password']);

    // Insert user into database
    $user = new stdClass();
    $user->name = $data['name'];
    $user->username = $data['email'];
    $user->email = $data['email'];
    $user->password = $hashedPassword;
    $user->block = 0;
    $user->sendEmail = 0;
    $user->registerDate = date('Y-m-d H:i:s');
    $user->lastvisitDate = $db->getNullDate();
    $user->activation = '';
    $user->params = '{}';

    // Insert user
    $db->insertObject('#__users', $user, 'id');
    $userId = $db->insertid();

    // Add user to Registered group (group_id = 2)
    $userGroup = new stdClass();
    $userGroup->user_id = $userId;
    $userGroup->group_id = 2;
    $db->insertObject('#__user_usergroup_map', $userGroup);

    // AUTO-LOGIN: Create session for new user
    $newUser = Factory::getUser($userId);
    $session = Factory::getSession();
    $app = Factory::getApplication();

    // Set user in session
    $session->set('user', $newUser);

    // Update session in application
    Factory::$application->loadIdentity($newUser);

    // Update last visit date
    $lastVisit = new stdClass();
    $lastVisit->id = $newUser->id;
    $lastVisit->lastvisitDate = date('Y-m-d H:i:s');
    $db->updateObject('#__users', $lastVisit, 'id');

    return [
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $newUser->id,
            'name' => $newUser->name,
            'email' => $newUser->email,
            'username' => $newUser->username
        ]
    ];
}

/**
 * Login user (Custom implementation bypassing broken auth plugins)
 */
function loginUser($data) {
    // Validate required fields
    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception('Email and password are required');
    }

    $db = Factory::getDbo();

    // Get user by email (username in our case)
    $query = $db->getQuery(true)
        ->select(['id', 'username', 'email', 'password', 'name', 'block'])
        ->from($db->quoteName('#__users'))
        ->where($db->quoteName('username') . ' = ' . $db->quote($data['email']));
    $db->setQuery($query);
    $userData = $db->loadObject();

    if (!$userData) {
        throw new Exception('Invalid email or password');
    }

    // Check if user is blocked
    if ($userData->block == 1) {
        throw new Exception('User account is blocked');
    }

    // Verify password using Joomla's official method
    if (!UserHelper::verifyPassword($data['password'], $userData->password)) {
        throw new Exception('Invalid email or password');
    }

    // Load the full user object
    $user = Factory::getUser($userData->id);

    // Get the application and session
    $app = Factory::getApplication();
    $session = Factory::getSession();

    // Manually set the user in the session (bypassing plugins)
    $session->set('user', $user);

    // Update session in application
    Factory::$application->loadIdentity($user);

    // Update last visit date
    $lastVisit = new stdClass();
    $lastVisit->id = $user->id;
    $lastVisit->lastvisitDate = date('Y-m-d H:i:s');
    $db->updateObject('#__users', $lastVisit, 'id');

    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username
        ]
    ];
}

/**
 * Logout user (Custom implementation bypassing broken plugins)
 */
function logoutUser() {
    $session = Factory::getSession();
    $app = Factory::getApplication();

    // Clear the user from session
    $session->set('user', new \Joomla\CMS\User\User());

    // Load guest identity in application
    Factory::$application->loadIdentity(new \Joomla\CMS\User\User());

    // Destroy the session
    $session->destroy();

    return [
        'success' => true,
        'message' => 'Logout successful'
    ];
}

/**
 * Check if user is logged in and get user info
 */
function checkSession() {
    $user = Factory::getUser();

    if ($user->guest) {
        return [
            'success' => true,
            'loggedIn' => false,
            'user' => null
        ];
    }

    return [
        'success' => true,
        'loggedIn' => true,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username
        ]
    ];
}

// Handle API request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                throw new Exception('POST method required for registration');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $result = registerUser($input);
            break;

        case 'login':
            if ($method !== 'POST') {
                throw new Exception('POST method required for login');
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $result = loginUser($input);
            break;

        case 'logout':
            $result = logoutUser();
            break;

        case 'check':
        case 'session':
            $result = checkSession();
            break;

        default:
            throw new Exception('Invalid action. Use: register, login, logout, or check');
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
