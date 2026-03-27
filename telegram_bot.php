<?php
/**
 * Ping Pong Scoreboard - Telegram Bot Backend
 * 
 * Receives game updates from Telegram group in strict format
 * Parses messages and inserts data into database
 * 
 * Message Format Examples:
 * /game Alex Chen vs Sarah Williams 11 8
 * /game Player1 vs Player2 <score1> <score2>
 * 
 * With stats:
 * /game Alex Chen vs Sarah Williams 11 8 | 87 64 | player1:2,player2:1
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

// Telegram Bot Token - Get from @BotFather
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'ping_pong_scoreboard');

// Telegram Group/Channel ID (where bot receives messages)
// Get this from bot /start in group, or use @idbot
define('TELEGRAM_GROUP_ID', -1001234567890); // Negative for groups

// Allowed Admins (Telegram user IDs)
$ALLOWED_ADMINS = [123456789, 987654321]; // Replace with actual user IDs

// Message Format Validation
define('MESSAGE_FORMAT_STRICT', true); // Enforce strict format

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    error_log('Database connection failed: ' . $mysqli->connect_error);
    http_response_code(500);
    exit('Database connection error');
}

$mysqli->set_charset("utf8mb4");

// ============================================================================
// WEBHOOK HANDLER
// ============================================================================

/**
 * Handle incoming Telegram webhook
 */
function handleTelegramWebhook() {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if (!$update) {
        http_response_code(400);
        error_log('Invalid JSON received');
        exit('Invalid JSON');
    }

    // Log all updates
    error_log('Telegram Update: ' . json_encode($update, JSON_PRETTY_PRINT));

    // Handle different update types
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }

    // Always respond 200 to Telegram immediately
    http_response_code(200);
}

/**
 * Handle text messages from Telegram
 * @param array $message Telegram message object
 */
function handleMessage($message) {
    global $ALLOWED_ADMINS;

    // Extract message details
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // Log message
    error_log("Message from $user_id in chat $chat_id: $text");

    // Check if it's a group message and bot is mentioned (for security)
    if ($message['chat']['type'] === 'group' || $message['chat']['type'] === 'supergroup') {
        // Commands that work in groups
        if (strpos($text, '/game') === 0) {
            handleGameCommand($text, $chat_id, $user_id);
        } elseif (strpos($text, '/stats') === 0) {
            handleStatsCommand($chat_id, $user_id);
        } elseif (strpos($text, '/help') === 0) {
            sendMessage($chat_id, getHelpText());
        } elseif (strpos($text, '/players') === 0) {
            handlePlayersListCommand($chat_id);
        } elseif (strpos($text, '/undo') === 0 && in_array($user_id, $ALLOWED_ADMINS)) {
            handleUndoCommand($chat_id, $message);
        } elseif (strpos($text, '/register') === 0) {
            handleRegisterCommand($text, $chat_id, $user_id, $message);
        }
    } elseif ($message['chat']['type'] === 'private') {
        // Private commands
        if (strpos($text, '/start') === 0) {
            sendMessage($chat_id, "Welcome to Ping Pong Scoreboard Bot!\n\n" . getHelpText());
        } elseif (strpos($text, '/mygames') === 0) {
            handleMyGamesCommand($chat_id, $user_id);
        } elseif (strpos($text, '/register') === 0) {
            handleRegisterCommand($text, $chat_id, $user_id, $message);
        }
    }
}

// ============================================================================
// COMMAND HANDLERS
// ============================================================================

/**
 * Parse and execute /game command
 * Format: /game Player1 vs Player2 <score1> <score2> [| <passes1> <passes2>] [| aces1:x,aces2:y]
 * 
 * Examples:
 * /game Alex Chen vs Sarah Williams 11 8
 * /game Alex Chen vs Sarah Williams 11 8 | 87 64
 * /game Alex Chen vs Sarah Williams 11 8 | 87 64 | aces1:2,aces2:1
 */
