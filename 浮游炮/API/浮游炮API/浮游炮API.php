<?php
declare(strict_types=1);

@header('Content-type: application/json');
ini_set('display_errors', '0');

final class JsonResponse
{
	public static function success(array $data = [], int $code = 200): void
	{
		http_response_code($code);
		echo json_encode(['success' => true, 'data' => $data]);
		exit;
	}

	public static function error(string $message, int $code = 400): void
	{
		http_response_code($code);
		echo json_encode(['success' => false, 'error' => $message]);
		exit;
	}
}

final class DatabaseConfig
{
	private static ?self $instance = null;
	private array $config;

	private function __construct()
	{
		$this->config = [
			"host"     => "127.0.0.1",
			"username" => "ELDment",
			"password" => "EG4bpEmntS8xeJFh",
			"dbname"   => "aet_community",
			"port"     => 3306,
			"options"  => [
				"MYSQLI_OPT_CONNECT_TIMEOUT" => 3,
				"MYSQLI_OPT_READ_TIMEOUT"    => 10
			]
		];
	}

	public static function getInstance(): self
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function getConfig(): array
	{
		return $this->config;
	}
}

final class Database
{
	private mysqli $connection;

	public function __construct()
	{
		$config = DatabaseConfig::getInstance()->getConfig();
		$this->connection = new mysqli();

		try {
			$this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $config['options']["MYSQLI_OPT_CONNECT_TIMEOUT"]);
			$this->connection->options(MYSQLI_OPT_READ_TIMEOUT, $config['options']["MYSQLI_OPT_READ_TIMEOUT"]);
			$this->connection->real_connect(
				$config['host'],
				$config['username'],
				$config['password'],
				$config['dbname'],
				$config['port']
			);

			if ($this->connection->connect_error) {
				throw new RuntimeException('Database connection failed');
			}
		} catch (mysqli_sql_exception $e) {
			JsonResponse::error('Database error', 502);
		}
	}

	public function query(string $sql, array $params = []): mysqli_result
	{
		$stmt = $this->connection->prepare($sql);
		if (!$stmt) {
			throw new RuntimeException('Query preparation failed');
		}

		if ($params) {
			$types = str_repeat('s', count($params));
			$stmt->bind_param($types, ...$params);
		}

		if (!$stmt->execute()) {
			throw new RuntimeException('Query execution failed');
		}

		return $stmt->get_result();
	}

	public function getAffectedRows(): int
	{
		return $this->connection->affected_rows;
	}

	public function __destruct()
	{
		$this->connection->close();
	}
}

final class RequestParam
{
	public static function get(string $name): string
	{
		$value = $_POST[$name] ?? $_GET[$name] ?? null;
		
		if (self::isEmpty($value)) {
			JsonResponse::error("Missing parameter: $name", 400);
		}
		
		return trim($value);
	}

	private static function isEmpty(?string $value): bool
	{
		return $value === null || trim($value) === '';
	}
}

final class RailCannonService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function getData(string $steamId): void
	{
		if ($this->isSpecialEventActive()) {
			JsonResponse::success([
				'RailCannon' => true,
				'R' => 235,
				'G' => 35,
				'B' => 40
			]);
		}

		$result = $this->db->query(
			"SELECT * FROM `railcannon` 
			WHERE `Steam32` = ? AND `Expiretime` > UNIX_TIMESTAMP()",
			[$steamId]
		);

		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$response = [
				'RailCannon' => true,
				'Unix' => (int)$row['Expiretime'],
				'R' => (int)$row['R'],
				'G' => (int)$row['G'],
				'B' => (int)$row['B']
			];
		} else {
			$response = [
				'RailCannon' => false,
				'Unix' => -1,
				'R' => 255,
				'G' => 255,
				'B' => 255
			];
		}

		JsonResponse::success($response);
	}

	private function isSpecialEventActive(): bool
	{
		$now = time();
		return $now > 1674230400 && $now < 1674338400;
	}
}

