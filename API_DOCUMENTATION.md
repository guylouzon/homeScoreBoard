# Ping Pong Scoreboard Backend API Documentation

## Base URL
```
http://localhost/pong-scoreboard/api.php
```

## Authentication

The API uses session-based authentication. Players must log in via email (no password required for demo).

### Session Management
- Sessions persist for 24 hours
- Session ID is maintained in cookies (PHP session default)
- All authenticated endpoints check for valid session

---

## Endpoints

### 1. AUTHENTICATION ENDPOINTS

#### 1.1 Login
**Endpoint:** `POST /api.php?action=login`

**Description:** Authenticate a player using their email

**Request:**
```json
{
  "action": "login",
  "email": "alex.chen@email.com"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "player_id": 1,
    "name": "Alex Chen",
    "email": "alex.chen@email.com",
    "session_id": "abc123xyz"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Player not found or inactive",
  "data": []
}
```

**Status Codes:**
- `200` - Login successful
- `400` - Missing or invalid email
- `401` - Player not found or inactive
- `500` - Database error

---

#### 1.2 Logout
**Endpoint:** `POST /api.php?action=logout`

**Description:** End player session

**Request:**
```json
{
  "action": "logout"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": []
}
```

**Response (Error - Not Logged In):**
```json
{
  "success": false,
  "message": "Not logged in",
  "data": []
}
```

**Status Codes:**
- `200` - Logout successful
- `401` - Not logged in

---

#### 1.3 Register
**Endpoint:** `POST /api.php?action=register`

**Description:** Create a new player account

**Request:**
```json
{
  "action": "register",
  "name": "New Player",
  "email": "newplayer@email.com",
  "phone": "555-0999"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "player_id": 7,
    "name": "New Player",
    "email": "newplayer@email.com",
    "session_id": "def456uvw"
  }
}
```

**Response (Error - Email Exists):**
```json
{
  "success": false,
  "message": "Email already registered",
  "data": []
}
```

**Status Codes:**
- `201` - Registration successful (auto-logged in)
- `400` - Missing required fields or invalid data
- `409` - Email already registered
- `500` - Database error

**Required Fields:**
- `name` (2-255 characters)
- `email` (valid email format)

**Optional Fields:**
- `phone`

---

#### 1.4 Auth Check
**Endpoint:** `GET /api.php?action=auth-check`

**Description:** Check if user is logged in

**Response (Logged In):**
```json
{
  "success": true,
  "message": "Authenticated",
  "data": {
    "player_id": 1,
    "name": "Alex Chen",
    "email": "alex.chen@email.com"
  }
}
```

**Response (Not Logged In):**
```json
{
  "success": false,
  "message": "Not logged in",
  "data": []
}
```

**Status Codes:**
- `200` - User is authenticated
- `401` - User not logged in

---

### 2. PUBLIC DATA ENDPOINTS (No Authentication Required)

#### 2.1 Latest Game
**Endpoint:** `GET /api.php?action=latest-game`

**Description:** Get the most recent game result

**Response (Success):**
```json
{
  "success": true,
  "message": "Latest game retrieved",
  "data": {
    "game_id": 1,
    "match_date": "2024-03-26",
    "match_time": "18:45:00",
    "player1_id": 1,
    "player1_name": "Alex Chen",
    "player1_score": 11,
    "player2_id": 2,
    "player2_name": "Sarah Williams",
    "player2_score": 8,
    "player1_passes": 87,
    "player2_passes": 64,
    "winner_id": 1,
    "winner_name": "Alex Chen"
  }
}
```

**Response (No Games):**
```json
{
  "success": true,
  "message": "No games found",
  "data": []
}
```

---

#### 2.2 Top Passes
**Endpoint:** `GET /api.php?action=top-passes`

**Description:** Get top 3 players by passes in last 7 days