function handleGameCommand($text, $chat_id, $user_id) {
    global $mysqli;

    // Remove /game prefix and trim
    $game_text = trim(substr($text, 5));

    if (empty($game_text)) {
        sendMessage($chat_id, "❌ *Error*: Missing game data\n\n" .
            "Format: `/game Player1 vs Player2 <score1> <score2>` [| passes] [| stats]\n\n" .
            "Example: `/game Alex Chen vs Sarah Williams 11 8`", true);
        return;
    }

    // Parse game data
    $parsed = parseGameMessage($game_text);

    if (!$parsed['success']) {
        sendMessage($chat_id, "❌ *Parse Error*: " . $parsed['error'] . "\n\n" .
            "Expected format: `Player1 vs Player2 score1 score2` [| passes1 passes2]", true);
        error_log("Parse error: " . $parsed['error'] . " | Input: $game_text");
        return;
    }

    // Validate scores
    if (!isValidScore($parsed['data']['score1'], $parsed['data']['score2'])) {
        sendMessage($chat_id, "❌ *Score Error*: Invalid score. Winning score must be 11 with 2-point lead.\n" .
            "Scores must be positive integers (e.g., 11-8, 15-13)", true);
        return;
    }

    // Get or find players
    $player1 = getOrCreatePlayer($parsed['data']['player1']);
    $player2 = getOrCreatePlayer($parsed['data']['player2']);

    if (!$player1 || !$player2) {
        sendMessage($chat_id, "❌ *Player Error*: Could not find or create players. Try `/register` first.", true);
        return;
    }

    // Prevent same player playing against themselves
    if ($player1['id'] === $player2['id']) {
        sendMessage($chat_id, "❌ *Game Error*: A player cannot play against themselves!", true);
        return;
    }

    // Determine winner
    $winner_id = $parsed['data']['score1'] > $parsed['data']['score2'] ? $player1['id'] : $player2['id'];

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Insert game
        $stmt = $mysqli->prepare("
            INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes)
            VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), NULL)
        ");

        $stmt->bind_param(
            'iiiii',
            $player1['id'],
            $player2['id'],
            $parsed['data']['score1'],
            $parsed['data']['score2'],
            $winner_id
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert game: ' . $stmt->error);
        }

        $game_id = $stmt->insert_id;
        $stmt->close();

        // Insert game stats
        $passes1 = $parsed['data']['passes1'] ?? null;
        $passes2 = $parsed['data']['passes2'] ?? null;
        $aces1 = $parsed['data']['aces1'] ?? 0;
        $aces2 = $parsed['data']['aces2'] ?? 0;

        // Player 1 stats
        $stmt = $mysqli->prepare("
            INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, aces, winner)
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?)
        ");

        $winner1 = ($winner_id === $player1['id']) ? 1 : 0;
        $stmt->bind_param(
            'iiiiiii',
            $game_id,
            $player1['id'],
            $parsed['data']['score1'],
            $parsed['data']['score2'],
            $passes1,
            $aces1,
            $winner1
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert player1 stats: ' . $stmt->error);
        }
        $stmt->close();

        // Player 2 stats
        $stmt = $mysqli->prepare("
            INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, aces, winner)
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?)
        ");

        $winner2 = ($winner_id === $player2['id']) ? 1 : 0;
        $stmt->bind_param(
            'iiiiiii',
            $game_id,
            $player2['id'],
            $parsed['data']['score2'],
            $parsed['data']['score1'],
            $passes2,
            $aces2,
            $winner2
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert player2 stats: ' . $stmt->error);
        }
        $stmt->close();

        // Update aggregated stats
        updatePlayerStats($player1['id']);
        updatePlayerStats($player2['id']);

        // Commit transaction
        $mysqli->commit();

        // Success message
        $passes_text = ($passes1 && $passes2) ? " | Passes: ${passes1}-${passes2}" : "";
        $message = "✅ *Game recorded!*\n\n" .
                   "*${player1['name']}* 🏓 *${player2['name']}*\n" .
                   "${parsed['data']['score1']}-${parsed['data']['score2']}\n\n" .
                   "🏆 Winner: *${parsed['data']['score1'] > $parsed['data']['score2'] ? $player1['name'] : $player2['name']}*" .
                   $passes_text;

        sendMessage($chat_id, $message, true);
        error_log("Game recorded: $player1[name] vs $player2[name] {$parsed['data']['score1']}-{$parsed['data']['score2']}");

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Transaction failed: ' . $e->getMessage());
        sendMessage($chat_id, "❌ *Database Error*: " . $e->getMessage(), true);
    }
}

/**
 * Handle /stats command - Show global statistics
 */
