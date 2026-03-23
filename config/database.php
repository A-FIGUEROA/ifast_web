<?php
// config/database.php
// Configuración de conexión a la base de datos

class Database {
    private $host = "localhost";
    private $db_name = "iaplokxt_ifast_db";
    private $username = "iaplokxt_ifast_shipping";
    private $password = "_U+;5F@W-kge";
    public $conn;

    // Obtener la conexión a la base de datos
    public function getConnection() {
        $this->conn = null;

        try {
            // Corregido: quitar las comillas alrededor de las variables
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password
            );
            
            // Configurar atributos de PDO
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Sincronizar zona horaria de MySQL con PHP para evitar desfase en cálculos de tiempo
            $this->conn->exec("SET time_zone = '" . date('P') . "'");
            
        } catch(PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
            die();
        }

        return $this->conn;
    }
}
?>