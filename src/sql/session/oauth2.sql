CREATE TABLE IF NOT EXISTS `oauth2_subjects` (
    `subject_id` char(36) NOT NULL,
    `login` varchar(255) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`subject_id`),
    UNIQUE KEY `uq_oauth2_subjects_login` (`login`),
    CONSTRAINT `fk_oauth2_subjects_user`
        FOREIGN KEY (`login`) REFERENCES `macc_users` (`login`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `oauth2_clients` (
    `client_id` varchar(128) NOT NULL,
    `name` varchar(255) NOT NULL,
    `redirect_uris` text NOT NULL,
    `grant_types` text NOT NULL,
    `allowed_scopes` text NOT NULL,
    `is_confidential` tinyint(1) NOT NULL DEFAULT 1,
    `secret_hash` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`client_id`),
    KEY `idx_oauth2_clients_active` (`is_active`)
);

CREATE TABLE IF NOT EXISTS `oauth2_authorization_codes` (
    `code_id` varchar(128) NOT NULL,
    `client_id` varchar(128) NOT NULL,
    `subject_id` char(36) NOT NULL,
    `redirect_uri` text DEFAULT NULL,
    `scopes` text NOT NULL,
    `expires_at` datetime NOT NULL,
    `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`code_id`),
    KEY `idx_oauth2_auth_codes_expiry` (`expires_at`),
    CONSTRAINT `fk_oauth2_auth_codes_client`
        FOREIGN KEY (`client_id`) REFERENCES `oauth2_clients` (`client_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_oauth2_auth_codes_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `oauth2_subjects` (`subject_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `oauth2_access_tokens` (
    `token_id` varchar(128) NOT NULL,
    `client_id` varchar(128) NOT NULL,
    `subject_id` char(36) DEFAULT NULL,
    `scopes` text NOT NULL,
    `expires_at` datetime NOT NULL,
    `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token_id`),
    KEY `idx_oauth2_access_tokens_expiry` (`expires_at`),
    KEY `idx_oauth2_access_tokens_subject` (`subject_id`),
    CONSTRAINT `fk_oauth2_access_tokens_client`
        FOREIGN KEY (`client_id`) REFERENCES `oauth2_clients` (`client_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_oauth2_access_tokens_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `oauth2_subjects` (`subject_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `oauth2_refresh_tokens` (
    `refresh_token_id` varchar(128) NOT NULL,
    `access_token_id` varchar(128) NOT NULL,
    `expires_at` datetime NOT NULL,
    `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`refresh_token_id`),
    KEY `idx_oauth2_refresh_tokens_expiry` (`expires_at`),
    CONSTRAINT `fk_oauth2_refresh_tokens_access_token`
        FOREIGN KEY (`access_token_id`) REFERENCES `oauth2_access_tokens` (`token_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
);
