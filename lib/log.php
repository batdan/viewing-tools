<?php
namespace tools;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Log des services d'API
 */
class log
{
    /**
     * [log description]
     * @param  [type] $type [description]
     * @param  [type] $log  [description]
     * @param  [type] $nameLog [description]
     * @return [type]       [description]
     */
    public static function setLog($type, $log, $nameLog=null)
    {
        // Vérification du type
        $types = ['debug','info','notice','warning','error','critical','freeName'];
        if (!in_array($type, $types)) {
            throw new \Exception('Type autorisés : ' . implode(', ', $types), 1);
        }

        if ($type == 'freeName' && is_null($nameLog)) {
            throw new \Exception('Type freeName : le nameLog doit être précisé', 1);
        }

        // Instenciation du Logger
        $logger = new Logger(' ');

        // Mise en forme des tableaux
        if (is_array($log) || is_object($log)) {
            $log = print_r($log, true);
        }

        $fileName = is_null($nameLog) ? $type : $nameLog;
        $path = __DIR__ . '/../../../../var/log/' . $fileName . '_' . date('Y-m') . '.log';

        switch ($type)
        {
            case 'debug' :
                $logger->pushHandler(new StreamHandler( $path, Logger::DEBUG));
                $logger->debug($log);
                break;

            case 'info' :
                $logger->pushHandler(new StreamHandler( $path, Logger::INFO));
                $logger->info($log);
                break;

            case 'notice' :
                $logger->pushHandler(new StreamHandler( $path, Logger::NOTICE));
                $logger->notice($log);
                break;

            case 'warning' :
                $logger->pushHandler(new StreamHandler( $path, Logger::WARNING));
                $logger->warning($log);
                break;

            case 'error' :
                $logger->pushHandler(new StreamHandler( $path, Logger::ERROR));
                $logger->error($log);
                break;

            case 'critical' :
                $logger->pushHandler(new StreamHandler( $path, Logger::CRITICAL));
                $logger->critical($log);
                break;

            case 'freeName' :
                $logger->pushHandler(new StreamHandler( $path . $nameLog . '.log', Logger::INFO));
                $logger->freeName($log);
                break;
        }
    }
}
