<?php
/**
 * Database Configuration Example
 * 
 * INSTRUCCIONES:
 * 1. Copia este archivo como 'database.php' en el mismo directorio
 * 2. Actualiza los valores con tus credenciales reales de cPanel
 * 3. NUNCA subas 'database.php' a Git (está en .gitignore)
 */

// Database credentials - UPDATE THESE!
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');  // En cPanel: cpanel_user_dbname
define('DB_USER', 'your_database_user');  // En cPanel: cpanel_user_dbuser
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone()
    {
    }

    // Prevent unserialization
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB()
{
    return Database::getInstance()->getConnection();
}
