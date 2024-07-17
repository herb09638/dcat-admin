<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Http\Auth\Permission;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Scaffold\ControllerCreator;
use Dcat\Admin\Scaffold\LangCreator;
use Dcat\Admin\Scaffold\MigrationCreator;
use Dcat\Admin\Scaffold\ModelCreator;
use Dcat\Admin\Scaffold\RepositoryCreator;
use Dcat\Admin\Support\Helper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

class ScaffoldController extends Controller
{
    public static $dbTypes = [
        'string', 'integer', 'text', 'float', 'double', 'decimal', 'boolean', 'date', 'time',
        'dateTime', 'timestamp', 'char', 'mediumText', 'longText', 'tinyInteger', 'smallInteger',
        'mediumInteger', 'bigInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
        'unsignedInteger', 'unsignedBigInteger', 'enum', 'json', 'jsonb', 'dateTimeTz', 'timeTz',
        'timestampTz', 'nullableTimestamps', 'binary', 'ipAddress', 'macAddress',
    ];

    public static $dataTypeMap = [
        'int'                => 'integer',
        'int@unsigned'       => 'unsignedInteger',
        'tinyint'            => 'tinyInteger',
        'tinyint@unsigned'   => 'unsignedTinyInteger',
        'smallint'           => 'smallInteger',
        'smallint@unsigned'  => 'unsignedSmallInteger',
        'mediumint'          => 'mediumInteger',
        'mediumint@unsigned' => 'unsignedMediumInteger',
        'bigint'             => 'bigInteger',
        'bigint@unsigned'    => 'unsignedBigInteger',

        'date'      => 'date',
        'time'      => 'time',
        'datetime'  => 'dateTime',
        'timestamp' => 'timestamp',

        'enum'   => 'enum',
        'json'   => 'json',
        'binary' => 'binary',

        'float'   => 'float',
        'double'  => 'double',
        'decimal' => 'decimal',

        'varchar'    => 'string',
        'char'       => 'char',
        'text'       => 'text',
        'mediumtext' => 'mediumText',
        'longtext'   => 'longText',
    ];

    public function index(Content $content)
    {
        if (! config('app.debug')) {
            Permission::error();
        }

        if ($tableName = request('singular')) {
            return $this->singular($tableName);
        }

        Admin::requireAssets('select2');
        Admin::requireAssets('sortable');

        $dbTypes = static::$dbTypes;
        $dataTypeMap = static::$dataTypeMap;
        $action = URL::current();
        $namespaceBase = 'App\\'.implode('\\', array_map(function ($name) {
            return Str::studly($name);
        }, explode(DIRECTORY_SEPARATOR, substr(config('admin.directory'), strlen(app_path().DIRECTORY_SEPARATOR)))));
        $tables = collect($this->getDatabaseColumns())->map(function ($v) {
            return array_keys($v);
        })->toArray();

        return $content
            ->title(trans('admin.scaffold.header'))
            ->description(' ')
            ->body(view(
                'admin::helpers.scaffold',
                compact('dbTypes', 'action', 'tables', 'dataTypeMap', 'namespaceBase')
            ));
    }

    protected function singular($tableName)
    {
        return [
            'status' => 1,
            'value'  => Str::singular($tableName),
        ];
    }

    public function store(Request $request)
    {
        if (! config('app.debug')) {
            Permission::error();
        }

        $paths = [];
        $message = '';

        $creates = (array) $request->get('create');
        $table = Helper::slug($request->get('table_name'), '_');
        $controller = $request->get('controller_name');
        $model = $request->get('model_name');
        $repository = $request->get('repository_name');

        try {
            // 1. Create model.
            if (in_array('model', $creates)) {
                $modelCreator = new ModelCreator($table, $model);

                $paths['model'] = $modelCreator->create(
                    $request->get('primary_key'),
                    $request->get('timestamps') == 1,
                    $request->get('soft_deletes') == 1
                );
            }

            // 2. Create controller.
            if (in_array('controller', $creates)) {
                $paths['controller'] = (new ControllerCreator($controller))
                    ->create(in_array('repository', $creates) ? $repository : $model);
            }

            // 3. Create migration.
            if (in_array('migration', $creates)) {
                $migrationName = 'create_'.$table.'_table';

                $paths['migration'] = (new MigrationCreator(app('files')))->buildBluePrint(
                    $request->get('fields'),
                    $request->get('primary_key', 'id'),
                    $request->get('timestamps') == 1,
                    $request->get('soft_deletes') == 1
                )->create($migrationName, database_path('migrations'), $table);
            }

            if (in_array('lang', $creates)) {
                $paths['lang'] = (new LangCreator($request->get('fields')))
                    ->create($controller, $request->get('translate_title'));
            }

            if (in_array('repository', $creates)) {
                $paths['repository'] = (new RepositoryCreator())
                    ->create($model, $repository);
            }

            // Run migrate.
            if (in_array('migrate', $creates)) {
                Artisan::call('migrate');
                $message = Artisan::output();
            }

            // Make ide helper file.
            if (in_array('migrate', $creates) || in_array('controller', $creates)) {
                try {
                    Artisan::call('admin:ide-helper', ['-c' => $controller]);

                    $paths['ide-helper'] = 'dcat_admin_ide_helper.php';
                } catch (\Throwable $e) {
                }
            }
        } catch (\Exception $exception) {
            // Delete generated files if exception thrown.
            app('files')->delete($paths);

            return $this->backWithException($exception);
        }

        return $this->backWithSuccess($paths, $message);
    }

