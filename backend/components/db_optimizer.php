<?php
/**
 * Оптимизация запросов к базе данных
 * Кэширование результатов запросов, подготовленные запросы
 */

class DBOptimizer {
    private static $queryCache = [];
    private static $cacheEnabled = true;
    
    /**
     * Кэшированный запрос
     */
    public static function cachedQuery($pdo, $sql, $params = [], $ttl = 300) {
        if (!self::$cacheEnabled) {
            return self::executeQuery($pdo, $sql, $params);
        }
        
        $cacheKey = md5($sql . serialize($params));
        
        // Проверяем кэш
        if (isset(self::$queryCache[$cacheKey])) {
            $cached = self::$queryCache[$cacheKey];
            if (time() - $cached['time'] < $ttl) {
                return $cached['data'];
            }
        }
        
        // Выполняем запрос
        $result = self::executeQuery($pdo, $sql, $params);
        
        // Сохраняем в кэш
        self::$queryCache[$cacheKey] = [
            'data' => $result,
            'time' => time()
        ];
        
        return $result;
    }
    
    /**
     * Выполнение запроса с подготовленными параметрами
     */
    private static function executeQuery($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[DB Optimizer] Query failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Очистка кэша запросов
     */
    public static function clearCache() {
        self::$queryCache = [];
    }
    
    /**
     * Включить/выключить кэширование
     */
    public static function setCacheEnabled($enabled) {
        self::$cacheEnabled = $enabled;
    }
}