final class CDKeyManager
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function generateKey(int $days, int $count = 1): void
	{
		$keys = [];
		for ($i = 0; $i < $count; $i++) {
			$key = base64_encode(json_encode([
				'salt' => bin2hex(random_bytes(16)),
				'days' => $days,
				'nonce' => random_int(PHP_INT_MIN, PHP_INT_MAX)
			]));
			$keys[] = ['Key' => $key];
		}
		JsonResponse::success($count === 1 ? $keys[0] : $keys);
	}

	public function validateKey(string $steamId, string $steamName, string $cdKey): void
	{
		try {
			$decoded = json_decode(base64_decode($cdKey), true);
			
			if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['days'])) {
				throw new InvalidArgumentException('Invalid CDKey');
			}

			$this->db->query('START TRANSACTION');

			if ($this->isKeyUsed($cdKey)) {
				throw new RuntimeException('CDKey already used');
			}

			$this->processKeyActivation($steamId, $steamName, $decoded['days'], $cdKey);
			
			$this->db->query('COMMIT');
			JsonResponse::success(['Msg' => 'Success']);

		} catch (Exception $e) {
			$this->db->query('ROLLBACK');
			JsonResponse::error($e->getMessage());
		}
	}

	private function isKeyUsed(string $cdKey): bool
	{
		$result = $this->db->query(
			"SELECT 1 FROM `rc_cdkey` WHERE `CDKey` = ?",
			[$cdKey]
		);
		return $result->num_rows > 0;
	}

	private function processKeyActivation(string $steamId, string $steamName, int $days, string $cdKey): void
	{
		$seconds = $days * 86400;
		$expireTime = time() + $seconds;

		$result = $this->db->query(
			"INSERT INTO `railcannon` 
			(`Steam32`, `Expiretime`, `name`, `R`, `G`, `B`) 
			VALUES (?, ?, ?, 65, 230, 125)
			ON DUPLICATE KEY UPDATE 
			`Expiretime` = `Expiretime` + VALUES(`Expiretime`)",
			[$steamId, $expireTime, $steamName]
		);

		if ($this->db->getAffectedRows() === 0) {
			throw new RuntimeException('Failed to update railcannon');
		}

		// 记录已使用CDKey
		$this->db->query(
			"INSERT INTO `rc_cdkey` (`CDKey`, `Steam`, `Time`) 
			VALUES (?, ?, NOW())",
			[$cdKey, $steamId]
		);
	}
}

final class Application
{
	public function run(): void
	{
		try {
			$operation = RequestParam::get('Operate');
			$handlers = [
				'Update'  => fn() => $this->handleUpdate(),
				'CDKey'   => fn() => $this->handleCDKey(),
				'Make'    => fn() => $this->handleMakeKey(),
				'default' => fn() => $this->handleGetData()
			];

			($handlers[$operation] ?? $handlers['default'])();
			
		} catch (Exception $e) {
			JsonResponse::error($e->getMessage());
		}
	}

	private function handleUpdate(): void
	{
		$steamId = RequestParam::get('Steam32');
		$r = $this->validateColor(RequestParam::get('R'));
		$g = $this->validateColor(RequestParam::get('G'));
		$b = $this->validateColor(RequestParam::get('B'));

		$db = new Database();
		$db->query(
			"UPDATE `railcannon` 
			SET R = ?, G = ?, B = ? 
			WHERE Steam32 = ?",
			[$r, $g, $b, $steamId]
		);

		JsonResponse::success([
			'Status' => true,
			'SetR'  => true,
			'SetG'  => true,
			'SetB'  => true
		]);
	}

	private function validateColor(string $value): int
	{
		$color = filter_var($value, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 0, 'max_range' => 255]
		]);
		
		if ($color === false) {
			throw new InvalidArgumentException('Invalid color value');
		}
		return $color;
	}

	private function handleCDKey(): void
	{
		$cdKeyManager = new CDKeyManager(new Database());
		$cdKeyManager->validateKey(
			RequestParam::get('Steam32'),
			RequestParam::get('Name'),
			RequestParam::get('Key')
		);
	}

	private function handleMakeKey(): void
	{
		$days = (int)RequestParam::get('Days');
		$count = (int)($_REQUEST['Count'] ?? 1);
		
		(new CDKeyManager(new Database()))->generateKey($days, $count);
	}

	private function handleGetData(): void
	{
		(new RailCannonService(new Database()))->getData(
			RequestParam::get('Steam32')
		);
	}
}

(new Application())->run();