**Response:**
```json
{
  "success": true,
  "message": "Top pass leaders retrieved",
  "data": [
    {
      "player_id": 3,
      "name": "Marcus Johnson",
      "total_passes": 190
    },
    {
      "player_id": 2,
      "name": "Sarah Williams",
      "total_passes": 156
    },
    {
      "player_id": 1,
      "name": "Alex Chen",
      "total_passes": 158
    }
  ]
}
```

---

#### 2.3 Player Rankings
**Endpoint:** `GET /api.php?action=player-rankings`

**Description:** Get leaderboard with all players ranked by wins

**Response:**
```json
{
  "success": true,
  "message": "Player rankings retrieved",
  "data": [
    {
      "player_id": 1,
      "name": "Alex Chen",
      "wins": 12,
      "passes": 1087,
      "matches": 15,
      "pass_accuracy": 89.97,
      "avg_passes_per_game": 72.47,
      "win_percentage": 80.00,
      "rank": 1
    },
    {
      "player_id": 2,
      "name": "Sarah Williams",
      "wins": 11,
      "passes": 1142,
      "matches": 14,
      "pass_accuracy": 89.93,
      "avg_passes_per_game": 81.57,
      "win_percentage": 78.57,
      "rank": 2
    }
  ]
}
```

---

#### 2.4 Dashboard (All Data)
**Endpoint:** `GET /api.php?action=dashboard`

**Description:** Get all dashboard data in a single request (latest game, top passes, rankings)

**Response:**
```json
{
  "success": true,
  "message": "Dashboard data retrieved",
  "data": {
    "latest_game": {
      "game_id": 1,
      "match_date": "2024-03-26",
      "match_time": "18:45:00",
      "player1_id": 1,
      "player1_name": "Alex Chen",
      "player1_score": 11,
      "player2_id": 2,
      "player2_name": "Sarah Williams",
      "player2_score": 8,
      "player1_passes": 87,
      "player2_passes": 64,
      "winner_id": 1
    },
    "top_passes": [
      {
        "player_id": 3,
        "name": "Marcus Johnson",
        "total_passes": 190
      }
    ],
    "player_rankings": [
      {
        "player_id": 1,
        "name": "Alex Chen",
        "wins": 12,
        "passes": 1087,
        "matches": 15,
        "pass_accuracy": 89.97,
        "avg_passes_per_game": 72.47,
        "win_percentage": 80.00,
        "rank": 1
      }
    ]
  }
}
```

---

### 3. AUTHENTICATED ENDPOINTS (Requires Login)

#### 3.1 Player Profile
**Endpoint:** `GET /api.php?action=player-profile`

**Description:** Get logged-in player's detailed profile

**Requirements:** User must be logged in

**Response:**
```json
{
  "success": true,
  "message": "Player profile retrieved",
  "data": {
    "id": 1,
    "name": "Alex Chen",
    "email": "alex.chen@email.com",
    "phone": "555-0101",
    "join_date": "2024-01-15 10:30:00",
    "total_games": 15,
    "total_wins": 12,
    "total_losses": 3,
    "total_passes": 1087,
    "pass_accuracy": 89.97,
    "avg_passes_per_game": 72.47,
    "win_percentage": 80.00,
    "avg_points_per_game": 11.13
  }
}
```

**Response (Not Logged In):**
```json
{
  "success": false,
  "message": "Not logged in",
  "data": []
}
```

---

#### 3.2 Game History
**Endpoint:** `GET /api.php?action=game-history&limit=10`

**Description:** Get logged-in player's match history

**Requirements:** User must be logged in

**Query Parameters:**
- `limit` (optional, default: 10, max: 100) - Number of games to retrieve

**Response:**
```json
{
  "success": true,
  "message": "Game history retrieved",
  "data": [
    {
      "id": 1,
      "match_date": "2024-03-26",
      "match_time": "18:45:00",
      "opponent_name": "Sarah Williams",
      "player_score": 11,
      "opponent_score": 8,
      "result": "W",
      "player_passes": 87
    },
    {
      "id": 2,
      "match_date": "2024-03-25",
      "match_time": "19:10:00",
      "opponent_name": "Marcus Johnson",
      "player_score": 6,
      "opponent_score": 11,
      "result": "L",
      "player_passes": 71
    }
  ]
}
```

