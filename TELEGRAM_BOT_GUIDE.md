# Ping Pong Scoreboard - Telegram Bot Setup Guide

## Quick Start

### Step 1: Create Telegram Bot

1. Open Telegram and search for **@BotFather**
2. Send `/newbot` command
3. Choose a name: `Ping Pong Scoreboard Bot`
4. Choose a username: `pong_scoreboard_bot` (must end with `_bot`)
5. Copy the **API Token** (looks like: `123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi`)

### Step 2: Configure Bot

1. Send `/setcommands` to @BotFather
2. Enter this list:
```
game - Record a game result
stats - View player rankings
players - List active players
register - Register a new player
undo - Delete last game (admin)
help - Show commands
```

### Step 3: Update PHP Configuration

Edit `telegram_bot.php`:

```php
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE'); // Paste your token

// Get group ID by:
// 1. Add bot to group
// 2. Send any message
// 3. Visit: https://api.telegram.org/botYOUR_TOKEN/getUpdates
// 4. Find "chat":{"id": -1001234567890 in response
define('TELEGRAM_GROUP_ID', -1001234567890);

// Add Telegram user IDs of admins (for /undo command)
$ALLOWED_ADMINS = [123456789, 987654321];
```

Get your Telegram user ID:
- Search @userinfobot
- Send any message
- It will show your ID

### Step 4: Set Webhook

Set up a webhook so Telegram sends updates to your server:

```bash
curl -X POST https://api.telegram.org/botYOUR_TOKEN/setWebhook \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://yourdomain.com/path/to/telegram_bot.php",
    "allowed_updates": ["message", "callback_query"]
  }'
```

**Important:** Your server must be HTTPS and publicly accessible

Check webhook status:
```bash
curl https://api.telegram.org/botYOUR_TOKEN/getWebhookInfo
```

### Step 5: Add Bot to Group

1. Create a Telegram group (or use existing)
2. Search for your bot username (e.g., `@pong_scoreboard_bot`)
3. Add to group
4. Give admin permissions (optional, for /undo to work)

---

## Message Format Reference

### Basic Game Recording

**Format:**
```
/game Player1 vs Player2 <score1> <score2>
```

**Examples:**
```
/game Alex Chen vs Sarah Williams 11 8
/game John Smith vs Jane Doe 15 13
/game Player A vs Player B 11 5
```

**Rules:**
- Player names can have spaces
- Scores must be valid ping pong scores (11+ with 2-point lead, or deuce rules)
- Case insensitive
- Names will auto-create players if they don't exist

---

### Game with Passes

**Format:**
```
/game Player1 vs Player2 <score1> <score2> | <passes1> <passes2>
```

**Example:**
```
/game Alex Chen vs Sarah Williams 11 8 | 87 64
```

**Rules:**
- Passes are optional
- Two integers separated by space after the `|` character
- Represents total passes made by each player

---

### Game with Detailed Stats

**Format:**
```
/game Player1 vs Player2 <score1> <score2> | <passes1> <passes2> | aces1:X,aces2:Y
```

**Example:**
```
/game Alex Chen vs Sarah Williams 11 8 | 87 64 | aces1:2,aces2:1
```

**Stats Available:**
- `aces1:X` - Aces by player 1
- `aces2:Y` - Aces by player 2

---

### Register New Player

**Format:**
```
/register Name | email@example.com | (optional) Phone
```

**Examples:**
```
/register John Smith | john.smith@email.com
/register Jane Doe | jane.doe@email.com | 555-1234
```

**Rules:**
- Name must be 2+ characters
- Email must be valid format
- Phone is optional
- Email must be unique (no duplicates)

---

### View Rankings

**Command:**
```
/stats
```

Shows top 10 players with:
- Win count
- Total games
- Win percentage
- Total passes

---

### List All Players

**Command:**
```
/players
```

Shows all active players in alphabetical order

---

### Delete Last Game (Admin Only)

**Command:**
```
/undo
```

**Note:** Only users in `$ALLOWED_ADMINS` array can use this

