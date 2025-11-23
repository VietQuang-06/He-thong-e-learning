<?php
class Database {
    private static $host = "localhost";
    private static $db   = "elearning_ptit";
    private static $user = "root";
    private static $pass = "";
    private static $pdo  = null;

    public static function pdo() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$db . ";charset=utf8mb4",
                    self::$user,
                    self::$pass
                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Kết nối database thất bại: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
