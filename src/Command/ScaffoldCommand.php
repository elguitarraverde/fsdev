<?php

namespace Elguitarraverde\Fsdev\Command;

use Elguitarraverde\Fsdev\Lib\Columna;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ScaffoldCommand extends Command
{
    protected static $defaultName = 'scaffold';
    protected static $defaultDescription = 'Crea los archivos necesarios para crear tablas, Modelos, EditViews, ListViews, para un plugin de FacturaScripts';
    private bool $globalFields = true;
    private string $pluginPath;

    protected function configure()
    {
        $this->addArgument('directorio-plugin', InputArgument::REQUIRED, 'Directorio del Plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->pluginPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input->getArgument('directorio-plugin')), DIRECTORY_SEPARATOR);
        $pathYaml = implode(DIRECTORY_SEPARATOR, [FS_FOLDER, $this->pluginPath, 'scaffold.yaml']);
        if(!file_exists($pathYaml)){
            $io->error('No existe el archivo ' . $pathYaml);
            return Command::FAILURE;
        }

        $datos = Yaml::parseFile($pathYaml);
        foreach ($datos as $item) {
            $nombreModelo = $item['modelo'];

            $campos = $this->camposPredefinidosAntes();
            foreach ($item['campos'] as $campo) {
                $campos[] = new Columna([
                    'nombre' => $campo['nombre'],
                    'titulo' => $campo['titulo'],
                    'tipo' => $campo['tipo'] ?? 'character varying',
                    'longitud' => 255,
                    'numcolumns' => $campo['numcolumns'] ?? null,
                    'hideInListView' => $campo['hideInListView'] ?? false,
                    'groupid' => $campo['groupid'] ?? null,
                ]);
            }
            array_push($campos, ...$this->camposPredefinidosDespues());

            $this->createModelAction($nombreModelo, $item['tabla'], $campos, $item['grupos']);
        }

        return Command::SUCCESS;
    }

    private function createModelAction($nombreModelo, $nombreTabla, $campos, $grupos)
    {
        // CREAMOS EL MODELO
        $modelPath = $this->pluginPath . DIRECTORY_SEPARATOR . 'Model/';
        $modelFileName = $modelPath . $nombreModelo . '.php';
        $this->createFolder($modelPath);
        $this->createModelByFields($modelFileName, $nombreTabla, $campos, $nombreModelo);

        // CREAMOS LA TABLA
        $tablePath = $this->pluginPath . DIRECTORY_SEPARATOR . 'Table/';
        $tableFilename = $tablePath . $nombreTabla . '.xml';
        $this->createFolder($tablePath);
        $this->createXMLTableByFields($tableFilename, $nombreTabla, $campos);

        // CREAMOS EL EDIT CONTROLLER
        $this->createControllerEdit($nombreModelo, $campos, $grupos);

        // CREAMOS EL LIST CONTROLLER
        $this->createControllerList($nombreModelo, $campos);
    }

    private function createFolder(string $path)
    {
        if (file_exists($path)) {
            return;
        }

        mkdir($path, 0755, true);
    }

    private function createModelByFields(string $fileName, string $tableName, array $fields, string $name)
    {
        $properties = '';
        $primaryColumn = '';
        $clear = '';
        $clearExclude = ['creation_date', 'nick', 'last_nick', 'last_update'];
        $test = '';
        $testExclude = ['creation_date', 'nick', 'last_nick', 'last_update'];

        foreach ($fields as $field) {
            // para especificar el tipo de propiedad
            $typeProperty = '';

            // Para el método clear()
            switch ($field->tipo) {
                case 'serial':
                    $typeProperty = 'int';
                    $primaryColumn = $field->nombre;
                    break;

                case 'integer':
                    $typeProperty = 'int';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = 0;' . "\n";
                    }
                    break;

                case 'double precision':
                    $typeProperty = 'float';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = 0.0;' . "\n";
                    }
                    break;

                case 'boolean':
                    $typeProperty = 'bool';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = false;' . "\n";
                    }
                    break;

                case 'timestamp':
                    $typeProperty = 'string';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = date(self::DATETIME_STYLE);' . "\n";
                    }
                    break;

                case 'date':
                    $typeProperty = 'string';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = date(self::DATE_STYLE);' . "\n";
                    }
                    break;

                case 'time':
                    $typeProperty = 'string';
                    if (false === in_array($field->nombre, $clearExclude)) {
                        $clear .= '        $this->' . $field->nombre . ' = date(self::HOUR_STYLE);' . "\n";
                    }
                    break;

                case 'text':
                    $typeProperty = 'string';
                    if (false === in_array($field->nombre, $testExclude)) {
                        $test .= '        $this->' . $field->nombre . ' = Tools::noHtml($this->' . $field->nombre . ');' . "\n";
                    }
                    break;
            }

            if ($field->primary) {
                $primaryColumn = $field->nombre;
            }

            if (strpos($field->tipo, 'character varying') !== false) {
                $typeProperty = 'string';
                if (false === in_array($field->nombre, $testExclude)) {
                    $test .= '        $this->' . $field->nombre . ' = Tools::noHtml($this->' . $field->nombre . ');' . "\n";
                }
            }

            // Para la creación de properties
            $properties .= "    /** @var " . $typeProperty . " */\n";
            $properties .= "    public $" . $field->nombre . ";" . "\n\n";
        }

        $sample = '<?php' . "\n\n"
            . 'namespace FacturaScripts\\' . $this->getNamespace() . '\Model;' . "\n\n"
            . "use FacturaScripts\Core\Model\Base\ModelClass;\n"
            . "use FacturaScripts\Core\Model\Base\ModelTrait;\n"
            . "use FacturaScripts\Core\Tools;\n";

        if ($this->globalFields) {
            $sample .= "use FacturaScripts\Core\Session;\n\n";
        }

        $sample .= 'class ' . $name . ' extends ModelClass' . "\n"
            . '{' . "\n"
            . '    use ModelTrait;' . "\n\n"
            . $properties
            . "    public function clear() \n"
            . "    {\n"
            . '        parent::clear();' . "\n"
            . $clear
            . '    }' . "\n\n"
            . "    public static function primaryColumn(): string\n"
            . "    {\n"
            . '        return "' . $primaryColumn . '";' . "\n"
            . '    }' . "\n\n"
            . "    public static function tableName(): string\n"
            . "    {\n"
            . '        return "' . $tableName . '";' . "\n"
            . '    }' . "\n\n"
            . "    public function test(): bool\n"
            . "    {\n";

        if ($this->globalFields) {
            $sample .= '        $this->creation_date = $this->creation_date ?? Tools::dateTime();' . "\n"
                . '        $this->nick = $this->nick ?? Session::user()->nick;' . "\n";
        }

        $sample .= $test
            . '        return parent::test();' . "\n"
            . '    }';

        if ($this->globalFields) {
            $sample .= "\n\n"
                . '    protected function saveUpdate(array $values = []): bool' . "\n"
                . '    {' . "\n"
                . '        $this->last_nick = Session::user()->nick;' . "\n"
                . '        $this->last_update = Tools::dateTime();' . "\n"
                . '        return parent::saveUpdate($values);' . "\n"
                . '    }' . "\n";
        }

        $sample .= '}' . "\n";
        file_put_contents($fileName, $sample);
    }

    private function getNamespace(): string
    {
        $ini = parse_ini_file($this->pluginPath . DIRECTORY_SEPARATOR . 'facturascripts.ini');
        return 'Plugins\\' . $ini['name'];
    }

    private function createXMLTableByFields(string $tableFilename, string $tableName, array $fields)
    {
        $columns = '';
        $constraints = '';
        foreach ($fields as $field) {
            if ($field->tipo === 'character varying') {
                $field->tipo .= '(' . $field->longitud . ')';
            }

            $columns .= "    <column>\n"
                . "        <name>$field->nombre</name>\n"
                . "        <type>$field->tipo</type>\n";

            if ($field->tipo === 'serial' || $field->primary || $field->requerido) {
                $columns .= "        <null>NO</null>\n";
            }

            $columns .= "    </column>\n";

            if ($field->tipo === 'serial' || $field->primary) {
                $constraints .= "    <constraint>\n"
                    . '        <name>' . $tableName . "_pkey</name>\n"
                    . '        <type>PRIMARY KEY (' . $field->nombre . ")</type>\n"
                    . "    </constraint>\n";
            }

            if ($field->nombre === 'nick' || $field->nombre === 'last_nick') {
                $constraints .= "    <constraint>\n"
                    . "        <name>ca_" . $tableName . "_users_" . $field->nombre . "</name>\n"
                    . "        <type>FOREIGN KEY (" . $field->nombre . ") REFERENCES users (nick) ON DELETE SET NULL ON UPDATE CASCADE</type>\n"
                    . "    </constraint>\n";
            }
        }

        $sample = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<table>' . "\n"
            . $columns
            . $constraints
            . '</table>' . "\n";
        file_put_contents($tableFilename, $sample);
    }

    private function camposPredefinidosAntes()
    {
        $fields[] = new Columna([
            'display' => 'none',
            'nombre' => 'id',
            'primary' => true,
            'requerido' => true,
            'tipo' => 'serial'
        ]);

        return $fields;
    }

    private function camposPredefinidosDespues()
    {
        $fields[] = new Columna([
            'display' => 'none',
            'nombre' => 'creation_date',
            'requerido' => true,
            'tipo' => 'timestamp'
        ]);
        $fields[] = new Columna([
            'display' => 'none',
            'nombre' => 'last_update',
            'tipo' => 'timestamp'
        ]);
        $fields[] = new Columna([
            'display' => 'none',
            'nombre' => 'nick',
            'tipo' => 'character varying',
            'longitud' => 50
        ]);
        $fields[] = new Columna([
            'display' => 'none',
            'nombre' => 'last_nick',
            'tipo' => 'character varying',
            'longitud' => 50
        ]);

        return $fields;
    }

    private function createControllerEdit(string $modelName, array $fields, array $grupos)
    {
        $filePath = $this->pluginPath . DIRECTORY_SEPARATOR . 'Controller/';
        $fileName = $filePath . 'Edit' . $modelName . '.php';

        $this->createFolder($filePath);

        $sample = file_get_contents(__DIR__ . "/../SAMPLES/EditController.php.sample");
        $template = str_replace(['[[NAME_SPACE]]', '[[MODEL_NAME]]'], [$this->getNamespace(), $modelName], $sample);
        file_put_contents($fileName, $template);


        $xmlPath = $this->pluginPath . DIRECTORY_SEPARATOR . 'XMLView/';
        $xmlFilename = $xmlPath . 'Edit' . $modelName . '.xml';
        $this->createFolder($xmlPath);

        $this->createXMLViewByFields($xmlFilename, $fields, 'edit', false, $grupos);
    }

    private function createControllerList(string $modelName, array $fields)
    {
        $menu = $modelName;
        $title = $modelName;

        $filePath = $this->pluginPath . DIRECTORY_SEPARATOR . 'Controller/';
        $fileName = $filePath . 'List' . $modelName . '.php';
        $this->createFolder($filePath);

        $sample = file_get_contents(__DIR__ . "/../SAMPLES/ListController.php.sample");
        $template = str_replace(
            ['[[NAME_SPACE]]', '[[MODEL_NAME]]', '[[TITLE]]', '[[MENU]]'],
            [$this->getNamespace(), $modelName, $title, $menu],
            $sample
        );
        file_put_contents($fileName, $template);

        $xmlPath = $this->pluginPath . DIRECTORY_SEPARATOR . 'XMLView/';
        $xmlFilename = $xmlPath . 'List' . $modelName . '.xml';
        $this->createFolder($xmlPath);

        $this->createXMLViewByFields($xmlFilename, $fields, 'list');
    }

    /**
     * @param string $xmlFilename
     * @param Columna[] $fields
     * @param string $type
     * @param bool $extension
     *
     * @return void
     */
    private function createXMLViewByFields(string $xmlFilename, array $fields, string $type, bool $extension = false, array $grupos = [])
    {
        // agrupamos las columnas por las que tienen grupo
        // y las que no tienen grupo
        $columnasConGrupo = [];
        $columnasSinGrupo = [];
        foreach ($fields as $columna) {
            if($columna->groupid){
                $columnasConGrupo[$columna->groupid][] = $columna;
            }else{
                $columnasSinGrupo[] = $columna;
            }
        }

        $tabForColumns = 12;
        if ($type === 'list') { // Es un ListController
            $tabForColumns = 8;
        }

        $order = 110;
        $columns = '';

        $fieldDefault = [];
        foreach ($fields as $field) {
            if($type === 'list' && $field->hideInListView){
                $field->display = 'none';
            }

            // guardamos las columnas por defecto aparte
            if ($this->globalFields && in_array($field->nombre, ['creation_date', 'last_nick', 'last_update', 'nick'])) {
                $fieldDefault[] = $field;
                continue;
            }

            // si la columna es de tipo serial o primary, la ponemos al principio
            if ($field->tipo === 'serial' || $field->primary) {
                $columns = $this->getWidget($field, 100, $tabForColumns) . $columns;
                continue;
            }

            $columns .= $this->getWidget($field, $order, $tabForColumns);
            $order += 10;
        }

        if (count($fieldDefault) > 0) {
            // ordenamos el array de columnas poniendo este orden: creation_date, nick, last_update, last_nick
            usort($fieldDefault, function ($a, $b) {
                $order = ['creation_date' => 1, 'nick' => 2, 'last_update' => 3, 'last_nick' => 4];
                return $order[$a->nombre] <=> $order[$b->nombre];
            });
        }

        $sample = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<view>' . "\n"
            . '    <columns>' . "\n";

        switch ($type) {
            case 'list': // Es un ListController
                $sample .= $columns;

                // añadimos las columnas por defecto al final
                if ($this->globalFields) {
                    foreach ($fieldDefault as $field) {
                        $sample .= $this->getWidget($field, $order, $tabForColumns);
                        $order += 10;
                    }
                }
                break;

            case 'edit': // Es un EditController
                $sample .= '        <group name="data" numcolumns="12">' . "\n"
                    . $this->getXmlForColumns($columnasSinGrupo)
                    . '        </group>' . "\n";

                foreach ($grupos as $grupoId => $grupo) {
                    $sample .= '        <group name="' . $grupo['name'] . '" title="'.$grupo['title'].'" icon="'.$grupo['icon'].'" numcolumns="12">' . "\n"
                        . $this->getXmlForColumns($columnasConGrupo[$grupoId])
                        . '        </group>' . "\n";
                }

                // añadimos el grupo de logs
                if ($this->globalFields) {
                    $order = 100;
                    $sample .= '        <group name="logs" numcolumns="12">' . "\n";
                    foreach ($fieldDefault as $field) {
                        $sample .= $this->getWidget($field, $order, $tabForColumns);
                        $order += 10;
                    }
                    $sample .= '        </group>' . "\n";
                }
                break;

            default: // No es ninguna de las opciones de antes
                return;
        }

        $sample .= '    </columns>' . "\n"
            . '</view>' . "\n";

        file_put_contents($xmlFilename, $sample);
    }

    private function getWidget(Columna $column, string $order, int $tabForColums): string
    {
        $spaces = str_repeat(" ", $tabForColums);
        $sample = '';

        $max = is_null($column->maximo) ? '' : ' max="' . $column->maximo . '"';
        $maxlength = is_null($column->longitud) ? '' : ' maxlength="' . $column->longitud . '"';
        $min = is_null($column->minimo) ? '' : ' min="' . $column->minimo . '"';
        $nombreColumn = $column->nombre;
        $nombreWidget = $column->nombre;
        $step = is_null($column->step) ? '' : ' step="' . $column->step . '"';
        $requerido = $column->requerido ? ' required="true"' : '';

        switch ($nombreWidget) {
            case 'creation_date':
                $nombreColumn = 'creation-date';
                break;

            case 'last_nick':
                $nombreColumn = 'last-user';
                break;

            case 'last_update':
                $nombreColumn = 'last-update';
                break;

            case 'nick':
                $nombreColumn = 'user';
                break;
        }

        switch ($nombreWidget) {
            case 'last_nick':
            case 'nick':
//                $sample .= $spaces . '<column name="' . $nombreColumn . '" order="' . $order . '">' . "\n"
//                    . $spaces . '    <widget type="select" fieldname="' . $nombreWidget . '"' . $requerido . '>' . "\n"
//                    . $spaces . '        <values source="users" fieldcode="nick" fieldtile="nick"/>' . "\n"
//                    . $spaces . '    </widget>' . "\n"
//                    . $spaces . "</column>\n";
                return $sample;
        }

        $numcolumns = $column->numcolumns ?? 3;
        $titulo = $column->titulo ?? '';

        switch ($column->tipo) {
            default:
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="text" fieldname="' . $nombreWidget . '"' . $maxlength . $requerido . '/>' . "\n";
                break;

            case 'serial':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="text" fieldname="' . $nombreWidget . '" readonly="true"/>' . "\n";
                break;

            case 'double precision':
            case 'integer':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="number" fieldname="' . $nombreWidget . '"' . $max . $min . $step . $requerido . '/>' . "\n";
                break;

            case 'boolean':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="checkbox" fieldname="' . $nombreWidget . '"' . $requerido . '/>' . "\n";
                break;

            case 'text':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="textarea" fieldname="' . $nombreWidget . '"' . $requerido . '/>' . "\n";
                break;

            case 'timestamp':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="datetime" fieldname="' . $nombreWidget . '"' . $requerido . '/>' . "\n";
                break;

            case 'date':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="date" fieldname="' . $nombreWidget . '"' . $requerido . '/>' . "\n";
                break;

            case 'time':
                $sample .= $spaces . '<column name="' . $nombreColumn . '" title="' . $titulo . '" numcolumns="' . $numcolumns . '" display="' . $column->display . '" order="' . $order . '">' . "\n"
                    . $spaces . '    <widget type="time" fieldname="' . $nombreWidget . '"' . $requerido . '/>' . "\n";
                break;
        }
        $sample .= $spaces . "</column>\n";
        return $sample;
    }

    /**
     * @param Columna[] $columnas
     * @return string
     */
    private function getXmlForColumns(array $columnas): string
    {
        $order = 110;
        $columns = '';

        foreach ($columnas as $field) {
            $columns .= $this->getWidget($field, $order, 12);
            $order += 10;
        }

        return $columns;
    }
}