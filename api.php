<?php
/**
 * Ping Pong Scoreboard Backend API
 * Handles authentication, login/logout, and data retrieval for dashboard
 * All database queries use prepared statements for security
 * Returns JSON responses
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'ping_pong_scoreboard');

// Session Configuration
define('SESSION_TIMEOUT', 86400); // 24 hours
define('SESSION_NAME', 'pong_session');

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $mysqli->connect_error
    ]);
    exit;
}

$mysqli->set_charset("utf8mb4");

// ============================================================================
// SESSION MANAGEMENT
// ============================================================================

session_start();

/**
 * Check if user is logged in
 * @return array|false Player data if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['player_id']) && isset($_SESSION['player_email']) 
        ? ['id' => $_SESSION['player_id'], 'email' => $_SESSION['player_email'], 'name' => $_SESSION['player_name']]
        : false;
}

/**
 * Create a new session for player
 * @param int $player_id
 * @param string $email
 * @param string $name
 */
function createSession($player_id, $email, $name) {
    $_SESSION['player_id'] = $player_id;
    $_SESSION['player_email'] = $email;
    $_SESSION['player_name'] = $name;
    $_SESSION['login_time'] = time();
}

/**
 * Destroy player session
 */
function destroySession() {
    session_unset();
    session_destroy();
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sanitize input
 * @param string $input
 * @return string Sanitized input
 */
function sanitize($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Send JSON response
 * @param bool $success
 * @param array $data
 * @param string $message
 * @param int $httpCode
 */
function respondJSON($success, $data = [], $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// ============================================================================
// ROUTE DISPATCHER
// ============================================================================

$request_method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

if ($request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    // Parse action from POST body if not in URL
    if (!$action && isset($input['action'])) {
        $action = sanitize($input['action']);
    }

    switch ($action) {
        case 'login':
            handleLogin($input);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'register':
            handleRegister($input);
            break;
        case 'latest-game':
            handleLatestGame();
            break;
        case 'top-passes':
            handleTopPasses();
            break;
        case 'player-rankings':
            handlePlayerRankings();
            break;
        case 'dashboard':
            handleDashboard();
            break;
        case 'player-profile':
            handlePlayerProfile();
            break;
        case 'game-history':
            handleGameHistory();
            break;
        case 'auth-check':
            handleAuthCheck();
            break;
        default:
            http_response_code(400);
            respondJSON(false, [], 'Invalid action', 400);
    }
} else {
    http_response_code(405);
    respondJSON(false, [], 'Method not allowed', 405);
}

// ============================================================================
// AUTHENTICATION ENDPOINTS
// ============================================================================

/**
 * Handle player login
 * POST /api.php?action=login
 * {
 *   "email": "player@email.com"
 * }
 */
function handleLogin($input) {
    global $mysqli;

    if (!isset($input['email']) || empty($input['email'])) {
        respondJSON(false, [], 'Email is required', 400);
    }

    $email = sanitize($input['email']);

    if (!isValidEmail($email)) {
        respondJSON(false, [], 'Invalid email format', 400);
    }

    // Prepare and execute query
    $stmt = $mysqli->prepare("SELECT id, name, email, status FROM players WHERE email = ? AND status = 'active'");
    
    if (!$stmt) {
        respondJSON(false, [], 'Database error: ' . $mysqli->error, 500);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respondJSON(false, [], 'Player not found or inactive', 401);
    }

    $player = $result->fetch_assoc();
    $stmt->close();

    // Create session
    createSession($player['id'], $player['email'], $player['name']);

    respondJSON(true, [
        'player_id' => $player['id'],
        'name' => $player['name'],
        'email' => $player['email'],
        'session_id' => session_id()
    ], 'Login successful');
}

/**
 * Handle player logout
 * POST /api.php?action=logout
 */
function handleLogout() {
    $logged_in = isLoggedIn();
    
    if (!$logged_in) {
        respondJSON(false, [], 'Not logged in', 401);
    }

    destroySession();
    respondJSON(true, [], 'Logged out successfully');
}

/**
 * Handle player registration
 * POST /api.php?action=register
 * {
 *   "name": "Player Name",
 *   "email": "player@email.com",
 *   "phone": "555-0000"
 * }
 */
function handleRegister($input) {
    global $mysqli;

    $required_fields = ['name', 'email'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            respondJSON(false, [], ucfirst($field) . ' is required', 400);
        }
    }

    $name = sanitize($input['name']);
    $email = sanitize($input['email']);
    $phone = isset($input['phone']) ? sanitize($input['phone']) : null;

    if (!isValidEmail($email)) {
        respondJSON(false, [], 'Invalid email format', 400);
    }

    if (strlen($name) < 2 || strlen($name) > 255) {
        respondJSON(false, [], 'Name must be between 2 and 255 characters', 400);
    }

    // Check if email already exists
    $check_stmt = $mysqli->prepare("SELECT id FROM players WHERE email = ?");
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        respondJSON(false, [], 'Email already registered', 409);
    }
    $check_stmt->close();

    // Insert new player
    $stmt = $mysqli->prepare("INSERT INTO players (name, email, phone, status, join_date) VALUES (?, ?, ?, 'active', NOW())");
    
    if (!$stmt) {
        respondJSON(false, [], 'Database error: ' . $mysqli->error, 500);
    }

    $stmt->bind_param('sss', $name, $email, $phone);
    
    if (!$stmt->execute()) {
        $stmt->close();
        respondJSON(false, [], 'Registration failed: ' . $mysqli->error, 500);
    }

    $player_id = $stmt->insert_id;
    $stmt->close();

    // Auto-login after registration
    createSession($player_id, $email, $name);

    respondJSON(true, [
        'player_id' => $player_id,
        'name' => $name,
        'email' => $email,
        'session_id' => session_id()
    ], 'Registration successful', 201);
}

/**
 * Check authentication status
 * GET /api.php?action=auth-check
 */
function handleAuthCheck() {
    $logged_in = isLoggedIn();
    
    if (!$logged_in) {
        respondJSON(false, [], 'Not logged in', 401);
    }

    respondJSON(true, [
        'player_id' => $logged_in['id'],
        'name' => $logged_in['name'],
        'email' => $logged_in['email']
    ], 'Authenticated');
}

// ============================================================================
// DASHBOARD DATA ENDPOINTS
// ============================================================================

/**
 * Get latest game result
 * GET /api.php?action=latest-game
 */
function handleLatestGame() {
    global $mysqli;

    $query = "
        SELECT 
            g.id as game_id,
            g.match_date,
            g.match_time,
            g.player1_id,
            p1.name as player1_name,
            g.player1_score,
            g.player2_id,
            p2.name as player2_name,
            g.player2_score,
            gs1.total_passes as player1_passes,
            gs2.total_passes as player2_passes,
            g.winner_id,
            CASE WHEN g.winner_id = g.player1_id THEN p1.name ELSE p2.name END as winner_name
        FROM games g
        JOIN players p1 ON g.player1_id = p1.id
        JOIN players p2 ON g.player2_id = p2.id
        LEFT JOIN games_stats gs1 ON g.id = gs1.game_id AND gs1.player_id = g.player1_id
        LEFT JOIN games_stats gs2 ON g.id = gs2.game_id AND gs2.player_id = g.player2_id
        WHERE p1.status = 'active' AND p2.status = 'active'
        ORDER BY g.match_date DESC, g.match_time DESC
        LIMIT 1
    ";

    $result = $mysqli->query($query);

    if (!$result) {
        respondJSON(false, [], 'Query error: ' . $mysqli->error, 500);
    }

    if ($result->num_rows === 0) {
        respondJSON(true, [], 'No games found');
    }

    $game = $result->fetch_assoc();
    $result->close();

    // Convert to appropriate data types
    $game['player1_score'] = (int) $game['player1_score'];
    $game['player2_score'] = (int) $game['player2_score'];
    $game['player1_passes'] = (int) $game['player1_passes'];
    $game['player2_passes'] = (int) $game['player2_passes'];

    respondJSON(true, $game, 'Latest game retrieved');
}

/**
 * Get top 3 pass leaders from recent games
 * GET /api.php?action=top-passes
 */
function handleTopPasses() {
    global $mysqli;

    // Get top 3 from last 7 days
    $query = "
        SELECT 
            p.id as player_id,
            p.name,
            SUM(gs.total_passes) as total_passes
        FROM games g
        JOIN games_stats gs ON g.id = gs.game_id
        JOIN players p ON gs.player_id = p.id
        WHERE g.match_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND p.status = 'active'
        GROUP BY p.id, p.name
        ORDER BY total_passes DESC
        LIMIT 3
    ";

    $result = $mysqli->query($query);

    if (!$result) {
        respondJSON(false, [], 'Query error: ' . $mysqli->error, 500);
    }

    $top_passes = [];
    while ($row = $result->fetch_assoc()) {
        $row['total_passes'] = (int) $row['total_passes'];
        $top_passes[] = $row;
    }
    $result->close();

    respondJSON(true, $top_passes, 'Top pass leaders retrieved');
}

/**
 * Get player rankings/leaderboard
 * GET /api.php?action=player-rankings
 */
function handlePlayerRankings() {
    global $mysqli;

    $query = "
        SELECT 
            p.id as player_id,
            p.name,
            COALESCE(ps.total_wins, 0) as wins,
            COALESCE(ps.total_passes, 0) as passes,
            COALESCE(ps.total_games, 0) as matches,
            COALESCE(ps.pass_accuracy, 0) as pass_accuracy,
            COALESCE(ps.avg_passes_per_game, 0) as avg_passes_per_game,
            COALESCE(ps.win_percentage, 0) as win_percentage,
            RANK() OVER (ORDER BY COALESCE(ps.total_wins, 0) DESC, COALESCE(ps.win_percentage, 0) DESC) as rank
        FROM players p
        LEFT JOIN passes_stats ps ON p.id = ps.player_id
        WHERE p.status = 'active'
        ORDER BY COALESCE(ps.total_wins, 0) DESC, COALESCE(ps.win_percentage, 0) DESC, p.name ASC
    ";

    $result = $mysqli->query($query);

    if (!$result) {
        respondJSON(false, [], 'Query error: ' . $mysqli->error, 500);
    }

    $rankings = [];
    while ($row = $result->fetch_assoc()) {
        // Convert numeric strings to integers/floats
        $row['player_id'] = (int) $row['player_id'];
        $row['wins'] = (int) $row['wins'];
        $row['passes'] = (int) $row['passes'];
        $row['matches'] = (int) $row['matches'];
        $row['pass_accuracy'] = (float) $row['pass_accuracy'];
        $row['avg_passes_per_game'] = (float) $row['avg_passes_per_game'];
        $row['win_percentage'] = (float) $row['win_percentage'];
        $row['rank'] = (int) $row['rank'];
        $rankings[] = $row;
    }
    $result->close();

    respondJSON(true, $rankings, 'Player rankings retrieved');
}

/**
 * Get all dashboard data in one call
 * GET /api.php?action=dashboard
 */
function handleDashboard() {
    global $mysqli;

    $dashboard = [];

    // Latest Game
    $game_query = "
        SELECT 
            g.id as game_id,
            g.match_date,
            g.match_time,
            g.player1_id,
            p1.name as player1_name,
            g.player1_score,
            g.player2_id,
            p2.name as player2_name,
            g.player2_score,
            gs1.total_passes as player1_passes,
            gs2.total_passes as player2_passes,
            g.winner_id
        FROM games g
        JOIN players p1 ON g.player1_id = p1.id
        JOIN players p2 ON g.player2_id = p2.id
        LEFT JOIN games_stats gs1 ON g.id = gs1.game_id AND gs1.player_id = g.player1_id
        LEFT JOIN games_stats gs2 ON g.id = gs2.game_id AND gs2.player_id = g.player2_id
        WHERE p1.status = 'active' AND p2.status = 'active'
        ORDER BY g.match_date DESC, g.match_time DESC
        LIMIT 1
    ";

    $result = $mysqli->query($game_query);
    if ($result && $result->num_rows > 0) {
        $game = $result->fetch_assoc();
        $game['player1_score'] = (int) $game['player1_score'];
        $game['player2_score'] = (int) $game['player2_score'];
        $game['player1_passes'] = (int) $game['player1_passes'];
        $game['player2_passes'] = (int) $game['player2_passes'];
        $dashboard['latest_game'] = $game;
    } else {
        $dashboard['latest_game'] = null;
    }
    if ($result) $result->close();

    // Top Passes
    $passes_query = "
        SELECT 
            p.id as player_id,
            p.name,
            SUM(gs.total_passes) as total_passes
        FROM games g
        JOIN games_stats gs ON g.id = gs.game_id
        JOIN players p ON gs.player_id = p.id
        WHERE g.match_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND p.status = 'active'
        GROUP BY p.id, p.name
        ORDER BY total_passes DESC
        LIMIT 3
    ";

    $result = $mysqli->query($passes_query);
    $top_passes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['total_passes'] = (int) $row['total_passes'];
            $top_passes[] = $row;
        }
        $result->close();
    }
    $dashboard['top_passes'] = $top_passes;

    // Player Rankings
    $rankings_query = "
        SELECT 
            p.id as player_id,
            p.name,
            COALESCE(ps.total_wins, 0) as wins,
            COALESCE(ps.total_passes, 0) as passes,
            COALESCE(ps.total_games, 0) as matches,
            COALESCE(ps.pass_accuracy, 0) as pass_accuracy,
            COALESCE(ps.avg_passes_per_game, 0) as avg_passes_per_game,
            COALESCE(ps.win_percentage, 0) as win_percentage,
            RANK() OVER (ORDER BY COALESCE(ps.total_wins, 0) DESC, COALESCE(ps.win_percentage, 0) DESC) as rank
        FROM players p
        LEFT JOIN passes_stats ps ON p.id = ps.player_id
        WHERE p.status = 'active'
        ORDER BY COALESCE(ps.total_wins, 0) DESC, COALESCE(ps.win_percentage, 0) DESC, p.name ASC
    ";

    $result = $mysqli->query($rankings_query);
    $rankings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['player_id'] = (int) $row['player_id'];
            $row['wins'] = (int) $row['wins'];
            $row['passes'] = (int) $row['passes'];
            $row['matches'] = (int) $row['matches'];
            $row['pass_accuracy'] = (float) $row['pass_accuracy'];
            $row['avg_passes_per_game'] = (float) $row['avg_passes_per_game'];
            $row['win_percentage'] = (float) $row['win_percentage'];
            $row['rank'] = (int) $row['rank'];
            $rankings[] = $row;
        }
        $result->close();
    }
    $dashboard['player_rankings'] = $rankings;

    respondJSON(true, $dashboard, 'Dashboard data retrieved');
}