---

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid input or missing required fields |
| 401 | Unauthorized | Not logged in or authentication failed |
| 404 | Not Found | Resource not found |
| 405 | Method Not Allowed | Wrong HTTP method |
| 409 | Conflict | Resource already exists (e.g., email taken) |
| 500 | Server Error | Database or server error |

---

## JavaScript/Frontend Examples

### Login
```javascript
async function login(email) {
  const response = await fetch('/api.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include', // Include cookies for session
    body: JSON.stringify({ email })
  });
  const data = await response.json();
  return data;
}

// Usage
login('alex.chen@email.com').then(res => {
  if (res.success) {
    console.log('Welcome:', res.data.name);
  } else {
    console.error(res.message);
  }
});
```

### Get Dashboard Data
```javascript
async function getDashboard() {
  const response = await fetch('/api.php?action=dashboard');
  const data = await response.json();
  return data.data; // Contains latest_game, top_passes, player_rankings
}

// Usage
getDashboard().then(dashboard => {
  console.log('Latest game:', dashboard.latest_game);
  console.log('Top passes:', dashboard.top_passes);
  console.log('Rankings:', dashboard.player_rankings);
});
```

### Get Player Profile (Authenticated)
```javascript
async function getProfile() {
  const response = await fetch('/api.php?action=player-profile', {
    credentials: 'include' // Send session cookie
  });
  const data = await response.json();
  
  if (!data.success) {
    console.log('Not logged in');
    return;
  }
  
  return data.data;
}

// Usage
getProfile().then(profile => {
  console.log('Name:', profile.name);
  console.log('Wins:', profile.total_wins);
});
```

### Get Game History (Authenticated)
```javascript
async function getGameHistory(limit = 10) {
  const response = await fetch(`/api.php?action=game-history&limit=${limit}`, {
    credentials: 'include'
  });
  const data = await response.json();
  return data.data;
}

// Usage
getGameHistory(5).then(games => {
  games.forEach(game => {
    console.log(`${game.opponent_name}: ${game.result}`);
  });
});
```

---

## Security Features

✅ **Prepared Statements** - All queries use parameterized statements to prevent SQL injection
✅ **Input Validation** - Email format, field length, and data type validation
✅ **Input Sanitization** - HTML special characters escaped
✅ **Session Management** - Server-side PHP sessions
✅ **CORS Enabled** - Cross-origin requests allowed (modify for production)
✅ **Type Safety** - Responses include proper data types (integers, floats)

---

## Configuration

Edit these values in `api.php`:

```php
define('DB_HOST', 'localhost');     // MySQL host
define('DB_USER', 'root');          // Database user
define('DB_PASS', 'password');      // Database password
define('DB_NAME', 'ping_pong_scoreboard');  // Database name
define('SESSION_TIMEOUT', 86400);   // 24 hours
```

---

## Database Setup

1. Create database:
```sql
CREATE DATABASE ping_pong_scoreboard;
```

2. Run schema (from `pong_scoreboard_schema.sql`)

3. Populate sample data (from `pong_scoreboard_inserts.sql`)

4. Update credentials in `api.php`

---

## Rate Limiting

Currently not implemented. For production, add rate limiting:

```php
function rateLimitCheck($ip) {
    $key = 'rate_limit_' . $ip;
    // Implement Redis or similar
}
```

---

## Future Enhancements

- [ ] Password-based authentication
- [ ] JWT tokens for stateless auth
- [ ] Rate limiting
- [ ] API key management
- [ ] Webhook for game submissions
- [ ] Statistics filtering (date range, opponent)
- [ ] Head-to-head stats endpoint
- [ ] Team management
- [ ] Tournaments