---

## Database Operations

### What Happens When You Submit a Game

1. **Parse Message** - Extract player names and scores
2. **Validate Score** - Ensure it's a valid ping pong score
3. **Get/Create Players** - Find players in database or create new ones
4. **Insert Game** - Record game in `games` table
5. **Insert Stats** - Record player stats in `games_stats` table
6. **Update Aggregates** - Refresh `passes_stats` table with calculated totals
7. **Send Confirmation** - Bot replies with success message

### Database Tables Updated

**games**
- game_id
- player1_id / player2_id
- player1_score / player2_score
- winner_id
- match_date
- match_time

**games_stats**
- game_id
- player_id
- points_scored
- points_allowed
- total_passes
- aces
- winner (boolean)

**passes_stats**
- player_id
- total_games
- total_wins / total_losses
- total_passes
- win_percentage
- pass_accuracy
- avg_passes_per_game

**players** (auto-created if needed)
- name
- email (auto-generated: firstname.lastname@telegram.local)
- status (active)

---

## Scoring Rules

### Valid Scores

First to 11 points with 2-point lead:
- ✅ 11-8, 11-9, 11-5, 11-0
- ✅ 12-10, 13-11, 14-12 (deuce)
- ❌ 10-8, 9-7 (need 11 minimum)
- ❌ 11-11, 12-11 (need 2-point lead)

---

## Response Messages

### Success
```
✅ Game recorded!

Alex Chen 🏓 Sarah Williams
11-8

🏆 Winner: Alex Chen
| Passes: 87-64
```

### Errors

**Invalid format:**
```
❌ Parse Error: Missing " vs " separator between players

Expected format: `Player1 vs Player2 score1 score2` [| passes1 passes2]
```

**Invalid score:**
```
❌ Score Error: Invalid score. Winning score must be 11 with 2-point lead.
Scores must be positive integers (e.g., 11-8, 15-13)
```

**Player not found:**
```
❌ Player Error: Could not find or create players. Try /register first.
```

**Already registered:**
```
❌ Error: Email already registered
```

---

## Logging and Debugging

### Enable Debug Mode

In `telegram_bot.php`, all actions are logged to PHP error_log:

```php
error_log('Game recorded: Player1 vs Player2 11-8');
error_log('New player registered: John Doe (john.doe@email.com)');
error_log('Parse error: Missing " vs " separator');
```

### Check Logs

On your server:
```bash
# Usually in:
tail -f /var/log/php-fpm/error.log
# or
tail -f /home/username/public_html/error_log
# or check your hosting control panel logs
```

### Test Webhook

```bash
# Check if webhook is registered
curl https://api.telegram.org/botYOUR_TOKEN/getWebhookInfo

# Should return:
# {
#   "ok": true,
#   "result": {
#     "url": "https://yourdomain.com/telegram_bot.php",
#     "has_custom_certificate": false,
#     "pending_update_count": 0,
#     "last_error_date": null
#   }
# }
```

### Test Locally

Set webhook to a local tunnel:
```bash
# Install ngrok or similar
ngrok http 80

# Then use the ngrok URL as webhook
curl -X POST https://api.telegram.org/botYOUR_TOKEN/setWebhook \
  -d url=https://abc123.ngrok.io/telegram_bot.php
```

---

## Complete Workflow Example

### Day 1: Group Setup

1. Create group "Ping Pong Club"
2. Add @pong_scoreboard_bot
3. Register first batch of players:

```
/register Alex Chen | alex.chen@email.com | 555-0101
/register Sarah Williams | sarah.w@email.com | 555-0102
/register Marcus Johnson | marcus.j@email.com | 555-0103
```

### Day 1: Record Games

```
/game Alex Chen vs Sarah Williams 11 8 | 87 64
✅ Game recorded!

/game Sarah Williams vs Marcus Johnson 11 9 | 92 88
✅ Game recorded!
```

### Day 1: Check Rankings

