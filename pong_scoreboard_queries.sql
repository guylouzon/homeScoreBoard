-- Ping Pong Scoreboard App - SELECT Queries
-- Queries to pull data for each UI component/box

-- ============================================================================
-- BOX 1: LATEST GAME RESULT
-- ============================================================================
-- Pulls the most recent game with both players' details and pass counts
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
ORDER BY g.match_date DESC, g.match_time DESC
LIMIT 1;

-- Alternative: If you want to also display the 3 most recent games (for pagination)
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
LIMIT 3;

-- ============================================================================
-- BOX 2: MOST PASSES (TOP 3 PLAYERS BY PASSES IN RECENT GAMES)
-- ============================================================================
-- Pulls top 3 players by pass count from the 3 most recent games
SELECT 
  p.id as player_id,
  p.name,
  SUM(gs.total_passes) as total_passes_in_recent_games
FROM games g
JOIN games_stats gs ON g.id = gs.game_id
JOIN players p ON gs.player_id = p.id
WHERE g.match_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  AND p.status = 'active'
GROUP BY p.id, p.name
ORDER BY total_passes_in_recent_games DESC
LIMIT 3;

-- Alternative: Top 3 from the last 3 games only
WITH recent_games AS (
  SELECT id
  FROM games
  ORDER BY match_date DESC, match_time DESC
  LIMIT 3
)
SELECT 
  p.id as player_id,
  p.name,
  SUM(gs.total_passes) as total_passes
FROM recent_games rg
JOIN games_stats gs ON rg.id = gs.game_id
JOIN players p ON gs.player_id = p.id
GROUP BY p.id, p.name
ORDER BY total_passes DESC
LIMIT 3;

-- ============================================================================
-- BOX 3: PLAYER RANKINGS (LEADERBOARD)
-- ============================================================================
-- Pulls all active players ranked by wins with stats
SELECT 
  p.id as player_id,
  p.name,
  ps.total_wins as wins,
  ps.total_passes as passes,
  ps.total_games as matches,
  ps.pass_accuracy,
  ps.avg_passes_per_game,
  ps.win_percentage,
  RANK() OVER (ORDER BY ps.total_wins DESC, ps.win_percentage DESC) as rank
FROM players p
LEFT JOIN passes_stats ps ON p.id = ps.player_id
WHERE p.status = 'active'
ORDER BY ps.total_wins DESC, ps.win_percentage DESC, p.name ASC;

-- Alternative: With calculated stats if passes_stats table not populated
SELECT 
  p.id as player_id,
  p.name,
  COALESCE(COUNT(CASE WHEN g.winner_id = p.id THEN 1 END), 0) as wins,
  COALESCE(SUM(gs.total_passes), 0) as passes,
  COALESCE(COUNT(DISTINCT g.id), 0) as matches,
  COALESCE(
    ROUND(
      (SUM(CASE WHEN gs.successful_passes > 0 THEN gs.successful_passes ELSE 0 END) / 
       SUM(CASE WHEN gs.total_passes > 0 THEN gs.total_passes ELSE 1 END)) * 100, 
      2
    ), 
    0
  ) as pass_accuracy,
  COALESCE(
    ROUND(SUM(gs.total_passes) / NULLIF(COUNT(DISTINCT g.id), 0), 2), 
    0
  ) as avg_passes_per_game,
  COALESCE(
    ROUND(
      (COUNT(CASE WHEN g.winner_id = p.id THEN 1 END) / 
       NULLIF(COUNT(DISTINCT g.id), 0)) * 100, 
      2
    ), 
    0
  ) as win_percentage,
  RANK() OVER (
    ORDER BY 
      COUNT(CASE WHEN g.winner_id = p.id THEN 1 END) DESC,
      (COUNT(CASE WHEN g.winner_id = p.id THEN 1 END) / 
       NULLIF(COUNT(DISTINCT g.id), 0)) DESC
  ) as rank
