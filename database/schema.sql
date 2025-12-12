CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_type ENUM('valorant', 'lol', 'cs2') NOT NULL,
    team1_name VARCHAR(255) NOT NULL,
    team2_name VARCHAR(255) NOT NULL,
    tournament_name VARCHAR(255),
    match_time DATETIME,
    match_region VARCHAR(50) DEFAULT 'Other',
    team1_score INT DEFAULT NULL,
    team2_score INT DEFAULT NULL,
    match_status ENUM('upcoming', 'live', 'completed') DEFAULT 'upcoming',
    match_url VARCHAR(512) DEFAULT NULL,
    bo_type VARCHAR(20) DEFAULT NULL,
    ai_prediction FLOAT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