```
/stats

📊 Player Rankings:

1. Alex Chen
   Wins: 1 | Games: 1 | Win %: 100% | Passes: 87

2. Sarah Williams
   Wins: 1 | Games: 2 | Win %: 50% | Passes: 152

3. Marcus Johnson
   Wins: 0 | Games: 1 | Win %: 0% | Passes: 88
```

### Day 2: More Games

```
/game Alex Chen vs Marcus Johnson 11 6 | 98 72
✅ Game recorded!

/stats
📊 Player Rankings:

1. Alex Chen
   Wins: 2 | Games: 2 | Win %: 100% | Passes: 185

... and so on
```

---

## Troubleshooting

### Bot Doesn't Respond

1. **Check webhook status:**
   ```bash
   curl https://api.telegram.org/botYOUR_TOKEN/getWebhookInfo
   ```

2. **Verify token is correct** in `telegram_bot.php`

3. **Check PHP error logs** for exceptions

4. **Verify HTTPS is working** (webhook must be HTTPS)

5. **Test with a simple cURL:**
   ```bash
   curl -X POST https://yourdomain.com/telegram_bot.php \
     -H "Content-Type: application/json" \
     -d '{
       "update_id": 123,
       "message": {
         "chat": {"id": -1001234567890},
         "from": {"id": 123456},
         "text": "/help"
       }
     }'
   ```

### "Player not found" Error

- Make sure you registered the player first with `/register`
- Or bot will auto-create players from game messages
- Check spelling matches exactly

### Database Connection Error

1. Verify credentials in `telegram_bot.php`
2. Check MySQL is running
3. Verify user has database permissions:
   ```sql
   GRANT ALL ON ping_pong_scoreboard.* TO 'user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Players Auto-Creating with Wrong Names

Message parsing issue. Check:
- Names have consistent capitalization
- No extra spaces: `"Alex Chen"` not `"Alex  Chen"`
- Separators are correct: ` vs ` (space-vs-space)

Example that will auto-create if name doesn't match exactly:
```
/game alex chen vs sarah williams 11 8   ❌ Wrong case
/game Alex Chen vs Sarah Williams 11 8  ✅ Correct
```

### Webhook Keeps Failing

1. Check file permissions:
   ```bash
   chmod 755 telegram_bot.php
   ```

2. Verify database connection in webhook context

3. Remove and re-add webhook:
   ```bash
   curl https://api.telegram.org/botYOUR_TOKEN/deleteWebhook
   
   curl -X POST https://api.telegram.org/botYOUR_TOKEN/setWebhook \
     -d url=https://yourdomain.com/telegram_bot.php
   ```

---

## Security Notes

✅ **Implemented:**
- Input validation (email format, score ranges)
- Prepared statements (SQL injection prevention)
- User ID checks for admin commands
- HTTPS requirement for webhook
- Message sanitization

⚠️ **Recommendations for Production:**
- Add rate limiting (prevent spam)
- Implement CAPTCHA for registration
- Add moderation commands
- Log all changes with user attribution
- Set up admin approval for new players
- Use TELEGRAM_GROUP_ID check for group-only commands

---

## API Reference

### Message Object Structure

```json
{
  "update_id": 123456789,
  "message": {
    "message_id": 1,
    "date": 1234567890,
    "chat": {
      "id": -1001234567890,
      "type": "group"
    },
    "from": {
      "id": 123456,
      "is_bot": false,
      "first_name": "John"
    },
    "text": "/game Player1 vs Player2 11 8"
  }
}
```

---

## Advanced Features

### Add Points Tracking

Modify `games_stats` insert to include:
```php
'points_scored' => $parsed['data']['score1'],
'points_allowed' => $parsed['data']['score2']
```

### Add Match Duration

```
/game Player1 vs Player2 11 8 25
```

Parse match duration (minutes) from message

### Add Location Tracking

```
/game@hallA Player1 vs Player2 11 8
```

Parse location from message prefix

---

## Support

For issues or questions:
1. Check logs in `error_log`
2. Test webhook with cURL
3. Verify database schema matches
4. Review message format requirements