function handleStatsCommand($chat_id, $user_id) {
    global $mysqli;

    $stmt = $mysqli->prepare("
        SELECT 
            p.name,
            COALESCE(ps.total_wins, 0) as wins,
            COALESCE(ps.total_games, 0) as games,
            COALESCE(ps.total_passes, 0) as passes,
            COALESCE(ps.win_percentage, 0) as win_pct
        FROM players p
        LEFT JOIN passes_stats ps ON p.id = ps.player_id
        WHERE p.status = 'active'
        ORDER BY COALESCE(ps.total_wins, 0) DESC
        LIMIT 10
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    $message = "*📊 Player Rankings:*\n\n";
    $rank = 1;

    while ($row = $result->fetch_assoc()) {
        $win_pct = $row['games'] > 0 ? round(($row['wins'] / $row['games']) * 100) : 0;
        $message .= "$rank. *${row['name']}*\n";
        $message .= "  Wins: ${row['wins']} | Games: ${row['games']} | Win %: ${win_pct}% | Passes: ${row['passes']}\n\n";
        $rank++;
    }

    $stmt->close();

    sendMessage($chat_id, $message, true);
}

/**
 * Handle /players command - List all players
 */
function handlePlayersListCommand($chat_id) {
    global $mysqli;

    $result = $mysqli->query("
        SELECT id, name, email, status 
        FROM players 
        WHERE status = 'active'
        ORDER BY name
    ");

    if (!$result || $result->num_rows === 0) {
        sendMessage($chat_id, "No active players found.", true);
        return;
    }

    $message = "*👥 Active Players:*\n\n";
    $count = 1;

    while ($row = $result->fetch_assoc()) {
        $message .= "$count. *${row['name']}*\n";
        $count++;
    }

    $result->close();
    sendMessage($chat_id, $message, true);
}

/**
 * Handle /register command - Register new player
 * Format: /register Name Email [Phone]
 */
function handleRegisterCommand($text, $chat_id, $user_id, $message) {
    global $mysqli;

    // Parse: /register Name | email@example.com | 555-0000
    $parts = explode('|', $text);
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ *Error*: Invalid format\n\n" .
            "*Format:* `/register Name | email@example.com | (optional) Phone`\n\n" .
            "*Example:* `/register Alex Chen | alex.chen@email.com | 555-0101`", true);
        return;
    }

    $name = trim(str_replace('/register', '', $parts[0]));
    $email = trim($parts[1]);
    $phone = isset($parts[2]) ? trim($parts[2]) : null;

    // Validate
    if (empty($name) || strlen($name) < 2) {
        sendMessage($chat_id, "❌ *Error*: Invalid name (min 2 characters)", true);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendMessage($chat_id, "❌ *Error*: Invalid email format", true);
        return;
    }

    // Check if email exists
    $check = $mysqli->prepare("SELECT id FROM players WHERE email = ?");
    $check->bind_param('s', $email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $check->close();
        sendMessage($chat_id, "❌ *Error*: Email already registered", true);
        return;
    }
    $check->close();

    // Insert player
    $stmt = $mysqli->prepare("
        INSERT INTO players (name, email, phone, status, join_date)
        VALUES (?, ?, ?, 'active', NOW())
    ");

    $stmt->bind_param('sss', $name, $email, $phone);

    if ($stmt->execute()) {
        $stmt->close();
        sendMessage($chat_id, "✅ *Registration successful!*\n\n" .
            "*Name:* $name\n" .
            "*Email:* $email" . ($phone ? "\n*Phone:* $phone" : ""), true);
        error_log("New player registered: $name ($email)");
    } else {
        $stmt->close();
        sendMessage($chat_id, "❌ *Error*: Failed to register player", true);
        error_log('Registration failed: ' . $mysqli->error);
    }
}

/**
 * Handle /mygames command - Show user's recent games (private chat)
 */
function handleMyGamesCommand($chat_id, $user_id) {
    global $mysqli;

    // This would require storing Telegram user ID in players table
    // For now, we'll show a placeholder
    $message = "To view your games, please use the web dashboard or the /stats command in the group.";
    sendMessage($chat_id, $message);
}

/**
 * Handle /undo command - Remove last game (admin only)
 */
function handleUndoCommand($chat_id, $message) {
    global $mysqli;

    // Delete the most recent game
    $stmt = $mysqli->prepare("
        DELETE FROM games 
        WHERE id = (
            SELECT id FROM games 
            ORDER BY created_at DESC 
            LIMIT 1
        )
    ");

    if ($stmt->execute()) {
        sendMessage($chat_id, "✅ *Last game deleted*", true);
        error_log("Game deleted by admin");
    } else {
        sendMessage($chat_id, "❌ *Error*: Could not delete game", true);
    }

    $stmt->close();
}

/**
 * Handle callback queries (inline buttons)
 */
function handleCallbackQuery($callback_query) {
    $callback_id = $callback_query['id'];
    $data = $callback_query['data'] ?? '';
    $chat_id = $callback_query['message']['chat']['id'] ?? null;

    // Process based on callback data
    // This can be used for inline keyboard confirmations

    // Always answer callback query
    answerCallbackQuery($callback_id, "Processing...");
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Parse /game message into structured data
 * @param string $message Game message text
 * @return array Parsed data with success flag
 */
function parseGameMessage($message) {
    // Remove extra whitespace
    $message = trim($message);

    // Split by pipes for extended data
    $sections = array_map('trim', explode('|', $message));
    $main_section = $sections[0];

    // Main section: Player1 vs Player2 score1 score2
    // More flexible parsing to handle names with spaces
    
    // Look for " vs " separator
    if (strpos($main_section, ' vs ') === false) {
        return [
            'success' => false,
            'error' => 'Missing " vs " separator between players'
        ];
    }

    // Split by " vs "
    $vs_parts = explode(' vs ', $main_section);
    if (count($vs_parts) !== 2) {
        return [
            'success' => false,
            'error' => 'Invalid player separator'
        ];
    }

    $player1_and_score = trim($vs_parts[0]);
    $player2_and_score = trim($vs_parts[1]);

    // Extract scores from the end
    // Scores are the last two space-separated numbers
    $tokens1 = explode(' ', $player1_and_score);
    $tokens2 = explode(' ', $player2_and_score);

    if (count($tokens1) < 2 || count($tokens2) < 2) {
        return [
            'success' => false,
            'error' => 'Missing scores'
        ];
    }

    // Last token is score1
    $score1 = array_pop($tokens1);
    $player1 = implode(' ', $tokens1);

    // Last token is score2
    $score2 = array_pop($tokens2);
    $player2 = implode(' ', $tokens2);

    // Validate scores are numeric
    if (!is_numeric($score1) || !is_numeric($score2)) {
        return [
            'success' => false,
            'error' => 'Scores must be numbers'
        ];
    }

    $data = [
        'player1' => $player1,
        'player2' => $player2,
        'score1' => (int) $score1,
        'score2' => (int) $score2
    ];

    // Parse optional passes section
    if (isset($sections[1])) {
        $passes = explode(' ', trim($sections[1]));
        if (count($passes) >= 2) {
            $data['passes1'] = (int) $passes[0];
            $data['passes2'] = (int) $passes[1];
        }
    }

    // Parse optional stats section (aces, etc)
    if (isset($sections[2])) {
        $stats = trim($sections[2]);
        // Format: aces1:2,aces2:1
        if (strpos($stats, 'aces') !== false) {
            preg_match('/aces1:(\d+)/', $stats, $m1);
            preg_match('/aces2:(\d+)/', $stats, $m2);
            if (isset($m1[1])) $data['aces1'] = (int) $m1[1];
            if (isset($m2[1])) $data['aces2'] = (int) $m2[1];
        }
    }

    return [
        'success' => true,
        'data' => $data
    ];
}

/**
 * Validate ping pong score
 * Winner must have 11+ points with 2-point lead
 * @param int $score1
 * @param int $score2
 * @return bool
 */
function isValidScore($score1, $score2) {
    // Must be positive
    if ($score1 < 0 || $score2 < 0) {
        return false;
    }

    $max_score = max($score1, $score2);
    $min_score = min($score1, $score2);

    // Standard ping pong: first to 11 with 2-point lead
    if ($max_score >= 11 && ($max_score - $min_score) >= 2) {
        return true;
    }

    // Deuce: if score is at least 10-10, need 2-point lead
    if ($score1 >= 10 && $score2 >= 10 && abs($score1 - $score2) >= 2) {
        return true;
    }

    return false;
}

/**
 * Get existing player or create new one from name
 * @param string $name Player name
 * @return array|false Player data or false on failure
 */
function getOrCreatePlayer($name) {
    global $mysqli;

    // Trim and clean name
    $name = trim($name);

    if (empty($name)) {
        return false;
    }

    // Try to find existing player
    $stmt = $mysqli->prepare("SELECT id, name, email FROM players WHERE LOWER(name) = LOWER(?) AND status = 'active'");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $player = $result->fetch_assoc();
        $stmt->close();
        return $player;
    }

    $stmt->close();

    // Create new player with auto-generated email (won't be used for login from Telegram)
    $email = strtolower(str_replace(' ', '.', $name)) . '@telegram.local';
    
    // Check if auto-email already exists
    $check = $mysqli->prepare("SELECT id FROM players WHERE email = ?");
    $check->bind_param('s', $email);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        // Email exists, player might be inactive
        return false;
    }
    $check->close();

    // Insert new player
    $stmt = $mysqli->prepare("
        INSERT INTO players (name, email, status, join_date)
        VALUES (?, ?, 'active', NOW())
    ");

    $stmt->bind_param('ss', $name, $email);

    if ($stmt->execute()) {
        $player_id = $stmt->insert_id;
        $stmt->close();
        error_log("Auto-created player: $name ($email)");
        return ['id' => $player_id, 'name' => $name, 'email' => $email];
    }

    $stmt->close();
    return false;
}

/**
 * Update aggregated player statistics
 * @param int $player_id
 */
function updatePlayerStats($player_id) {
    global $mysqli;

    // Calculate stats from games_stats
    $stmt = $mysqli->prepare("
        INSERT INTO passes_stats (player_id, total_games, total_passes, successful_passes, pass_accuracy, avg_passes_per_game, total_wins, total_losses, win_percentage, total_points, avg_points_per_game)
        SELECT 
            ?,
            COUNT(DISTINCT game_id),
            COALESCE(SUM(total_passes), 0),
            COALESCE(SUM(successful_passes), 0),
            ROUND(COALESCE((SUM(successful_passes) / SUM(total_passes)) * 100, 0), 2),
            ROUND(COALESCE(SUM(total_passes) / NULLIF(COUNT(DISTINCT game_id), 0), 0), 2),
            SUM(CASE WHEN winner = TRUE THEN 1 ELSE 0 END),
            SUM(CASE WHEN winner = FALSE THEN 1 ELSE 0 END),
            ROUND((SUM(CASE WHEN winner = TRUE THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT game_id), 0)) * 100, 2),
            SUM(points_scored),
            ROUND(SUM(points_scored) / NULLIF(COUNT(DISTINCT game_id), 0), 2)
        FROM games_stats
        WHERE player_id = ?
        GROUP BY player_id
        ON DUPLICATE KEY UPDATE 
            total_games = VALUES(total_games),
            total_passes = VALUES(total_passes),
            successful_passes = VALUES(successful_passes),
            pass_accuracy = VALUES(pass_accuracy),
            avg_passes_per_game = VALUES(avg_passes_per_game),
            total_wins = VALUES(total_wins),
            total_losses = VALUES(total_losses),
            win_percentage = VALUES(win_percentage),
            total_points = VALUES(total_points),
            avg_points_per_game = VALUES(avg_points_per_game),
            last_updated = NOW()
    ");

    $stmt->bind_param('ii', $player_id, $player_id);
    $stmt->execute();
    $stmt->close();
}

// ============================================================================
// TELEGRAM API FUNCTIONS
// ============================================================================

/**
 * Send message to Telegram chat
 * @param int $chat_id
 * @param string $text
 * @param bool $markdown Use Markdown formatting
 */
function sendMessage($chat_id, $text, $markdown = false) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $markdown ? 'Markdown' : 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TELEGRAM_API_URL . '/sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Telegram API Error: $error");
    }

    error_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
}

