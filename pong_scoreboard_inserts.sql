-- Ping Pong Scoreboard Database - INSERT Queries
-- Comprehensive INSERT statements with foreign key handling and transactions

-- ============================================================================
-- SETUP: Disable foreign key checks for bulk insert (if needed)
-- ============================================================================
-- SET FOREIGN_KEY_CHECKS=0;  -- Uncomment to disable temporarily
-- ... run inserts ...
-- SET FOREIGN_KEY_CHECKS=1;  -- Re-enable after inserts


-- ============================================================================
-- TABLE 1: INSERT PLAYERS
-- ============================================================================

-- Single Player Insert
INSERT INTO players (name, email, phone, join_date, status)
VALUES ('Alex Chen', 'alex.chen@email.com', '555-0101', NOW(), 'active');

-- Multiple Players Insert (Batch)
INSERT INTO players (name, email, phone, join_date, status)
VALUES 
  ('Alex Chen', 'alex.chen@email.com', '555-0101', NOW(), 'active'),
  ('Sarah Williams', 'sarah.w@email.com', '555-0102', NOW(), 'active'),
  ('Marcus Johnson', 'marcus.j@email.com', '555-0103', NOW(), 'active'),
  ('James Rodriguez', 'james.r@email.com', '555-0104', NOW(), 'active'),
  ('Emily Chen', 'emily.chen@email.com', '555-0105', NOW(), 'active'),
  ('David Park', 'david.park@email.com', '555-0106', NOW(), 'active');

-- Insert with ON DUPLICATE KEY UPDATE (for upsert/update if exists)
INSERT INTO players (name, email, phone, status)
VALUES ('Alex Chen', 'alex.chen@email.com', '555-0101', 'active')
ON DUPLICATE KEY UPDATE 
  email = VALUES(email),
  phone = VALUES(phone),
  status = VALUES(status),
  updated_at = NOW();

-- ============================================================================
-- TABLE 2: INSERT GAMES
-- ============================================================================
-- Note: player1_id, player2_id, and winner_id must exist in players table

-- Single Game Insert with Explicit Foreign Keys
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes, location)
VALUES (
  1,                  -- player1_id (must exist in players table)
  2,                  -- player2_id (must exist in players table)
  11,                 -- player1_score
  8,                  -- player2_score
  1,                  -- winner_id (must be either player1_id or player2_id)
  '2024-03-26',       -- match_date
  '18:45:00',         -- match_time
  25,                 -- duration_minutes
  'Club Hall A'       -- location
);

-- Multiple Games Insert (Batch)
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes, location)
VALUES 
  (1, 2, 11, 8, 1, '2024-03-26', '18:45:00', 25, 'Club Hall A'),
  (3, 4, 11, 9, 3, '2024-03-26', '17:20:00', 28, 'Club Hall B'),
  (1, 3, 6, 11, 3, '2024-03-25', '19:10:00', 22, 'Club Hall A'),
  (2, 4, 10, 11, 4, '2024-03-24', '18:00:00', 26, 'Club Hall C'),
  (5, 6, 11, 7, 5, '2024-03-24', '19:30:00', 20, 'Club Hall B');

-- Insert Game with subquery to get player IDs by name (safer for data entry)
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes, location)
SELECT 
  (SELECT id FROM players WHERE name = 'Alex Chen' AND status = 'active') as player1_id,
  (SELECT id FROM players WHERE name = 'Sarah Williams' AND status = 'active') as player2_id,
  11 as player1_score,
  8 as player2_score,
  (SELECT id FROM players WHERE name = 'Alex Chen' AND status = 'active') as winner_id,
  '2024-03-27' as match_date,
  '19:00:00' as match_time,
  25 as duration_minutes,
  'Club Hall A' as location;

-- Insert Multiple Games Using Player Name Lookups (Batch with Subqueries)
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes, location)
SELECT 
  p1.id as player1_id,
  p2.id as player2_id,
  game_data.player1_score,
  game_data.player2_score,
  CASE 
    WHEN game_data.player1_score > game_data.player2_score THEN p1.id
    ELSE p2.id
  END as winner_id,
  game_data.match_date,
  game_data.match_time,
  game_data.duration_minutes,
  game_data.location