// ============================================================================
// PLAYER-SPECIFIC ENDPOINTS (Require Login)
// ============================================================================

/**
 * Get specific player's profile
 * GET /api.php?action=player-profile
 * Requires authentication
 */
function handlePlayerProfile() {
    global $mysqli;
    
    $logged_in = isLoggedIn();
    if (!$logged_in) {
        respondJSON(false, [], 'Not logged in', 401);
    }

    $player_id = $logged_in['id'];

    $stmt = $mysqli->prepare("
        SELECT 
            p.id,
            p.name,
            p.email,
            p.phone,
            p.join_date,
            ps.total_games,
            ps.total_wins,
            ps.total_losses,
            ps.total_passes,
            ps.pass_accuracy,
            ps.avg_passes_per_game,
            ps.win_percentage,
            ps.avg_points_per_game
        FROM players p
        LEFT JOIN passes_stats ps ON p.id = ps.player_id
        WHERE p.id = ?
    ");

    $stmt->bind_param('i', $player_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        respondJSON(false, [], 'Player not found', 404);
    }

    $profile = $result->fetch_assoc();
    $stmt->close();

    // Type conversions
    $profile['id'] = (int) $profile['id'];
    $profile['total_games'] = (int) $profile['total_games'];
    $profile['total_wins'] = (int) $profile['total_wins'];
    $profile['total_losses'] = (int) $profile['total_losses'];
    $profile['total_passes'] = (int) $profile['total_passes'];
    $profile['pass_accuracy'] = (float) $profile['pass_accuracy'];
    $profile['avg_passes_per_game'] = (float) $profile['avg_passes_per_game'];
    $profile['win_percentage'] = (float) $profile['win_percentage'];
    $profile['avg_points_per_game'] = (float) $profile['avg_points_per_game'];

    respondJSON(true, $profile, 'Player profile retrieved');
}

/**
 * Get player's game history
 * GET /api.php?action=game-history&limit=10
 * Requires authentication
 */
function handleGameHistory() {
    global $mysqli;
    
    $logged_in = isLoggedIn();
    if (!$logged_in) {
        respondJSON(false, [], 'Not logged in', 401);
    }

    $player_id = $logged_in['id'];
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $limit = min($limit, 100); // Max 100 records

    $stmt = $mysqli->prepare("
        SELECT 
            g.id,
            g.match_date,
            g.match_time,
            CASE WHEN g.player1_id = ? THEN p2.name ELSE p1.name END as opponent_name,
            CASE WHEN g.player1_id = ? THEN g.player1_score ELSE g.player2_score END as player_score,
            CASE WHEN g.player1_id = ? THEN g.player2_score ELSE g.player1_score END as opponent_score,
            CASE WHEN g.winner_id = ? THEN 'W' ELSE 'L' END as result,
            CASE WHEN g.player1_id = ? THEN gs1.total_passes ELSE gs2.total_passes END as player_passes
        FROM games g
        JOIN players p1 ON g.player1_id = p1.id
        JOIN players p2 ON g.player2_id = p2.id
        LEFT JOIN games_stats gs1 ON g.id = gs1.game_id AND gs1.player_id = g.player1_id
        LEFT JOIN games_stats gs2 ON g.id = gs2.game_id AND gs2.player_id = g.player2_id
        WHERE (g.player1_id = ? OR g.player2_id = ?)
            AND p1.status = 'active' 
            AND p2.status = 'active'
        ORDER BY g.match_date DESC, g.match_time DESC
        LIMIT ?
    ");

    $stmt->bind_param('iiiiiii', $player_id, $player_id, $player_id, $player_id, $player_id, $player_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $games = [];
    while ($row = $result->fetch_assoc()) {
        $row['player_score'] = (int) $row['player_score'];
        $row['opponent_score'] = (int) $row['opponent_score'];
        $row['player_passes'] = (int) $row['player_passes'];
        $games[] = $row;
    }
    
    $stmt->close();

    respondJSON(true, $games, 'Game history retrieved');
}

// ============================================================================
// ERROR HANDLER
// ============================================================================

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    respondJSON(false, [], 'Server error: ' . $errstr, 500);
});

?>