FROM players p
LEFT JOIN games g ON (p.id = g.player1_id OR p.id = g.player2_id)
LEFT JOIN games_stats gs ON g.id = gs.game_id AND gs.player_id = p.id
WHERE p.status = 'active'
GROUP BY p.id, p.name
ORDER BY wins DESC, win_percentage DESC, p.name ASC;

-- ============================================================================
-- BONUS: COMBINED QUERY FOR DASHBOARD (All 3 boxes)
-- ============================================================================
-- Get all data needed for the dashboard in one efficient query structure

-- Get Latest Game
SELECT 
  'latest_game' as data_type,
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
  NULL as rank,
  NULL as stat_type
FROM games g
JOIN players p1 ON g.player1_id = p1.id
JOIN players p2 ON g.player2_id = p2.id
LEFT JOIN games_stats gs1 ON g.id = gs1.game_id AND gs1.player_id = g.player1_id
LEFT JOIN games_stats gs2 ON g.id = gs2.game_id AND gs2.player_id = g.player2_id
WHERE p1.status = 'active' AND p2.status = 'active'
ORDER BY g.match_date DESC, g.match_time DESC
LIMIT 1;

-- ============================================================================
-- QUERIES WITH FILTERS (Examples for future use)
-- ============================================================================

-- Get recent games with date range filter
SELECT 
  g.id as game_id,
  g.match_date,
  g.match_time,
  p1.name as player1_name,
  g.player1_score,
  p2.name as player2_name,
  g.player2_score,
  gs1.total_passes as player1_passes,
  gs2.total_passes as player2_passes,
  CASE WHEN g.winner_id = g.player1_id THEN p1.name ELSE p2.name END as winner_name
FROM games g
JOIN players p1 ON g.player1_id = p1.id
JOIN players p2 ON g.player2_id = p2.id
LEFT JOIN games_stats gs1 ON g.id = gs1.game_id AND gs1.player_id = g.player1_id
LEFT JOIN games_stats gs2 ON g.id = gs2.game_id AND gs2.player_id = g.player2_id
WHERE g.match_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
  AND p1.status = 'active' 
  AND p2.status = 'active'
ORDER BY g.match_date DESC, g.match_time DESC
LIMIT 10;

-- Get player stats for a specific player
SELECT 
  p.id,
  p.name,
  ps.total_wins,
  ps.total_losses,
  ps.total_games,
  ps.total_passes,
  ps.pass_accuracy,
  ps.avg_passes_per_game,
  ps.win_percentage,
  ps.avg_points_per_game,
  ps.last_updated
FROM players p
LEFT JOIN passes_stats ps ON p.id = ps.player_id
WHERE p.id = ? AND p.status = 'active';

-- Get head-to-head stats between two players
SELECT 
  COUNT(g.id) as total_matches,
  SUM(CASE WHEN g.winner_id = p1.id THEN 1 ELSE 0 END) as player1_wins,
  SUM(CASE WHEN g.winner_id = p2.id THEN 1 ELSE 0 END) as player2_wins,
  AVG(CASE WHEN g.player1_id = p1.id THEN g.player1_score ELSE g.player2_score END) as player1_avg_score,
  AVG(CASE WHEN g.player1_id = p2.id THEN g.player1_score ELSE g.player2_score END) as player2_avg_score,
  SUM(CASE WHEN gs.player_id = p1.id THEN gs.total_passes ELSE 0 END) as player1_total_passes,
  SUM(CASE WHEN gs.player_id = p2.id THEN gs.total_passes ELSE 0 END) as player2_total_passes
FROM games g
JOIN games_stats gs ON g.id = gs.game_id
JOIN players p1 ON ? = p1.id
JOIN players p2 ON ? = p2.id
WHERE (g.player1_id = p1.id AND g.player2_id = p2.id)
   OR (g.player1_id = p2.id AND g.player2_id = p1.id);

-- Get games by specific player
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
ORDER BY g.match_date DESC, g.match_time DESC;