FROM (
  SELECT 'Alex Chen' as player1, 'Marcus Johnson' as player2, 11 as player1_score, 8 as player2_score, '2024-03-27' as match_date, '19:00:00' as match_time, 25 as duration_minutes, 'Club Hall A' as location
  UNION ALL
  SELECT 'Sarah Williams', 'James Rodriguez', 11, 9, '2024-03-27', '20:30:00', 28, 'Club Hall B'
  UNION ALL
  SELECT 'Emily Chen', 'David Park', 11, 6, '2024-03-26', '18:15:00', 22, 'Club Hall C'
) as game_data
JOIN players p1 ON game_data.player1 = p1.name AND p1.status = 'active'
JOIN players p2 ON game_data.player2 = p2.name AND p2.status = 'active';

-- ============================================================================
-- TABLE 3: INSERT GAMES_STATS
-- ============================================================================
-- Note: game_id and player_id must exist in games and players tables respectively

-- Single Games Stats Insert
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, errors, aces, winner)
VALUES (
  1,                  -- game_id (must exist in games table)
  1,                  -- player_id (must exist in players table)
  11,                 -- points_scored
  8,                  -- points_allowed
  87,                 -- total_passes
  78,                 -- successful_passes
  9,                  -- errors
  2,                  -- aces
  TRUE                -- winner
);

-- Insert Games Stats for Both Players in a Game
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, errors, aces, winner)
VALUES 
  (1, 1, 11, 8, 87, 78, 9, 2, TRUE),   -- Player 1 of Game 1 (winner)
  (1, 2, 8, 11, 64, 58, 6, 1, FALSE);  -- Player 2 of Game 1 (loser)

-- Insert Multiple Games Stats for Multiple Games (Batch)
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, errors, aces, winner)
VALUES 
  -- Game 1: Alex Chen vs Sarah Williams (Alex wins)
  (1, 1, 11, 8, 87, 78, 9, 2, TRUE),
  (1, 2, 8, 11, 64, 58, 6, 1, FALSE),
  
  -- Game 2: Marcus Johnson vs James Rodriguez (Marcus wins)
  (2, 3, 11, 9, 92, 83, 9, 3, TRUE),
  (2, 4, 9, 11, 88, 79, 9, 2, FALSE),
  
  -- Game 3: Alex Chen vs Marcus Johnson (Marcus wins)
  (3, 1, 6, 11, 71, 64, 7, 1, FALSE),
  (3, 3, 11, 6, 98, 88, 10, 4, TRUE),
  
  -- Game 4: Sarah Williams vs James Rodriguez (James wins)
  (4, 2, 10, 11, 82, 73, 9, 2, FALSE),
  (4, 4, 11, 10, 85, 76, 9, 3, TRUE),
  
  -- Game 5: Emily Chen vs David Park (Emily wins)
  (5, 5, 11, 7, 76, 69, 7, 2, TRUE),
  (5, 6, 7, 11, 63, 56, 7, 1, FALSE);

-- Insert Games Stats Using INSERT SELECT (Derived from Games Table)
-- This example assumes we have calculated stats in another table or source
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, errors, aces, winner)
SELECT 
  g.id as game_id,
  g.player1_id as player_id,
  g.player1_score as points_scored,
  g.player2_score as points_allowed,
  FLOOR(RAND() * (100 - 60) + 60) as total_passes,           -- Random between 60-100
  FLOOR(RAND() * (90 - 50) + 50) as successful_passes,       -- Random between 50-90
  FLOOR(RAND() * 15) as errors,                              -- Random 0-15
  FLOOR(RAND() * 5) as aces,                                 -- Random 0-5
  (g.winner_id = g.player1_id) as winner                     -- TRUE if player1 won
