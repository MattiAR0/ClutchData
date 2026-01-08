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
    hltv_match_id VARCHAR(50) DEFAULT NULL,
    match_details JSON DEFAULT NULL,
    ai_explanation TEXT DEFAULT NULL,
    ai_source ENUM('gemini', 'elo', 'fallback') DEFAULT 'elo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para estadísticas avanzadas de jugadores (VLR.gg / HLTV)
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
    rating DECIMAL(3,2) DEFAULT NULL,
    first_bloods INT DEFAULT NULL,
    first_deaths INT DEFAULT NULL,
    clutches VARCHAR(20) DEFAULT NULL,
    data_source ENUM('liquipedia', 'vlr', 'hltv') DEFAULT 'liquipedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    INDEX idx_match_id (match_id),
    INDEX idx_player_name (player_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para equipos
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    game_type ENUM('valorant', 'lol', 'cs2') NOT NULL,
    region VARCHAR(50) DEFAULT 'Other',
    country VARCHAR(100) DEFAULT NULL,
    logo_url VARCHAR(512) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    liquipedia_url VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team (name, game_type),
    INDEX idx_game_type (game_type),
    INDEX idx_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para jugadores
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(100) NOT NULL,
    real_name VARCHAR(255) DEFAULT NULL,
    team_id INT DEFAULT NULL,
    game_type ENUM('valorant', 'lol', 'cs2') NOT NULL,
    country VARCHAR(100) DEFAULT NULL,
    role VARCHAR(50) DEFAULT NULL,
    photo_url VARCHAR(512) DEFAULT NULL,
    birthdate DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    liquipedia_url VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    UNIQUE KEY unique_player (nickname, game_type),
    INDEX idx_team (team_id),
    INDEX idx_game (game_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

