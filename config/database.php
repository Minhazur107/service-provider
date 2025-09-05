<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 's24_services');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Ensure proper parameter binding
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Connection failed: " . $e->getMessage());
    }
}

// Helper function to execute queries
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            if (is_numeric($value)) {
                $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            } else {
                $stmt->bindValue($key + 1, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        // Log the error details for debugging
        error_log("Database Error: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
        throw $e;
    }
}

// Helper function to fetch single row
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

// Helper function to fetch all rows
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}
?> 