FROM games g
LEFT JOIN games_stats gs ON g.id = gs.game_id AND gs.player_id = g.player1_id
WHERE gs.id IS NULL                                          -- Only insert if not already exists
  AND g.id NOT IN (SELECT DISTINCT game_id FROM games_stats) -- Avoid duplicates;

UNION ALL

SELECT 
  g.id as game_id,
  g.player2_id as player_id,
  g.player2_score as points_scored,
  g.player1_score as points_allowed,
  FLOOR(RAND() * (100 - 60) + 60) as total_passes,
  FLOOR(RAND() * (90 - 50) + 50) as successful_passes,
  FLOOR(RAND() * 15) as errors,
  FLOOR(RAND() * 5) as aces,
  (g.winner_id = g.player2_id) as winner
FROM games g
LEFT JOIN games_stats gs ON g.id = gs.game_id AND gs.player_id = g.player2_id
WHERE gs.id IS NULL
  AND g.id NOT IN (SELECT DISTINCT game_id FROM games_stats);

-- ============================================================================
-- TABLE 4: INSERT PASSES_STATS (Aggregated Statistics)
-- ============================================================================
-- Note: player_id must exist in players table
-- This table is typically populated via INSERT SELECT from aggregated games_stats data

-- Single Player Passes Stats Insert
INSERT INTO passes_stats (player_id, total_games, total_passes, successful_passes, pass_accuracy, avg_passes_per_game, total_wins, total_losses, win_percentage, total_points, avg_points_per_game)
VALUES (
  1,                  -- player_id
  15,                 -- total_games
  1087,               -- total_passes
  978,                -- successful_passes
  89.97,              -- pass_accuracy
  72.47,              -- avg_passes_per_game
  12,                 -- total_wins
  3,                  -- total_losses
  80.00,              -- win_percentage
  167,                -- total_points
  11.13               -- avg_points_per_game
);

-- Insert Multiple Players Passes Stats (Batch)
INSERT INTO passes_stats (player_id, total_games, total_passes, successful_passes, pass_accuracy, avg_passes_per_game, total_wins, total_losses, win_percentage, total_points, avg_points_per_game)
VALUES 
  (1, 15, 1087, 978, 89.97, 72.47, 12, 3, 80.00, 167, 11.13),
  (2, 14, 1142, 1027, 89.93, 81.57, 11, 3, 78.57, 153, 10.93),
  (3, 14, 945, 850, 89.95, 67.50, 9, 5, 64.29, 142, 10.14),
  (4, 13, 912, 820, 89.91, 70.15, 8, 5, 61.54, 132, 10.15),
  (5, 12, 834, 750, 89.94, 69.50, 7, 5, 58.33, 119, 9.92),
  (6, 11, 756, 680, 89.95, 68.73, 6, 5, 54.55, 104, 9.45);

-- ============================================================================
-- BEST PRACTICE: INSERT WITH TRANSACTION
-- ============================================================================
-- Use transactions to ensure data integrity when inserting related records

START TRANSACTION;

-- Insert new player
INSERT INTO players (name, email, phone, status)
VALUES ('New Player', 'newplayer@email.com', '555-0999', 'active');

-- Get the ID of the newly inserted player
SET @new_player_id = LAST_INSERT_ID();

-- Insert a game using the new player
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time)
SELECT 
  @new_player_id as player1_id,
  1 as player2_id,  -- Against player with ID 1
  11 as player1_score,
  9 as player2_score,
  @new_player_id as winner_id,
  CURDATE() as match_date,
  CURTIME() as match_time;

-- Get the ID of the newly inserted game
SET @new_game_id = LAST_INSERT_ID();

-- Insert game stats for both players
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, winner)
VALUES 
  (@new_game_id, @new_player_id, 11, 9, 87, 78, TRUE),
  (@new_game_id, 1, 9, 11, 76, 68, FALSE);

COMMIT;

-- Rollback if there's an error:
-- ROLLBACK;

-- ============================================================================
-- ADVANCED: INSERT SELECT FOR PASSES_STATS POPULATION
-- ============================================================================
-- This populates the passes_stats table from aggregated games_stats data
-- Execute this after inserting games_stats to keep stats updated

