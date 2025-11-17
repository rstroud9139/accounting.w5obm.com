<?php
// filepath: e:\xampp\htdocs\w5obmcom_admin\w5obm.com\include\SpamPrevention.php

/**
 * SpamPrevention Class
 * 
 * Comprehensive spam detection and prevention system for W5OBM.com
 * Protects all email accounts and contact forms from automated and human spam
 * 
 * @author W5OBM Development Team - Robert Stroud
 * @version 1.0
 * @copyright 2025 W5OBM Amateur Radio Club
 */

class SpamPrevention
{
    private $conn;
    private $config;
    private $log_file;

    // Spam scoring thresholds
    const SPAM_THRESHOLD_LOW = 3;
    const SPAM_THRESHOLD_MEDIUM = 5;
    const SPAM_THRESHOLD_HIGH = 8;
    const SPAM_THRESHOLD_BLOCK = 10;

    // Rate limiting settings
    const RATE_LIMIT_WINDOW = 3600; // 1 hour
    const RATE_LIMIT_MAX_ATTEMPTS = 5;
    const RATE_LIMIT_ESCALATION = 24; // 24 hours for repeat offenders

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
        $this->log_file = __DIR__ . '/../logs/spam_prevention.log';
        $this->config = $this->loadConfiguration();
        $this->initializeTables();
    }

    /**
     * Load configuration (implementation added)
     */
    private function loadConfiguration()
    {
        return [
            'enable_external_checks' => true,
            'strict_mode' => false,
            'whitelist_ham_training' => true,
            'auto_ban_threshold' => 15
        ];
    }

    /**
     * Main spam analysis function
     * Returns comprehensive spam assessment
     */
    public function analyzeContent($data, $context = 'contact_form')
    {
        $analysis = [
            'is_spam' => false,
            'risk_level' => 'LOW',
            'confidence' => 0,
            'score' => 0,
            'flags' => [],
            'recommendations' => [],
            'action' => 'ALLOW'
        ];

        // Layer 1: IP Reputation Check
        $ip_analysis = $this->analyzeIPReputation($data['ip_address'] ?? '');
        $analysis['score'] += $ip_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $ip_analysis['flags']);

        // Layer 2: Content Analysis
        $content_analysis = $this->analyzeContentSpam($data['message'] ?? '');
        $analysis['score'] += $content_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $content_analysis['flags']);

        // Layer 3: Email Reputation
        $email_analysis = $this->analyzeEmailReputation($data['email'] ?? '');
        $analysis['score'] += $email_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $email_analysis['flags']);

        // Layer 4: Form Field Analysis
        $form_analysis = $this->analyzeFormFields($data);
        $analysis['score'] += $form_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $form_analysis['flags']);

        // Layer 5: Pattern Recognition
        $pattern_analysis = $this->analyzeSubmissionPatterns($data, $context);
        $analysis['score'] += $pattern_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $pattern_analysis['flags']);

        // Determine final assessment
        $analysis = $this->calculateFinalAssessment($analysis);

        // Log the analysis
        $this->logSpamAnalysis($data, $analysis, $context);

        return $analysis;
    }

    /**
     * Analyze IP reputation with multiple layers
     */
    private function analyzeIPReputation($ip_address)
    {
        $analysis = ['score' => 0, 'flags' => []];

        if (empty($ip_address) || $ip_address === 'unknown') {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'Unknown IP address';
            return $analysis;
        }

        // Check if IP is blacklisted
        if ($this->isIPBlacklisted($ip_address)) {
            $analysis['score'] += 15;
            $analysis['flags'][] = 'IP address is blacklisted';
            return $analysis; // Immediate high score for blacklisted IPs
        }

        // Check if IP is whitelisted
        if ($this->isIPWhitelisted($ip_address)) {
            $analysis['score'] -= 2;
            $analysis['flags'][] = 'IP address is on whitelist';
            return $analysis;
        }

        // Check rate limiting
        $rate_check = $this->checkRateLimit($ip_address);
        if (!$rate_check['allowed']) {
            $analysis['score'] += $rate_check['severity'];
            $analysis['flags'][] = "Rate limit exceeded: {$rate_check['message']}";
        }

        // Check geographic reputation
        $geo_analysis = $this->analyzeGeographicReputation($ip_address);
        $analysis['score'] += $geo_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $geo_analysis['flags']);

        // Check external reputation services
        if ($this->config['enable_external_checks']) {
            $external_rep = $this->checkExternalIPReputation($ip_address);
            $analysis['score'] += $external_rep['score'];
            $analysis['flags'] = array_merge($analysis['flags'], $external_rep['flags']);
        }

        return $analysis;
    }

    /**
     * Advanced Content Spam Analysis
     */
    private function analyzeContentSpam($content)
    {
        $analysis = ['score' => 0, 'flags' => []];

        if (empty($content)) {
            $analysis['score'] += 5;
            $analysis['flags'][] = 'Empty message content';
            return $analysis;
        }

        // Advanced keyword detection with weighted scoring
        $spam_patterns = $this->getAdvancedSpamPatterns();
        $keyword_score = 0;
        $detected_categories = [];

        foreach ($spam_patterns as $category => $patterns) {
            $category_matches = 0;
            foreach ($patterns['keywords'] as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    $keyword_score += $patterns['weight'];
                    $category_matches++;
                }
            }
            if ($category_matches > 0) {
                $detected_categories[] = ucfirst(str_replace('_', ' ', $category));
            }
        }

        $analysis['score'] += min(8, $keyword_score); // Cap keyword score
        if (!empty($detected_categories)) {
            $analysis['flags'][] = 'Spam keywords detected in: ' . implode(', ', $detected_categories);
        }

        // Content length analysis
        $length_analysis = $this->analyzeContentLength($content);
        $analysis['score'] += $length_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $length_analysis['flags']);

        // URL and link analysis
        $url_analysis = $this->analyzeURLsInContent($content);
        $analysis['score'] += $url_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $url_analysis['flags']);

        // Language and encoding analysis
        $language_analysis = $this->analyzeLanguagePatterns($content);
        $analysis['score'] += $language_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $language_analysis['flags']);

        // Check for duplicate content
        $duplicate_analysis = $this->checkDuplicateContent($content);
        $analysis['score'] += $duplicate_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $duplicate_analysis['flags']);

        return $analysis;
    }

    /**
     * Analyze email reputation
     */
    private function analyzeEmailReputation($email)
    {
        $analysis = ['score' => 0, 'flags' => []];

        if (empty($email)) {
            $analysis['score'] += 3;
            $analysis['flags'][] = 'No email address provided';
            return $analysis;
        }

        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $analysis['score'] += 5;
            $analysis['flags'][] = 'Invalid email format';
            return $analysis;
        }

        // Check domain reputation
        $domain = substr(strrchr($email, "@"), 1);
        $domain_analysis = $this->analyzeDomainReputation($domain);
        $analysis['score'] += $domain_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $domain_analysis['flags']);

        // Check for temporary/disposable email services
        if ($this->isDisposableEmail($domain)) {
            $analysis['score'] += 6;
            $analysis['flags'][] = 'Disposable email service detected';
        }

        // Check email submission history
        $history_analysis = $this->checkEmailHistory($email);
        $analysis['score'] += $history_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $history_analysis['flags']);

        return $analysis;
    }

    /**
     * Analyze form fields for suspicious patterns
     */
    private function analyzeFormFields($data)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Check for honeypot field triggers
        if (!empty($data['website']) || !empty($data['url'])) {
            $analysis['score'] += 10;
            $analysis['flags'][] = 'Honeypot field triggered';
        }

        // Analyze field consistency
        if (!empty($data['fname']) && !empty($data['lname'])) {
            $name_analysis = $this->analyzeNameFields($data['fname'], $data['lname']);
            $analysis['score'] += $name_analysis['score'];
            $analysis['flags'] = array_merge($analysis['flags'], $name_analysis['flags']);
        }

        // Check phone number format if provided
        if (!empty($data['telephone'])) {
            $phone_analysis = $this->analyzePhoneNumber($data['telephone']);
            $analysis['score'] += $phone_analysis['score'];
            $analysis['flags'] = array_merge($analysis['flags'], $phone_analysis['flags']);
        }

        return $analysis;
    }

    /**
     * Analyze submission patterns and timing
     */
    private function analyzeSubmissionPatterns($data, $context)
    {
        $analysis = ['score' => 0, 'flags' => []];

        $ip_address = $data['ip_address'] ?? '';

        // Check submission timing patterns
        $timing_analysis = $this->analyzeSubmissionTiming($ip_address, $context);
        $analysis['score'] += $timing_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $timing_analysis['flags']);

        // Check for bot-like behavior
        $behavior_analysis = $this->analyzeBotBehavior($data);
        $analysis['score'] += $behavior_analysis['score'];
        $analysis['flags'] = array_merge($analysis['flags'], $behavior_analysis['flags']);

        return $analysis;
    }

    /**
     * Get advanced spam pattern definitions
     */
    private function getAdvancedSpamPatterns()
    {
        return [
            'pharmaceutical' => [
                'weight' => 4,
                'keywords' => [
                    'viagra',
                    'cialis',
                    'weight loss',
                    'diet pills',
                    'pharmacy online',
                    'prescription drugs',
                    'pain relief',
                    'male enhancement',
                    'anti-aging',
                    'supplements'
                ]
            ],
            'gambling_casino' => [
                'weight' => 3,
                'keywords' => [
                    'casino',
                    'poker',
                    'blackjack',
                    'slots',
                    'lottery',
                    'jackpot',
                    'betting',
                    'gambling',
                    'odds',
                    'win money'
                ]
            ],
            'adult_content' => [
                'weight' => 4,
                'keywords' => [
                    'adult dating',
                    'porn',
                    'sex',
                    'escort',
                    'webcam',
                    'xxx',
                    'nude',
                    'erotic',
                    'dating site'
                ]
            ],
            'phishing_security' => [
                'weight' => 5,
                'keywords' => [
                    'verify account',
                    'suspended account',
                    'click here now',
                    'urgent action required',
                    'confirm identity',
                    'security alert',
                    'unusual activity',
                    'account locked'
                ]
            ],
            'generic_spam' => [
                'weight' => 1,
                'keywords' => [
                    'limited time offer',
                    'act now',
                    'special offer',
                    'free trial',
                    'no obligation',
                    'risk free',
                    'call now',
                    'click here',
                    'unsubscribe',
                    'congratulations',
                    'winner',
                    'selected'
                ]
            ]
        ];
    }

    /**
     * Rate limiting with escalation - FIXED VERSION
     */
    private function checkRateLimit($ip_address)
    {
        $result = ['allowed' => true, 'severity' => 0, 'message' => ''];

        // FIXED: Store constant in variable to allow passing by reference
        $time_window = self::RATE_LIMIT_WINDOW;

        // Check recent submissions
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts, 
                   MAX(created_at) as last_attempt,
                   MIN(created_at) as first_attempt
            FROM spam_log 
            WHERE ip_address = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");

        $stmt->bind_param('si', $ip_address, $time_window);
        $stmt->execute();
        $rate_data = $stmt->get_result()->fetch_assoc();

        if ($rate_data['attempts'] >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            $result['allowed'] = false;
            $result['severity'] = min(10, $rate_data['attempts'] - self::RATE_LIMIT_MAX_ATTEMPTS + 3);
            $result['message'] = "Rate limit exceeded: {$rate_data['attempts']} attempts";
        }

        return $result;
    }

    /**
     * Initialize required database tables
     */
    private function initializeTables()
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS spam_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45),
                email VARCHAR(255),
                content TEXT,
                spam_score INT,
                risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL'),
                flags JSON,
                action_taken ENUM('ALLOW', 'FLAG', 'BLOCK', 'QUARANTINE'),
                context VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time (ip_address, created_at),
                INDEX idx_email (email),
                INDEX idx_score (spam_score)
            )",

            "CREATE TABLE IF NOT EXISTS ip_reputation (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) UNIQUE,
                reputation_score INT DEFAULT 0,
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_whitelisted BOOLEAN DEFAULT FALSE,
                is_blacklisted BOOLEAN DEFAULT FALSE,
                country_code VARCHAR(2),
                notes TEXT,
                INDEX idx_ip (ip_address),
                INDEX idx_score (reputation_score)
            )",

            "CREATE TABLE IF NOT EXISTS spam_patterns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pattern_type ENUM('keyword', 'regex', 'domain', 'email'),
                pattern_value VARCHAR(500),
                weight INT DEFAULT 1,
                category VARCHAR(50),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($tables as $sql) {
            try {
                $this->conn->query($sql);
            } catch (Exception $e) {
                error_log("SpamPrevention table creation failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Check content length patterns
     */
    private function analyzeContentLength($content)
    {
        $analysis = ['score' => 0, 'flags' => []];
        $length = strlen($content);

        if ($length < 10) {
            $analysis['score'] += 3;
            $analysis['flags'][] = 'Very short message content';
        } elseif ($length > 5000) {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'Unusually long message content';
        }

        return $analysis;
    }

    /**
     * Analyze URLs in content
     */
    private function analyzeURLsInContent($content)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Count URLs
        $url_count = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($url_count > 3) {
            $analysis['score'] += min(5, $url_count - 3);
            $analysis['flags'][] = "Multiple URLs detected ({$url_count})";
        }

        // Check for suspicious URL patterns
        if (preg_match('/bit\.ly|tinyurl|short|redirect/i', $content)) {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'URL shortener detected';
        }

        return $analysis;
    }

    /**
     * Analyze language patterns
     */
    private function analyzeLanguagePatterns($content)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Check for excessive capitalization
        $caps_ratio = strlen(preg_replace('/[^A-Z]/', '', $content)) / strlen($content);
        if ($caps_ratio > 0.3) {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'Excessive capitalization';
        }

        // Check for non-English characters (could indicate foreign spam)
        if (preg_match('/[^\x00-\x7F]/', $content)) {
            $analysis['score'] += 1;
            $analysis['flags'][] = 'Non-ASCII characters detected';
        }

        return $analysis;
    }

    /**
     * Check for duplicate content - FIXED VERSION
     */
    private function checkDuplicateContent($content)
    {
        $analysis = ['score' => 0, 'flags' => []];

        if (empty($content)) {
            return $analysis;
        }

        $content_hash = md5(strtolower(trim($content)));

        try {
            // FIXED: Store variable to pass by reference
            $content_hash_param = $content_hash;

            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as similar_count 
                FROM spam_log 
                WHERE MD5(LOWER(TRIM(content))) = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->bind_param('s', $content_hash_param);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['similar_count'] > 0) {
                $analysis['score'] += min(5, $result['similar_count'] * 2);
                $analysis['flags'][] = "Similar message submitted {$result['similar_count']} times recently";
            }
        } catch (Exception $e) {
            error_log("SpamPrevention duplicate check failed: " . $e->getMessage());

            // Fallback: simple hash-based check
            try {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*) as similar_count 
                    FROM spam_log 
                    WHERE MD5(LOWER(TRIM(content))) = ? 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ");
                $stmt->bind_param('s', $content_hash_param);
                $stmt->execute();
                $fallback_result = $stmt->get_result()->fetch_assoc();

                if ($fallback_result['similar_count'] > 0) {
                    $analysis['score'] += min(5, $fallback_result['similar_count'] * 2);
                    $analysis['flags'][] = "Similar message submitted {$fallback_result['similar_count']} times recently (fallback check)";
                }
            } catch (Exception $fallback_error) {
                error_log("SpamPrevention fallback similarity check failed: " . $fallback_error->getMessage());
                // Continue without similarity checking
            }
        }

        return $analysis;
    }

    /**
     * Check if IP is blacklisted
     */
    private function isIPBlacklisted($ip_address)
    {
        $stmt = $this->conn->prepare("
            SELECT is_blacklisted 
            FROM ip_reputation 
            WHERE ip_address = ? AND is_blacklisted = TRUE
        ");
        $stmt->bind_param('s', $ip_address);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ? true : false;
    }

    /**
     * Check if IP is whitelisted
     */
    private function isIPWhitelisted($ip_address)
    {
        $stmt = $this->conn->prepare("
            SELECT is_whitelisted 
            FROM ip_reputation 
            WHERE ip_address = ? AND is_whitelisted = TRUE
        ");
        $stmt->bind_param('s', $ip_address);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ? true : false;
    }

    /**
     * Analyze domain reputation
     */
    private function analyzeDomainReputation($domain)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Check against known problematic domains
        $suspicious_domains = [
            'mailinator.com',
            '10minutemail.com',
            'guerrillamail.com',
            'tempmail.org',
            'yopmail.com'
        ];

        if (in_array(strtolower($domain), $suspicious_domains)) {
            $analysis['score'] += 3;
            $analysis['flags'][] = 'Known temporary email domain';
        }

        return $analysis;
    }

    /**
     * Check if email domain is disposable
     */
    private function isDisposableEmail($domain)
    {
        $disposable_patterns = [
            '10minutemail',
            'tempmail',
            'guerrillamail',
            'mailinator',
            'yopmail',
            'temp-mail',
            'throwaway'
        ];

        foreach ($disposable_patterns as $pattern) {
            if (stripos($domain, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check email submission history
     */
    private function checkEmailHistory($email)
    {
        $analysis = ['score' => 0, 'flags' => []];

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as submission_count,
                       AVG(spam_score) as avg_score
                FROM spam_log 
                WHERE email = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['submission_count'] > 5) {
                $analysis['score'] += min(4, $result['submission_count'] - 5);
                $analysis['flags'][] = "Frequent submitter ({$result['submission_count']} submissions)";
            }

            if ($result['avg_score'] > 5) {
                $analysis['score'] += 2;
                $analysis['flags'][] = 'Email has history of suspicious activity';
            }
        } catch (Exception $e) {
            error_log("SpamPrevention email history check failed: " . $e->getMessage());
        }

        return $analysis;
    }

    /**
     * Analyze name fields for consistency
     */
    private function analyzeNameFields($fname, $lname)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Check for obvious fake names
        $fake_names = ['test', 'asdf', 'qwerty', 'admin', 'user', 'name'];

        if (in_array(strtolower($fname), $fake_names) || in_array(strtolower($lname), $fake_names)) {
            $analysis['score'] += 3;
            $analysis['flags'][] = 'Suspicious name pattern detected';
        }

        // Check for single character names
        if (strlen($fname) < 2 || strlen($lname) < 2) {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'Unusually short name';
        }

        return $analysis;
    }

    /**
     * Analyze phone number format
     */
    private function analyzePhoneNumber($phone)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Remove all non-digit characters
        $digits_only = preg_replace('/\D/', '', $phone);

        if (strlen($digits_only) < 10) {
            $analysis['score'] += 2;
            $analysis['flags'][] = 'Invalid phone number format';
        }

        // Check for obvious fake numbers
        if (preg_match('/^(555|000|123)/', $digits_only)) {
            $analysis['score'] += 1;
            $analysis['flags'][] = 'Suspicious phone number pattern';
        }

        return $analysis;
    }

    /**
     * Analyze submission timing patterns
     */
    private function analyzeSubmissionTiming($ip_address, $context)
    {
        $analysis = ['score' => 0, 'flags' => []];

        try {
            // Check for rapid submissions
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as recent_count
                FROM spam_log 
                WHERE ip_address = ? 
                AND context = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->bind_param('ss', $ip_address, $context);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result['recent_count'] > 1) {
                $analysis['score'] += $result['recent_count'] * 2;
                $analysis['flags'][] = "Rapid submissions detected ({$result['recent_count']} in 5 minutes)";
            }
        } catch (Exception $e) {
            error_log("SpamPrevention timing analysis failed: " . $e->getMessage());
        }

        return $analysis;
    }

    /**
     * Analyze for bot-like behavior
     */
    private function analyzeBotBehavior($data)
    {
        $analysis = ['score' => 0, 'flags' => []];

        // Check user agent if available
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($user_agent)) {
            $analysis['score'] += 3;
            $analysis['flags'][] = 'No user agent provided';
        } elseif (preg_match('/bot|crawler|spider|scraper/i', $user_agent)) {
            $analysis['score'] += 5;
            $analysis['flags'][] = 'Bot user agent detected';
        }

        return $analysis;
    }

    /**
     * Analyze geographic reputation (placeholder)
     */
    private function analyzeGeographicReputation($ip_address)
    {
        return ['score' => 0, 'flags' => []];
    }

    /**
     * Check external IP reputation (placeholder)
     */
    private function checkExternalIPReputation($ip_address)
    {
        return ['score' => 0, 'flags' => []];
    }

    /**
     * Calculate final assessment
     */
    private function calculateFinalAssessment($analysis)
    {
        $score = $analysis['score'];

        if ($score >= self::SPAM_THRESHOLD_BLOCK) {
            $analysis['risk_level'] = 'CRITICAL';
            $analysis['action'] = 'BLOCK';
            $analysis['is_spam'] = true;
        } elseif ($score >= self::SPAM_THRESHOLD_HIGH) {
            $analysis['risk_level'] = 'HIGH';
            $analysis['action'] = 'QUARANTINE';
            $analysis['is_spam'] = true;
        } elseif ($score >= self::SPAM_THRESHOLD_MEDIUM) {
            $analysis['risk_level'] = 'MEDIUM';
            $analysis['action'] = 'FLAG';
        } elseif ($score >= self::SPAM_THRESHOLD_LOW) {
            $analysis['risk_level'] = 'LOW';
            $analysis['action'] = 'ALLOW';
        }

        $analysis['confidence'] = min(100, ($score / self::SPAM_THRESHOLD_BLOCK) * 100);

        return $analysis;
    }

    /**
     * Log spam analysis results
     */
    private function logSpamAnalysis($data, $analysis, $context)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO spam_log (
                    ip_address, 
                    email, 
                    content, 
                    spam_score, 
                    risk_level, 
                    flags, 
                    action_taken, 
                    context
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $ip_address = $data['ip_address'] ?? 'unknown';
            $email = $data['email'] ?? '';
            $content = $data['message'] ?? '';
            $spam_score = $analysis['score'];
            $risk_level = $analysis['risk_level'];
            $flags_json = json_encode($analysis['flags']);
            $action_taken = $analysis['action'];

            $stmt->bind_param(
                'ssssisss',
                $ip_address,
                $email,
                $content,
                $spam_score,
                $risk_level,
                $flags_json,
                $action_taken,
                $context
            );

            $stmt->execute();
        } catch (Exception $e) {
            error_log("SpamPrevention logging failed: " . $e->getMessage());
        }
    }

    /**
     * Update IP reputation score
     */
    public function updateIPReputation($ip_address, $score_adjustment, $notes = '')
    {
        try {
            // First, try to update existing record
            $stmt = $this->conn->prepare("
                UPDATE ip_reputation 
                SET reputation_score = reputation_score + ?,
                    last_seen = NOW(),
                    notes = CONCAT(IFNULL(notes, ''), ?)
                WHERE ip_address = ?
            ");
            $stmt->bind_param('iss', $score_adjustment, $notes, $ip_address);
            $stmt->execute();

            // If no rows affected, insert new record
            if ($stmt->affected_rows === 0) {
                $stmt = $this->conn->prepare("
                    INSERT INTO ip_reputation (ip_address, reputation_score, notes)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    reputation_score = reputation_score + VALUES(reputation_score),
                    last_seen = NOW(),
                    notes = CONCAT(IFNULL(notes, ''), VALUES(notes))
                ");
                $stmt->bind_param('sis', $ip_address, $score_adjustment, $notes);
                $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("SpamPrevention IP reputation update failed: " . $e->getMessage());
        }
    }

    /**
     * Add IP to blacklist
     */
    public function blacklistIP($ip_address, $reason = '')
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO ip_reputation (ip_address, is_blacklisted, notes)
                VALUES (?, TRUE, ?)
                ON DUPLICATE KEY UPDATE
                is_blacklisted = TRUE,
                notes = CONCAT(IFNULL(notes, ''), ?)
            ");
            $stmt->bind_param('sss', $ip_address, $reason, $reason);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("SpamPrevention IP blacklist failed: " . $e->getMessage());
        }
    }

    /**
     * Add IP to whitelist
     */
    public function whitelistIP($ip_address, $reason = '')
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO ip_reputation (ip_address, is_whitelisted, notes)
                VALUES (?, TRUE, ?)
                ON DUPLICATE KEY UPDATE
                is_whitelisted = TRUE,
                notes = CONCAT(IFNULL(notes, ''), ?)
            ");
            $stmt->bind_param('sss', $ip_address, $reason, $reason);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("SpamPrevention IP whitelist failed: " . $e->getMessage());
        }
    }

    /**
     * Get spam statistics
     */
    public function getSpamStats($days = 30)
    {
        $stats = [
            'total_submissions' => 0,
            'spam_blocked' => 0,
            'spam_rate' => 0,
            'top_spam_ips' => [],
            'risk_breakdown' => []
        ];

        try {
            // Total submissions
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM spam_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['total_submissions'] = $result['total'];

            // Spam blocked
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as blocked
                FROM spam_log 
                WHERE action_taken IN ('BLOCK', 'QUARANTINE')
                AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats['spam_blocked'] = $result['blocked'];

            // Calculate spam rate
            if ($stats['total_submissions'] > 0) {
                $stats['spam_rate'] = round(($stats['spam_blocked'] / $stats['total_submissions']) * 100, 2);
            }

            // Top spam IPs
            $stmt = $this->conn->prepare("
                SELECT ip_address, COUNT(*) as attempts, AVG(spam_score) as avg_score
                FROM spam_log 
                WHERE spam_score > ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY ip_address
                ORDER BY attempts DESC, avg_score DESC
                LIMIT 10
            ");
            $threshold = self::SPAM_THRESHOLD_MEDIUM;
            $stmt->bind_param('ii', $threshold, $days);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $stats['top_spam_ips'][] = $row;
            }

            // Risk level breakdown
            $stmt = $this->conn->prepare("
                SELECT risk_level, COUNT(*) as count
                FROM spam_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY risk_level
            ");
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $stats['risk_breakdown'][$row['risk_level']] = $row['count'];
            }
        } catch (Exception $e) {
            error_log("SpamPrevention stats generation failed: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Clean old spam log entries
     */
    public function cleanOldLogs($days = 90)
    {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM spam_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param('i', $days);
            $stmt->execute();

            return $stmt->affected_rows;
        } catch (Exception $e) {
            error_log("SpamPrevention log cleanup failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent spam attempts for monitoring
     */
    public function getRecentSpamAttempts($limit = 50)
    {
        $attempts = [];

        try {
            $stmt = $this->conn->prepare("
                SELECT ip_address, email, spam_score, risk_level, 
                       flags, action_taken, context, created_at
                FROM spam_log 
                WHERE spam_score > ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $threshold = self::SPAM_THRESHOLD_LOW;
            $stmt->bind_param('ii', $threshold, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $row['flags'] = json_decode($row['flags'], true);
                $attempts[] = $row;
            }
        } catch (Exception $e) {
            error_log("SpamPrevention recent attempts retrieval failed: " . $e->getMessage());
        }

        return $attempts;
    }

    /**
     * Export spam data for analysis
     */
    public function exportSpamData($start_date, $end_date, $format = 'array')
    {
        $data = [];

        try {
            $stmt = $this->conn->prepare("
                SELECT ip_address, email, spam_score, risk_level,
                       flags, action_taken, context, created_at
                FROM spam_log 
                WHERE created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
            ");
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $row['flags'] = json_decode($row['flags'], true);
                $data[] = $row;
            }

            if ($format === 'csv') {
                return $this->convertToCSV($data);
            }
        } catch (Exception $e) {
            error_log("SpamPrevention data export failed: " . $e->getMessage());
        }

        return $data;
    }

    /**
     * Convert data array to CSV format
     */
    private function convertToCSV($data)
    {
        if (empty($data)) {
            return '';
        }

        $csv = '';
        $headers = array_keys($data[0]);
        $csv .= implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $row['flags'] = is_array($row['flags']) ? implode(';', $row['flags']) : $row['flags'];
            $csv .= implode(',', array_map(function ($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }

        return $csv;
    }

    /**
     * Train spam filter with feedback
     */
    public function trainFilter($email, $content, $is_spam, $context = 'training')
    {
        try {
            // Log the training data
            $data = [
                'ip_address' => 'training',
                'email' => $email,
                'message' => $content
            ];

            $analysis = [
                'score' => $is_spam ? 15 : -5,
                'risk_level' => $is_spam ? 'CRITICAL' : 'LOW',
                'flags' => [$is_spam ? 'Manual spam classification' : 'Manual ham classification'],
                'action' => $is_spam ? 'BLOCK' : 'ALLOW'
            ];

            $this->logSpamAnalysis($data, $analysis, $context);

            return true;
        } catch (Exception $e) {
            error_log("SpamPrevention training failed: " . $e->getMessage());
            return false;
        }
    }
}
