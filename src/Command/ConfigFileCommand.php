<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigFileCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    protected static $defaultName = 'config:generate';
    protected static $defaultDescription = 'Genera 3 archivos de configuración para desarrollo(NO USAR EN PRODUCCIÓN): Uno para usar en el servidor local con mysql(config.php), otro para tests en mysql(config-mysql.php) y otro para tests en postgresql(config-postgresql.php).';
    /**
     * @var InputInterface
     */
    private $input;

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Sobreescribe el archivo');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);

        $this->generarConfigFile('config.php', 'mysql', 'localhost', '3306', 'facturascripts', 'root', 'toor', true);
        $this->generarConfigFile('config-mysql.php', 'mysql', 'localhost', '3306', 'facturascripts_tests', 'root', 'toor', false);
        $this->generarConfigFile('config-postgresql.php', 'postgresql', 'localhost', '5432', 'facturascripts_tests', 'postgres', 'toor', false);

        return Command::SUCCESS;
    }

    private function generarConfigFile(string $nombre_archivo, string $FS_DB_TYPE, string $FS_DB_HOST, string $FS_DB_PORT, string $FS_DB_NAME, string $FS_DB_USER, string $FS_DB_PASS, bool $FS_DEBUG): void
    {
        $path = FS_FOLDER . DIRECTORY_SEPARATOR . $nombre_archivo;

        if(file_exists($path) && false == $this->input->getOption('force')){
            $this->io->error('El archivo ' . $nombre_archivo . ' ya existe. Use --force para sobreescribirlo');
            return;
        }

        $archivo_guardado = $this->escribirEnArchivo($path, $FS_DB_TYPE, $FS_DB_HOST, $FS_DB_PORT, $FS_DB_NAME, $FS_DB_USER, $FS_DB_PASS, $FS_DEBUG);
        if(false == $archivo_guardado){
            $this->io->error('El archivo ' . $nombre_archivo . ' no se ha podido crear.');
            return;
        }

        $this->io->success('El archivo ' . $nombre_archivo . ' se ha creado correctamente.');
    }

    private function escribirEnArchivo(string $path, string $FS_DB_TYPE, string $FS_DB_HOST, string $FS_DB_PORT, string $FS_DB_NAME, string $FS_DB_USER, string $FS_DB_PASS, bool $FS_DEBUG)
    {
        $debug = $FS_DEBUG ? 'true' : 'false';
        
        $data = "<?php\n\n";

        $data .= "define('FS_COOKIES_EXPIRE', 604800);\n\n";

        $data .= "define('FS_LANG', 'es_ES');\n";
        $data .= "define('FS_TIMEZONE', 'Europe/Madrid');\n";
        $data .= "define('FS_ROUTE', '');\n\n";

        $data .= "define('FS_DB_TYPE', '$FS_DB_TYPE');\n";
        $data .= "define('FS_DB_HOST', '$FS_DB_HOST');\n";
        $data .= "define('FS_DB_PORT', '$FS_DB_PORT');\n";
        $data .= "define('FS_DB_NAME', '$FS_DB_NAME');\n";
        $data .= "define('FS_DB_USER', '$FS_DB_USER');\n";
        $data .= "define('FS_DB_PASS', '$FS_DB_PASS');\n";
        $data .= "define('FS_DB_FOREIGN_KEYS', true);\n";
        $data .= "define('FS_DB_TYPE_CHECK', true);\n";
        $data .= "define('FS_MYSQL_CHARSET', 'utf8');\n";
        $data .= "define('FS_MYSQL_COLLATE', 'utf8_bin');\n\n";

        $data .= "define('FS_HIDDEN_PLUGINS', '');\n";
        $data .= "define('FS_DEBUG', $debug);\n";
        $data .= "define('FS_DISABLE_ADD_PLUGINS', false);\n";
        $data .= "define('FS_DISABLE_RM_PLUGINS', false);\n\n";

        $data .= "define('FS_NF0', 2);\n\n";

        return file_put_contents($path, $data);
    }
}