INSERT INTO passes_stats (player_id, total_games, total_passes, successful_passes, pass_accuracy, avg_passes_per_game, total_wins, total_losses, win_percentage, total_points, avg_points_per_game)
SELECT 
  p.id as player_id,
  COUNT(DISTINCT gs.game_id) as total_games,
  COALESCE(SUM(gs.total_passes), 0) as total_passes,
  COALESCE(SUM(gs.successful_passes), 0) as successful_passes,
  ROUND(
    COALESCE(
      (SUM(gs.successful_passes) / SUM(gs.total_passes)) * 100,
      0
    ),
    2
  ) as pass_accuracy,
  ROUND(
    COALESCE(
      SUM(gs.total_passes) / NULLIF(COUNT(DISTINCT gs.game_id), 0),
      0
    ),
    2
  ) as avg_passes_per_game,
  SUM(CASE WHEN gs.winner = TRUE THEN 1 ELSE 0 END) as total_wins,
  SUM(CASE WHEN gs.winner = FALSE THEN 1 ELSE 0 END) as total_losses,
  ROUND(
    (SUM(CASE WHEN gs.winner = TRUE THEN 1 ELSE 0 END) / 
     NULLIF(COUNT(DISTINCT gs.game_id), 0)) * 100,
    2
  ) as win_percentage,
  SUM(gs.points_scored) as total_points,
  ROUND(
    SUM(gs.points_scored) / NULLIF(COUNT(DISTINCT gs.game_id), 0),
    2
  ) as avg_points_per_game
FROM players p
LEFT JOIN games_stats gs ON p.id = gs.player_id
WHERE p.status = 'active'
GROUP BY p.id
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
  last_updated = NOW();

-- ============================================================================
-- UTILITY: UPDATE PASSES_STATS FROM GAMES_STATS (For sync after new games)
-- ============================================================================
-- Use this query to refresh passes_stats after new games have been played

