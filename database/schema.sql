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
    match_importance INT DEFAULT 0,
    vlr_match_id VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para estadísticas avanzadas de jugadores (VLR.gg)
CREATE TABLE IF NOT EXISTS player_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_name VARCHAR(100) NOT NULL,
    team_name VARCHAR(255) DEFAULT NULL,
    agent VARCHAR(50) DEFAULT NULL,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    acs INT DEFAULT NULL,
    adr DECIMAL(5,1) DEFAULT NULL,
    kast DECIMAL(4,1) DEFAULT NULL,
    hs_percent DECIMAL(4,1) DEFAULT NULL,
    first_bloods INT DEFAULT NULL,
    first_deaths INT DEFAULT NULL,
    clutches VARCHAR(20) DEFAULT NULL,
    data_source ENUM('liquipedia', 'vlr') DEFAULT 'liquipedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    INDEX idx_match_id (match_id),
    INDEX idx_player_name (player_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