/**
 * Send message with inline keyboard
 * @param int $chat_id
 * @param string $text
 * @param array $buttons Inline buttons
 */
function sendMessageWithButtons($chat_id, $text, $buttons) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => $buttons
        ])
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TELEGRAM_API_URL . '/sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
}

/**
 * Answer callback query
 * @param string $callback_id
 * @param string $text Notification text
 */
function answerCallbackQuery($callback_id, $text = '') {
    $data = [
        'callback_query_id' => $callback_id,
        'text' => $text,
        'show_alert' => false
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TELEGRAM_API_URL . '/answerCallbackQuery');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
}

/**
 * Get help text with command list
 */
function getHelpText() {
    return "*📋 Available Commands:*\n\n" .
           "*In Group:*\n" .
           "`/game Player1 vs Player2 11 8` - Record a game\n" .
           "  Optional: `| 87 64` (passes) `| aces1:2,aces2:1`\n\n" .
           "`/stats` - View player rankings\n" .
           "`/players` - List all active players\n" .
           "`/register Name | email@example.com | Phone` - Register new player\n" .
           "`/help` - Show this message\n\n" .
           "*Admin Commands:*\n" .
           "`/undo` - Delete last game record\n\n" .
           "*Example:*\n" .
           "`/game Alex Chen vs Sarah Williams 11 8 | 87 64`\n\n" .
           "Data is automatically saved to the scoreboard!";
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

// Check if this is a valid Telegram webhook request
// You should verify the webhook was set up correctly
if (php_sapi_name() === 'cli') {
    // CLI mode - for testing
    echo "Telegram Bot API is ready\n";
} else {
    // HTTP mode - handle webhook
    handleTelegramWebhook();
}

?>