    /**
     * @return array
     */
    public function table()
    {
        $db = addslashes(\request('db'));
        $table = \request('tb');
        if (! $table || ! $db) {
            return ['status' => 1, 'list' => []];
        }

        $tables = collect($this->getDatabaseColumns($db, $table))
            ->filter(function ($v, $k) use ($db) {
                return $k == $db;
            })->map(function ($v) use ($table) {
                return Arr::get($v, $table);
            })
            ->filter()
            ->first();

        return ['status' => 1, 'list' => $tables];
    }

    /**
     * @return array
     */
    protected function getDatabaseColumns($db = null, $tb = null)
    {
        $data = [];

        try {
            $defaultConnection = config('database.default');
            $connectionConfig = config("database.connections.{$defaultConnection}");

            $connectName = $defaultConnection;
            $value = array_merge($connectionConfig, [
                'driver' => env('DB_CONNECTION', $connectionConfig['driver']),
                'host' => env('DB_HOST', $connectionConfig['host'] ?? ''),
                'port' => env('DB_PORT', $connectionConfig['port'] ?? ''),
                'database' => env('DB_DATABASE', $connectionConfig['database'] ?? ''),
                'username' => env('DB_USERNAME', $connectionConfig['username'] ?? ''),
                'password' => env('DB_PASSWORD', $connectionConfig['password'] ?? ''),
            ]);

            // 如果您想要確保只有特定的數據庫驅動被接受，可以添加以下檢查
            $supportedDrivers = ['mysql', 'pgsql'];  // 添加您支持的驅動
            if (!in_array($value['driver'], $supportedDrivers)) {
                return [];
            }

            $connection = \DB::connection($connectName);
            $databaseType = $connection->getDriverName();
            $prefix = $value['prefix'] ?? '';
            $database = $value['database'];

            $data[$database] = [];

            if ($databaseType === 'mysql') {
                $sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE,
                               COLUMN_KEY, EXTRA, COLUMN_COMMENT, ORDINAL_POSITION, DATA_TYPE
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ?";
                $bindings = [$database];

                if ($tb) {
                    $sql .= " AND TABLE_NAME = ?";
                    $bindings[] = $prefix . $tb;
                }

                $sql .= " ORDER BY TABLE_NAME, ORDINAL_POSITION";

                $columns = $connection->select($sql, $bindings);

            } elseif ($databaseType === 'pgsql') {
                $sql = "SELECT c.table_name, c.column_name, c.data_type, c.column_default,
                               c.is_nullable, c.ordinal_position,
                               CASE WHEN pk.constraint_type = 'PRIMARY KEY' THEN 'PRI' ELSE '' END as column_key,
                               pg_catalog.col_description(format('%s.%s', c.table_schema, c.table_name)::regclass::oid, c.ordinal_position) as column_comment
                        FROM information_schema.columns c
                        LEFT JOIN (
                            SELECT ku.table_schema, ku.table_name, ku.column_name, tc.constraint_type
                            FROM information_schema.table_constraints tc
                            JOIN information_schema.key_column_usage ku ON tc.constraint_name = ku.constraint_name
                            WHERE tc.constraint_type = 'PRIMARY KEY'
                        ) pk ON c.table_schema = pk.table_schema AND c.table_name = pk.table_name AND c.column_name = pk.column_name
                        WHERE c.table_schema = 'public' AND c.table_catalog = ?";
                $bindings = [$database];

                if ($tb) {
                    $sql .= " AND c.table_name = ?";
                    $bindings[] = $prefix . $tb;
                }

                $sql .= " ORDER BY c.table_name, c.ordinal_position";

                $columns = $connection->select($sql, $bindings);
            } else {
                throw new \Exception("Unsupported database type: $databaseType");
            }

            foreach ($columns as $column) {
                $tableName = \Str::replaceFirst($prefix, '', $column->table_name);
                $columnName = $column->column_name;

                if ($databaseType === 'mysql') {
                    $type = strtolower($column->data_type);
                    if (\Str::contains(strtolower($column->column_type), 'unsigned')) {
                        $type .= '@unsigned';
                    }
                } elseif ($databaseType === 'pgsql') {
                    $type = strtolower($column->data_type);
                }

                $data[$database][$tableName][$columnName] = [
                    'type' => $type,
                    'default' => $column->column_default,
                    'nullable' => strtoupper($column->is_nullable) === 'YES',
                    'key' => $column->column_key ?? '',
                    'id' => ($column->column_key ?? '') === 'PRI',
                    'comment' => $column->column_comment ?? null,
                ];
            }
        } catch (\Throwable $e) {
        }

        return $data;
    }

    protected function backWithException(\Exception $exception)
    {
        $error = new MessageBag([
            'title'   => 'Error',
            'message' => $exception->getMessage(),
        ]);

        return redirect()->refresh()->withInput()->with(compact('error'));
    }

    protected function backWithSuccess($paths, $message)
    {
        $messages = [];

        foreach ($paths as $name => $path) {
            $messages[] = ucfirst($name).": $path";
        }

        $messages[] = "<br />$message";

        $success = new MessageBag([
            'title'   => 'Success',
            'message' => implode('<br />', $messages),
        ]);

        return redirect()->refresh()->with(compact('success'));
    }
}