UPDATE passes_stats ps
SET 
  total_games = (
    SELECT COUNT(DISTINCT game_id) 
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  total_passes = (
    SELECT COALESCE(SUM(total_passes), 0) 
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  successful_passes = (
    SELECT COALESCE(SUM(successful_passes), 0) 
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  total_wins = (
    SELECT COUNT(*) 
    FROM games_stats 
    WHERE player_id = ps.player_id AND winner = TRUE
  ),
  total_losses = (
    SELECT COUNT(*) 
    FROM games_stats 
    WHERE player_id = ps.player_id AND winner = FALSE
  ),
  total_points = (
    SELECT COALESCE(SUM(points_scored), 0) 
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  pass_accuracy = (
    SELECT ROUND(
      (SUM(successful_passes) / SUM(total_passes)) * 100, 2
    )
    FROM games_stats 
    WHERE player_id = ps.player_id AND total_passes > 0
  ),
  avg_passes_per_game = (
    SELECT ROUND(
      SUM(total_passes) / COUNT(DISTINCT game_id), 2
    )
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  win_percentage = (
    SELECT ROUND(
      (SUM(CASE WHEN winner = TRUE THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2
    )
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  avg_points_per_game = (
    SELECT ROUND(
      SUM(points_scored) / COUNT(DISTINCT game_id), 2
    )
    FROM games_stats 
    WHERE player_id = ps.player_id
  ),
  last_updated = NOW();

-- ============================================================================
-- ERROR HANDLING: EXAMPLES OF FOREIGN KEY VIOLATIONS
-- ============================================================================

-- This would fail (player_id doesn't exist):
-- INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time)
-- VALUES (999, 1, 11, 8, 999, NOW(), NOW());
-- ERROR: Foreign key constraint fails

-- This would fail (winner_id is neither player1_id nor player2_id):
-- INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time)
-- VALUES (1, 2, 11, 8, 3, NOW(), NOW());
-- ERROR: Check constraint fails

-- ============================================================================
-- SAMPLE DATA INITIALIZATION SCRIPT
-- ============================================================================
-- Run this complete script to populate the database with sample data

TRUNCATE TABLE games_stats;
TRUNCATE TABLE games;
TRUNCATE TABLE passes_stats;
TRUNCATE TABLE players;

-- Insert all players
INSERT INTO players (name, email, phone, join_date, status)
VALUES 
  ('Alex Chen', 'alex.chen@email.com', '555-0101', NOW(), 'active'),
  ('Sarah Williams', 'sarah.w@email.com', '555-0102', NOW(), 'active'),
  ('Marcus Johnson', 'marcus.j@email.com', '555-0103', NOW(), 'active'),
  ('James Rodriguez', 'james.r@email.com', '555-0104', NOW(), 'active'),
  ('Emily Chen', 'emily.chen@email.com', '555-0105', NOW(), 'active'),
  ('David Park', 'david.park@email.com', '555-0106', NOW(), 'active');

-- Insert all games
INSERT INTO games (player1_id, player2_id, player1_score, player2_score, winner_id, match_date, match_time, duration_minutes, location)
VALUES 
  (1, 2, 11, 8, 1, '2024-03-26', '18:45:00', 25, 'Club Hall A'),
  (3, 4, 11, 9, 3, '2024-03-26', '17:20:00', 28, 'Club Hall B'),
  (1, 3, 6, 11, 3, '2024-03-25', '19:10:00', 22, 'Club Hall A'),
  (2, 4, 10, 11, 4, '2024-03-24', '18:00:00', 26, 'Club Hall C'),
  (5, 6, 11, 7, 5, '2024-03-24', '19:30:00', 20, 'Club Hall B');

-- Insert all games stats
INSERT INTO games_stats (game_id, player_id, points_scored, points_allowed, total_passes, successful_passes, errors, aces, winner)
VALUES 
  (1, 1, 11, 8, 87, 78, 9, 2, TRUE),
  (1, 2, 8, 11, 64, 58, 6, 1, FALSE),
  (2, 3, 11, 9, 92, 83, 9, 3, TRUE),
  (2, 4, 9, 11, 88, 79, 9, 2, FALSE),
  (3, 1, 6, 11, 71, 64, 7, 1, FALSE),
  (3, 3, 11, 6, 98, 88, 10, 4, TRUE),
  (4, 2, 10, 11, 82, 73, 9, 2, FALSE),
  (4, 4, 11, 10, 85, 76, 9, 3, TRUE),
  (5, 5, 11, 7, 76, 69, 7, 2, TRUE),
  (5, 6, 7, 11, 63, 56, 7, 1, FALSE);

-- Populate passes_stats from aggregated games_stats data
INSERT INTO passes_stats (player_id, total_games, total_passes, successful_passes, pass_accuracy, avg_passes_per_game, total_wins, total_losses, win_percentage, total_points, avg_points_per_game)
SELECT 
  p.id,
  COUNT(DISTINCT gs.game_id) as total_games,
  COALESCE(SUM(gs.total_passes), 0) as total_passes,
  COALESCE(SUM(gs.successful_passes), 0) as successful_passes,
  ROUND(COALESCE((SUM(gs.successful_passes) / SUM(gs.total_passes)) * 100, 0), 2) as pass_accuracy,
  ROUND(COALESCE(SUM(gs.total_passes) / NULLIF(COUNT(DISTINCT gs.game_id), 0), 0), 2) as avg_passes_per_game,
  SUM(CASE WHEN gs.winner = TRUE THEN 1 ELSE 0 END) as total_wins,
  SUM(CASE WHEN gs.winner = FALSE THEN 1 ELSE 0 END) as total_losses,
  ROUND((SUM(CASE WHEN gs.winner = TRUE THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT gs.game_id), 0)) * 100, 2) as win_percentage,
  SUM(gs.points_scored) as total_points,
  ROUND(SUM(gs.points_scored) / NULLIF(COUNT(DISTINCT gs.game_id), 0), 2) as avg_points_per_game
FROM players p
LEFT JOIN games_stats gs ON p.id = gs.player_id
WHERE p.status = 'active'
GROUP BY p.id;
