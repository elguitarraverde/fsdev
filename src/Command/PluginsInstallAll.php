<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use FacturaScripts\Core\Plugins;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginsInstallAll extends Command
{
    protected static $defaultName = 'plugins:install';
    protected static $defaultDescription = 'Instala TODOS los Plugins(que sean gratuidos y no sean para patrocinadores).';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pathMyFiles = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles';
        if (false === is_dir($pathMyFiles)) {
            mkdir($pathMyFiles);
        }

        $pathPluginsList = $pathMyFiles . DIRECTORY_SEPARATOR . 'plugins.json';
        if (false === is_file($pathPluginsList)) {
            touch($pathPluginsList);
        }

        $pathPluginZipFile = FS_FOLDER . DIRECTORY_SEPARATOR . 'plugin.zip';

        $io = new SymfonyStyle($input, $output);

        // Obtenemos el listado de Plugins
        $urlPluginInfoList = 'https://facturascripts.com/PluginInfoList';
        $pluginInfoList = json_decode(file_get_contents($urlPluginInfoList), true);

        // Plugins excluidos(patrocinadores)
        $excludedPlugins = [
            'AgrupadorMultiAlmacen',
            'Compras2pvp',
            'DiarioAgrupado',
            'DobleAgente',
            'EjecucionVentas',
            'EnviarDocumentos',
            'EtiquetasEnvio',
            'FacturarDias',
            'FacturasCompraUniq',
            'FechaVentas',
            'Fixer',
            'GrupoClientesCRM',
            'PlantillaObsTop',
            'RemesasSEPAprov',
            'ServiciosFabricacion',
            'Ticketbai',
            'Traducciones',
        ];

        foreach ($pluginInfoList as $pluginInfo) {

            if (false === in_array($pluginInfo['name'], $excludedPlugins)) {

                $pathPlugins = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins';
                $pathPlugin = $pathPlugins . DIRECTORY_SEPARATOR . $pluginInfo['name'];

                if (false === is_dir($pathPlugin) && $pluginInfo['price'] == 0) {

                    $urlDownloadPlugin = "https://facturascripts.com/DownloadBuild/" . $pluginInfo['idplugin'] . "/stable";

                    if (file_put_contents($pathPluginZipFile, @file_get_contents($urlDownloadPlugin)) > 0) {
                        if (true === Plugins::add($pathPluginZipFile)) {
                            $io->info('Plugin ' . $pluginInfo['name'] . ', instalado correctamente.');
                        } else {
                            $io->error('Ha fallado la descarga del plugin ' . $pluginInfo['name'] . '.');
                        }
                    } else {
                        $io->error('Ha fallado la descarga del plugin ' . $pluginInfo['name'] . '.');
                    }
                }
            }
        }

        if (is_file($pathPluginZipFile)) {
            unlink($pathPluginZipFile);
        }

        Plugins::deploy();

        $io->success('Intalación de Plugins completada.');

        return Command::SUCCESS;
    }
}
