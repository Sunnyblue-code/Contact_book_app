<?php
class RateLimiter
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->createTable();
    }

    private function createTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            requests INT NOT NULL DEFAULT 1,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ip_action (ip_address, action)
        )";

        $this->pdo->exec($sql);
    }

    public function isAllowed($ip, $action, $maxRequests, $timeWindow)
    {
        try {
            // Clean up old entries
            $this->cleanup($timeWindow);

            // Get current count
            $stmt = $this->pdo->prepare("
                SELECT requests, UNIX_TIMESTAMP(timestamp) as last_request
                FROM rate_limits 
                WHERE ip_address = ? AND action = ?
            ");
            $stmt->execute([$ip, $action]);
            $result = $stmt->fetch();

            $currentTime = time();

            if (!$result) {
                // First request
                $stmt = $this->pdo->prepare("
                    INSERT INTO rate_limits (ip_address, action, requests, timestamp)
                    VALUES (?, ?, 1, NOW())
                ");
                $stmt->execute([$ip, $action]);
                return true;
            }

            $timeDiff = $currentTime - $result['last_request'];

            if ($timeDiff > $timeWindow) {
                // Reset counter if time window has passed
                $stmt = $this->pdo->prepare("
                    UPDATE rate_limits 
                    SET requests = 1, timestamp = NOW()
                    WHERE ip_address = ? AND action = ?
                ");
                $stmt->execute([$ip, $action]);
                return true;
            }

            if ($result['requests'] >= $maxRequests) {
                return false;
            }

            // Increment counter
            $stmt = $this->pdo->prepare("
                UPDATE rate_limits 
                SET requests = requests + 1
                WHERE ip_address = ? AND action = ?
            ");
            $stmt->execute([$ip, $action]);
            return true;
        } catch (PDOException $e) {
            error_log("Rate limiter error: " . $e->getMessage());
            return true; // Allow request if there's an error
        }
    }

    private function cleanup($timeWindow)
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limits 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$timeWindow]);
        } catch (PDOException $e) {
            error_log("Rate limiter cleanup error: " . $e->getMessage());
        }
    }
}
