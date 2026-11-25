<?php

// Runs data/web/json_api.php for one request of ApiTestCase in a process of its own. The request comes from the JSON
// file in argv[1]; every legacy function and class json_api.php calls is a recorder; the status code, the output and
// the recorded calls go to the result file named in the request.

namespace ApiHarness {

    /** The recorded calls and the scripted answers of one request */
    final class Calls
    {
        /** @var list<array{0: string, 1: array}> */
        public static array $calls = [];
        /** @var list<array{call: string, args?: array, return?: mixed, messages?: list<array>, once?: bool}> */
        public static array $script = [];
        /** Methods that return a recorder unless scripted, so that chains like Module::fromGlobals()->api->x() work */
        private const CHAINED = ['fromGlobals', 'create', 'prepare', 'query'];

        public static function record(string $name, array $args, ?string $method = null): mixed
        {
            $args = self::plain($args);
            self::$calls[] = [$name, $args];
            foreach (self::$script as $i => $entry) {
                if ($entry['call'] !== $name || !self::startsWith($args, $entry['args'] ?? [])) {
                    continue;
                }
                foreach ($entry['messages'] ?? [] as $message) {
                    $_SESSION['return'][] = $message;
                }
                if (!empty($entry['once'])) {
                    unset(self::$script[$i]);
                }
                return $entry['return'] ?? null;
            }
            return ($method !== null && in_array($method, self::CHAINED, true))
                ? new Recorder(preg_replace('/::(fromGlobals|create)$/', '', $name)) : null;
        }

        /** Arguments as JSON data: recorders and other objects become "@<name>" */
        private static function plain(mixed $value): mixed
        {
            if ($value instanceof Recorder) {
                return '@' . $value->label();
            }
            if (is_object($value)) {
                return '@' . get_class($value);
            }
            return is_array($value) ? array_map([self::class, 'plain'], $value) : $value;
        }

        private static function startsWith(array $args, array $prefix): bool
        {
            foreach (array_values($prefix) as $i => $expected) {
                if (!array_key_exists($i, $args) || $args[$i] !== $expected) {
                    return false;
                }
            }
            return true;
        }
    }

    /** Stand-in for objects and classes: records method calls, reads give further recorders */
    class Recorder implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
    {
        private string $recorderLabel;

        public function __construct(mixed ...$args)
        {
            $this->recorderLabel = (isset($args[0]) && is_string($args[0]) && static::class === self::class) ? $args[0] : static::class;
            if (static::class !== self::class) {
                Calls::record('new ' . static::class, $args);
            }
        }

        public function label(): string
        {
            return $this->recorderLabel;
        }

        public function __get(string $name): self
        {
            return new self($this->recorderLabel . '->' . $name);
        }

        public function __call(string $name, array $args): mixed
        {
            return Calls::record($this->recorderLabel . '->' . $name, $args, $name);
        }

        public static function __callStatic(string $name, array $args): mixed
        {
            return Calls::record(static::class . '::' . $name, $args, $name);
        }

        public function count(): int
        {
            return 0;
        }

        public function getIterator(): \Iterator
        {
            return new \EmptyIterator();
        }

        public function offsetExists(mixed $offset): bool
        {
            return false;
        }

        public function offsetGet(mixed $offset): mixed
        {
            return null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
        }

        public function offsetUnset(mixed $offset): void
        {
        }

        public function jsonSerialize(): mixed
        {
            return ['@' => $this->recorderLabel];
        }
    }

    /** php://input with the request body (the CLI has none); every other php:// stream is the real one */
    final class InputStream
    {
        public static string $body = '';
        /** @var resource|null */
        public $context;
        private int $position = 0;
        /** @var resource|null */
        private $inner = null;

        public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
        {
            if (strtolower($path) === 'php://input') {
                return true;
            }
            stream_wrapper_restore('php');
            $this->inner = fopen($path, $mode) ?: null;
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', self::class);
            return $this->inner !== null;
        }

        public function stream_read(int $count): string|false
        {
            if ($this->inner !== null) {
                return fread($this->inner, $count);
            }
            $chunk = substr(self::$body, $this->position, $count);
            $this->position += strlen($chunk);
            return $chunk;
        }

        public function stream_write(string $data): int
        {
            return ($this->inner !== null) ? (int)fwrite($this->inner, $data) : 0;
        }

        public function stream_eof(): bool
        {
            return ($this->inner !== null) ? feof($this->inner) : $this->position >= strlen(self::$body);
        }

        public function stream_stat(): array|false
        {
            return ($this->inner !== null) ? fstat($this->inner) : ['size' => strlen(self::$body)];
        }

        public function stream_close(): void
        {
            if ($this->inner !== null) {
                fclose($this->inner);
            }
        }
    }

    /** Names of the functions $file calls that are neither PHP functions nor declared in $file */
    function calledFunctions(string $file): array
    {
        $tokens = array_values(array_filter(token_get_all(file_get_contents($file)),
            fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $declared = [];
        $called = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }
            $previous = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;
            if (is_array($previous) && $previous[0] === T_FUNCTION) {
                $declared[strtolower($token[1])] = true;
                continue;
            }
            if ($next !== '(' || (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true))) {
                continue;
            }
            $called[strtolower($token[1])] = $token[1];
        }
        return array_values(array_filter($called, fn(string $name): bool => !isset($declared[strtolower($name)]) && !function_exists($name)));
    }
}

namespace {

    use ApiHarness\Calls;
    use ApiHarness\InputStream;

    $request = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $jsonApi = $request['json_api'];

    foreach (ApiHarness\calledFunctions($jsonApi) as $name) {
        eval('function ' . $name . '(...$args) { return \ApiHarness\Calls::record(' . var_export($name, true) . ', $args); }');
    }
    spl_autoload_register(function (string $class): void {
        $parts = explode('\\', ltrim($class, '\\'));
        $short = array_pop($parts);
        $namespace = implode('\\', $parts);
        eval(($namespace !== '' ? "namespace $namespace; " : '') . "class $short extends \\ApiHarness\\Recorder {}");
    });

    $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/webroot';
    $_SERVER['REQUEST_METHOD'] = $request['method'];
    $_SERVER['REQUEST_URI'] = '/api/v1/' . $request['query'];
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    if (isset($request['content_type'])) {
        $_SERVER['HTTP_CONTENT_TYPE'] = $request['content_type'];
    }
    foreach ($request['server'] ?? [] as $key => $value) {
        $_SERVER[$key] = $value;
    }
    $_GET = ['query' => $request['query']] + ($request['get'] ?? []);
    $_POST = $request['post'] ?? [];
    $GLOBALS['__harness_session'] = $request['session'] ?? [];
    $GLOBALS['__harness_globals'] = $request['globals'] ?? [];
    Calls::$script = $request['script'] ?? [];
    InputStream::$body = (string)($request['body'] ?? '');
    stream_wrapper_unregister('php');
    stream_wrapper_register('php', InputStream::class);

    register_shutdown_function(function () use ($request): void {
        $output = '';
        while (ob_get_level() > 0) {
            $output = ob_get_clean() . $output;
        }
        $error = error_get_last();
        $fatal = ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true));
        stream_wrapper_restore('php');
        file_put_contents($request['result'], json_encode([
            'status' => http_response_code() ?: 200,
            'body' => $output,
            'calls' => Calls::$calls,
            'session_return' => $_SESSION['return'] ?? null,
            'error' => $fatal ? $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] : null,
        ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    });

    ob_start();
    require $jsonApi;
}
